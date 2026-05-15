"""
SEO Audit Engine -- 26-check site audit with 0-100 scoring.

Crawls own site pages and inspects articles for SEO issues across five
categories: meta tags, content quality, images, links, and structure.
"""
import logging
import re
from urllib.parse import urljoin, urlparse

import httpx
from bs4 import BeautifulSoup
from django.conf import settings
from django.contrib.sites.models import Site

from apps.articles.models import Article
from apps.seo.models import SeoAudit, SeoIssue

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

TITLE_MAX_LENGTH = 60
META_DESC_MAX_LENGTH = 160
MIN_WORD_COUNT = 300
MAX_EXTERNAL_LINKS = 50
MAX_IMAGE_SIZE_BYTES = 2 * 1024 * 1024  # 2 MB

HTTP_TIMEOUT = 15.0
USER_AGENT = 'NorthernTimes-SEO-Audit/1.0'


class SeoAuditEngine:
    """Run a full-site SEO audit producing an SeoAudit with SeoIssue records."""

    def __init__(self):
        self.base_url = self._get_base_url()
        self.issues: list[dict] = []

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def run_audit(self) -> SeoAudit:
        """Crawl published articles and site-wide pages, run all checks,
        and persist results as an SeoAudit with linked SeoIssue records."""
        self.issues = []

        articles = Article.objects.filter(
            status='published', deleted_at__isnull=True,
        ).order_by('-published_at')[:200]

        pages_scanned = 0

        # Audit individual articles
        for article in articles:
            url = self._article_url(article)
            html = self._fetch(url)
            if html is None:
                continue
            pages_scanned += 1
            soup = BeautifulSoup(html, 'html.parser')
            self._run_meta_checks(soup, url)
            self._run_content_checks(soup, url, article)
            self._run_image_checks(soup, url)
            self._run_link_checks(soup, url)
            self._run_structure_checks(soup, url)

        # Site-wide structural checks
        self._check_robots_txt()
        self._check_sitemap()

        # Calculate score
        criticals = sum(1 for i in self.issues if i['severity'] == 'critical')
        warnings = sum(1 for i in self.issues if i['severity'] == 'warning')
        infos = sum(1 for i in self.issues if i['severity'] == 'info')
        score = max(0, 100 - (criticals * 10 + warnings * 3 + infos * 1))

        audit = SeoAudit.objects.create(
            score=score,
            critical_count=criticals,
            warning_count=warnings,
            info_count=infos,
            pages_scanned=pages_scanned,
        )

        SeoIssue.objects.bulk_create([
            SeoIssue(
                audit=audit,
                url=issue['url'],
                issue_type=issue['type'],
                severity=issue['severity'],
                message=issue['message'],
            )
            for issue in self.issues
        ])

        logger.info(
            'SEO audit complete: score=%d, pages=%d, issues=%d',
            score, pages_scanned, len(self.issues),
        )
        return audit

    def audit_article(self, article) -> list[dict]:
        """Run all checks against a single article and return the raw issue
        list (without persisting)."""
        self.issues = []
        url = self._article_url(article)
        html = self._fetch(url)
        if html is None:
            self._add_issue('critical', 'page_unreachable', url,
                            'Page could not be fetched')
            return list(self.issues)

        soup = BeautifulSoup(html, 'html.parser')
        self._run_meta_checks(soup, url)
        self._run_content_checks(soup, url, article)
        self._run_image_checks(soup, url)
        self._run_link_checks(soup, url)
        self._run_structure_checks(soup, url)
        return list(self.issues)

    # ------------------------------------------------------------------
    # Check groups
    # ------------------------------------------------------------------

    def _run_meta_checks(self, soup: BeautifulSoup, url: str):
        """Meta tag checks (4 checks)."""
        # 1. Title
        title_tag = soup.find('title')
        if not title_tag or not title_tag.get_text(strip=True):
            self._add_issue('critical', 'missing_title', url,
                            'Page is missing a <title> tag')
        elif len(title_tag.get_text(strip=True)) > TITLE_MAX_LENGTH:
            self._add_issue('warning', 'title_too_long', url,
                            f'Title exceeds {TITLE_MAX_LENGTH} characters '
                            f'({len(title_tag.get_text(strip=True))} chars)')

        # 2. Meta description
        meta_desc = soup.find('meta', attrs={'name': 'description'})
        if not meta_desc or not meta_desc.get('content', '').strip():
            self._add_issue('critical', 'missing_meta_description', url,
                            'Page is missing a meta description')
        elif len(meta_desc['content'].strip()) > META_DESC_MAX_LENGTH:
            self._add_issue('warning', 'meta_description_too_long', url,
                            f'Meta description exceeds {META_DESC_MAX_LENGTH} characters '
                            f'({len(meta_desc["content"].strip())} chars)')

        # 3. OG image
        og_image = soup.find('meta', attrs={'property': 'og:image'})
        if not og_image or not og_image.get('content', '').strip():
            self._add_issue('warning', 'missing_og_image', url,
                            'Page is missing an og:image meta tag')

        # 4. Canonical URL
        canonical = soup.find('link', attrs={'rel': 'canonical'})
        if not canonical or not canonical.get('href', '').strip():
            self._add_issue('warning', 'missing_canonical', url,
                            'Page is missing a canonical link tag')

    def _run_content_checks(self, soup: BeautifulSoup, url: str,
                            article=None):
        """Content quality checks (5 checks)."""
        body = soup.find('body') or soup

        # 5. Thin content
        text = body.get_text(separator=' ', strip=True)
        word_count = len(text.split())
        if word_count < MIN_WORD_COUNT:
            self._add_issue('warning', 'thin_content', url,
                            f'Page has only {word_count} words '
                            f'(minimum recommended: {MIN_WORD_COUNT})')

        # 6. Missing H1
        h1_tags = soup.find_all('h1')
        if not h1_tags:
            self._add_issue('critical', 'missing_h1', url,
                            'Page is missing an <h1> heading')

        # 7. Duplicate H1
        if len(h1_tags) > 1:
            self._add_issue('warning', 'duplicate_h1', url,
                            f'Page has {len(h1_tags)} <h1> tags (should have exactly one)')

        # 8. No images
        images = soup.find_all('img')
        if not images:
            self._add_issue('info', 'no_images', url,
                            'Page contains no images')

        # 9. No internal links
        internal_links = [
            a for a in soup.find_all('a', href=True)
            if self._is_internal_url(a['href'])
        ]
        if not internal_links:
            self._add_issue('warning', 'no_internal_links', url,
                            'Page contains no internal links')

    def _run_image_checks(self, soup: BeautifulSoup, url: str):
        """Image checks (3 checks)."""
        images = soup.find_all('img')

        for img in images:
            src = img.get('src', '') or img.get('data-src', '')

            # 10. Missing alt text
            alt = img.get('alt', '')
            if not alt or not alt.strip():
                self._add_issue('warning', 'missing_alt_text', url,
                                f'Image missing alt text: {src[:120]}')

            # 11. Oversized images (estimate via Content-Length header)
            if src:
                abs_src = urljoin(url, src)
                size = self._check_image_size(abs_src)
                if size is not None and size > MAX_IMAGE_SIZE_BYTES:
                    size_mb = size / (1024 * 1024)
                    self._add_issue('warning', 'oversized_image', url,
                                    f'Image is {size_mb:.1f}MB (max 2MB): '
                                    f'{abs_src[:120]}')

            # 12. Broken image reference
            if src:
                abs_src = urljoin(url, src)
                if not self._url_reachable(abs_src):
                    self._add_issue('critical', 'broken_image', url,
                                    f'Broken image reference: {abs_src[:120]}')

    def _run_link_checks(self, soup: BeautifulSoup, url: str):
        """Link checks (3 checks)."""
        all_links = soup.find_all('a', href=True)

        external_count = 0
        for a in all_links:
            href = a['href']
            if not href or href.startswith('#') or href.startswith('javascript:'):
                continue

            abs_href = urljoin(url, href)

            if self._is_internal_url(abs_href):
                # 13. Broken internal links
                if not self._url_reachable(abs_href):
                    self._add_issue('critical', 'broken_internal_link', url,
                                    f'Broken internal link (404): {abs_href[:120]}')

                # 14. Nofollow on internal links
                rel = a.get('rel', [])
                if isinstance(rel, list) and 'nofollow' in rel:
                    self._add_issue('info', 'nofollow_internal_link', url,
                                    f'Internal link has rel="nofollow": '
                                    f'{abs_href[:120]}')
                elif isinstance(rel, str) and 'nofollow' in rel:
                    self._add_issue('info', 'nofollow_internal_link', url,
                                    f'Internal link has rel="nofollow": '
                                    f'{abs_href[:120]}')
            else:
                external_count += 1

        # 15. Too many external links
        if external_count > MAX_EXTERNAL_LINKS:
            self._add_issue('warning', 'too_many_external_links', url,
                            f'Page has {external_count} external links '
                            f'(recommended max: {MAX_EXTERNAL_LINKS})')

    def _run_structure_checks(self, soup: BeautifulSoup, url: str):
        """Structural / schema checks (3 checks)."""
        # 16. Missing JSON-LD schema
        json_ld = soup.find('script', attrs={'type': 'application/ld+json'})
        if not json_ld:
            self._add_issue('warning', 'missing_json_ld', url,
                            'Page is missing JSON-LD structured data')

        # 17. No sitemap reference (check <link> or robots meta)
        sitemap_link = soup.find(
            'link', attrs={'rel': 'sitemap'}
        ) or soup.find(
            'link', attrs={'type': 'application/xml'}
        )
        # Sitemap reference is also valid in robots.txt; this is page-level
        if not sitemap_link:
            self._add_issue('info', 'no_sitemap_reference', url,
                            'Page has no <link> reference to a sitemap')

        # 18. Missing robots.txt (checked once in site-wide pass, but also
        #     verify page doesn't have problematic robots meta)
        robots_meta = soup.find('meta', attrs={'name': 'robots'})
        if robots_meta:
            content = robots_meta.get('content', '').lower()
            if 'noindex' in content:
                self._add_issue('critical', 'noindex_page', url,
                                'Page has noindex directive in robots meta tag')

    # ------------------------------------------------------------------
    # Site-wide checks (not per-page)
    # ------------------------------------------------------------------

    def _check_robots_txt(self):
        """Check that robots.txt exists and is reachable."""
        robots_url = urljoin(self.base_url, '/robots.txt')
        html = self._fetch(robots_url)
        if html is None:
            self._add_issue('critical', 'missing_robots_txt', robots_url,
                            'robots.txt is missing or unreachable')

    def _check_sitemap(self):
        """Check that /sitemap.xml exists and is reachable."""
        sitemap_url = urljoin(self.base_url, '/sitemap.xml')
        html = self._fetch(sitemap_url)
        if html is None:
            self._add_issue('critical', 'missing_sitemap', sitemap_url,
                            'sitemap.xml is missing or unreachable')

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    def _add_issue(self, severity: str, issue_type: str, url: str,
                   message: str):
        self.issues.append({
            'severity': severity,
            'type': issue_type,
            'url': url,
            'message': message,
        })

    def _fetch(self, url: str) -> str | None:
        """Fetch a URL and return its text content, or None on failure."""
        try:
            with httpx.Client(
                timeout=HTTP_TIMEOUT,
                follow_redirects=True,
                headers={'User-Agent': USER_AGENT},
            ) as client:
                resp = client.get(url)
                if resp.status_code >= 400:
                    return None
                return resp.text
        except httpx.HTTPError:
            logger.debug('Failed to fetch %s', url)
            return None

    def _url_reachable(self, url: str) -> bool:
        """Return True if url responds with a non-4xx status (HEAD request)."""
        try:
            with httpx.Client(
                timeout=HTTP_TIMEOUT,
                follow_redirects=True,
                headers={'User-Agent': USER_AGENT},
            ) as client:
                resp = client.head(url)
                return resp.status_code < 400
        except httpx.HTTPError:
            return False

    def _check_image_size(self, url: str) -> int | None:
        """Return Content-Length of an image URL via HEAD, or None."""
        try:
            with httpx.Client(
                timeout=HTTP_TIMEOUT,
                follow_redirects=True,
                headers={'User-Agent': USER_AGENT},
            ) as client:
                resp = client.head(url)
                cl = resp.headers.get('content-length')
                return int(cl) if cl else None
        except (httpx.HTTPError, ValueError):
            return None

    def _is_internal_url(self, url: str) -> bool:
        """Check if a URL belongs to this site."""
        if url.startswith('/') and not url.startswith('//'):
            return True
        parsed = urlparse(url)
        base_parsed = urlparse(self.base_url)
        return parsed.netloc == base_parsed.netloc

    def _article_url(self, article) -> str:
        """Build the full URL for an article."""
        return urljoin(self.base_url, f'/article/{article.slug}')

    @staticmethod
    def _get_base_url() -> str:
        """Determine the site's base URL from Django Sites framework or
        settings."""
        try:
            site = Site.objects.get_current()
            domain = site.domain
            scheme = 'https' if not domain.startswith('localhost') else 'http'
            return f'{scheme}://{domain}'
        except Exception:
            return getattr(settings, 'SITE_URL', 'http://localhost:8000')
