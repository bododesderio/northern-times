"""
Multi-strategy BeautifulSoup content extractor.

Merges five extraction strategies, picking the best result by content length:
1. trafilatura (recall mode -> precision fallback)
2. newspaper3k (metadata-focused)
3. readability-lxml + CSS selectors (source-specific -> site-specific -> generic)
4. Text-density algorithm (Readability-style scoring)
5. OG/meta tag metadata

Includes paywall and truncation detection.
"""

import logging
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urlparse

from bs4 import BeautifulSoup

from .base import BaseExtractor, ExtractionResult
from .metadata import extract_metadata

logger = logging.getLogger(__name__)

MIN_CONTENT_LENGTH = 400

# ── Site-specific CSS selectors (44 domains) ──────────────────────

SITE_SELECTORS: dict[str, list[str]] = {
    # International news
    "bbc.com": ['[data-component="text-block"]', ".ssrcss-11r1m41-RichTextComponentWrapper", ".story-body__inner"],
    "bbc.co.uk": ['[data-component="text-block"]', ".ssrcss-11r1m41-RichTextComponentWrapper", ".story-body__inner"],
    "cnn.com": [".article__content", ".zn-body__paragraph"],
    "theguardian.com": [".article-body-commercial-selector", ".content--article-body", '[data-gu-name="body"]'],
    "reuters.com": [".article-body__content", ".ArticleBody__content"],
    "aljazeera.com": [".wysiwyg", ".article__body-content", "#main-content-area"],
    "nytimes.com": [".StoryBodyCompanionColumn", ".story-body", '[name="articleBody"]'],
    "washingtonpost.com": [".article-body"],
    "apnews.com": [".RichTextStoryBody", ".Article"],
    "npr.org": ["#storytext", ".storytext"],
    "france24.com": [".t-content__body", ".article__text"],
    "dw.com": [".rich-text", ".longText"],
    "voanews.com": [".body-container", ".wsw"],
    "africanews.com": [".article__text", ".article-content"],
    "theconversation.com": [".content-body"],
    "sky.com": [".sdc-article-body"],
    "skynews.com": [".sdc-article-body", ".article-body"],
    "telegraph.co.uk": [".article-body-text", ".articleBodyText"],
    "independent.co.uk": ["#main-content article", ".article-body"],
    "mirror.co.uk": [".article-body", ".body-content"],
    "foxnews.com": [".article-body", ".body-text"],
    "nbcnews.com": [".article-body", ".article-body__content"],
    "abcnews.go.com": [".Article__Content", ".article-copy"],
    "cbsnews.com": [".content__body", ".article-body"],
    "usatoday.com": [".gnt_ar_b", ".article-body"],
    "politico.com": [".article__content"],
    "thehill.com": [".field--name-body"],
    "bloomberg.com": [".body-content"],
    "ft.com": [".article-body"],
    "economist.com": [".article__body"],
    "axios.com": [".gtm-story-content"],
    "techcrunch.com": [".article-content"],
    "wired.com": [".body__inner-container"],
    "arstechnica.com": [".article-content"],
    "theverge.com": [".duet--article--article-body-component"],
    "buzzfeednews.com": [".xs-text-4"],
    "vice.com": [".article__body"],
    "time.com": ["#article-body"],
    # East Africa
    "monitor.co.ug": [".article-body", ".body-text"],
    "dailymonitor.co.ug": [".entry-content", ".article-content"],
    "newvision.co.ug": [".article-body", ".field-name-body"],
    "independent.co.ug": [".entry-content", ".tdb-block-inner"],
    "observer.ug": [".entry-content", ".post-content"],
    "nilepost.co.ug": [".entry-content", ".post-content"],
    "ugandaradionetwork.net": [".entry-content", ".post-content"],
    "dokolopost.com": [".entry-content", ".post-content"],
    "theeastafrican.co.ke": [".article-body", ".paragraph-wrapper"],
    "nation.africa": [".article-body", ".paragraph-wrapper"],
    "standardmedia.co.ke": [".article-body", ".entry-content"],
    "citizen.digital": [".article-body", ".entry-content"],
    "thecitizen.co.tz": [".article-body", ".field-name-body"],
    "dailynews.co.tz": [".entry-content"],
    # Nigeria
    "punchng.com": [".entry-content", ".post-content"],
    "premiumtimesng.com": [".entry-content", ".post-content"],
    # South Africa
    "news24.com": [".article__body", ".article_body"],
    "timeslive.co.za": [".article-widgets", ".text"],
    # India / Asia-Pacific
    "thehindu.com": [".article", '#content-body-14269002-0'],
    "scmp.com": [".article-body", ".body-output"],
    "smh.com.au": ['[data-testid="article-body"]', ".__body"],
}

# Generic content selectors tried after site-specific ones fail
GENERIC_SELECTORS = [
    "article .entry-content",
    "article .post-content",
    "article .article-content",
    "article .article-body",
    "article .story-body",
    "article .td-post-content",
    ".entry-content",
    ".post-content",
    ".article-content",
    ".article-body",
    ".story-body",
    ".td-post-content",
    ".post-body",
    ".field-name-body",
    ".node-content",
    ".body-text",
    '[itemprop="articleBody"]',
    '[role="main"] article',
    "article",
    "main .content",
    "main",
]

# ── Paywall detection ─────────────────────────────────────────────

PAYWALL_PATTERNS = [
    re.compile(r"subscribe\s+to\s+(?:continue|read|access|unlock)", re.I),
    re.compile(r"sign\s+in\s+to\s+(?:read|continue|access)", re.I),
    re.compile(r"create\s+(?:a\s+)?(?:free\s+)?account\s+to", re.I),
    re.compile(r"premium\s+(?:content|article|subscriber)", re.I),
    re.compile(
        r"(?:this|full)\s+(?:article|story|content)\s+is\s+(?:for|available\s+to)"
        r"\s+(?:subscribers?|members?|premium)", re.I,
    ),
    re.compile(r"already\s+a\s+subscriber\?\s*(?:sign|log)\s*in", re.I),
    re.compile(
        r"you(?:'ve|'ve| have)\s+reached\s+(?:your|the)\s+(?:free|monthly)"
        r"\s+(?:article|story)\s+limit", re.I,
    ),
]

PAYWALL_CLASSES = re.compile(
    r"paywall|premium[-_]?gate|subscribe[-_]?wall|piano[-_]?gate|"
    r"metered[-_]?content|registration[-_]?wall|access[-_]?wall|"
    r"locked[-_]?content|premium[-_]?overlay|subscriber[-_]?only",
    re.I,
)

TRUNCATION_PATTERNS = [
    re.compile(r"(?:read|continue|see)\s+(?:more|full\s+(?:article|story))\s*\.{0,3}\s*$", re.I),
    re.compile(r"\.\.\.\s*$"),
    re.compile(r"\u2026\s*$"),  # ellipsis character
]


# ── Helpers ───────────────────────────────────────────────────────

def _get_domain(url: str) -> str:
    """Extract bare domain (without www.) from a URL."""
    host = urlparse(url).netloc or ""
    return re.sub(r"^www\.", "", host).lower()


def _text_length(html: str) -> int:
    """Get plain text length from an HTML string."""
    return len(BeautifulSoup(html, "lxml").get_text(strip=True))


def _detect_paywall(html: str) -> bool:
    """Check if page HTML contains paywall indicators."""
    soup = BeautifulSoup(html[:50_000], "lxml")

    # Check CSS classes and IDs
    for el in soup.find_all(True, limit=500):
        classes = " ".join(el.get("class", []))
        el_id = el.get("id", "")
        if PAYWALL_CLASSES.search(classes) or PAYWALL_CLASSES.search(el_id):
            return True

    # Check text patterns
    text_sample = soup.get_text(" ", strip=True)[:5000]
    for pattern in PAYWALL_PATTERNS:
        if pattern.search(text_sample):
            return True

    return False


def _detect_truncation(text: str) -> bool:
    """Check if extracted text appears truncated."""
    if not text:
        return False
    tail = text[-500:] if len(text) > 500 else text
    for pattern in TRUNCATION_PATTERNS:
        if pattern.search(tail):
            return True
    if len(text) < 500:
        return True
    return False


# ── Individual extraction strategies ─────────────────────────────

def _strategy_trafilatura(html: str, url: str) -> dict:
    """Strategy 1: trafilatura — best for article body. Recall mode first, then default."""
    result: dict = {}
    try:
        import trafilatura

        body_html = trafilatura.extract(
            html, url=url, include_comments=False,
            include_tables=True, include_images=True,
            output_format="html", favor_recall=True,
        )
        body_text = trafilatura.extract(
            html, url=url, include_comments=False,
            output_format="txt", favor_recall=True,
        )

        # If recall mode returned too little, try default mode
        if not body_text or len(body_text) < 300:
            alt_html = trafilatura.extract(
                html, url=url, include_comments=False,
                include_tables=True, include_images=True,
                output_format="html", favor_precision=False,
            )
            alt_text = trafilatura.extract(
                html, url=url, include_comments=False,
                output_format="txt", favor_precision=False,
            )
            if alt_text and len(alt_text) > len(body_text or ""):
                body_html = alt_html
                body_text = alt_text

        metadata = trafilatura.extract_metadata(html, default_url=url)

        if body_html:
            # trafilatura emits images as <graphic src=...> in HTML output;
            # normalize to <img> so downstream cleaning and rendering treat them
            # as images (otherwise every inline image is silently dropped).
            if "<graphic" in body_html:
                from bs4 import BeautifulSoup
                gsoup = BeautifulSoup(body_html, "lxml")
                for g in gsoup.find_all("graphic"):
                    g.name = "img"
                body_html = str(gsoup)
            result["content"] = body_html
        if body_text:
            result["text"] = body_text
        if metadata:
            try:
                if metadata.title:
                    result["title"] = metadata.title
                if metadata.date:
                    result["published_date"] = metadata.date
                if metadata.image:
                    result["hero_image"] = metadata.image
            except (AttributeError, TypeError):
                pass
    except ImportError:
        logger.debug("trafilatura not installed, skipping strategy")
    except Exception as e:
        logger.warning("trafilatura failed for %s: %s", url, e)
    return result


def _strategy_newspaper(html: str, url: str) -> dict:
    """Strategy 2: newspaper3k — metadata-focused (title, authors, date, images, language).
    Never use article.html as content — it is the full page, not the article body.
    """
    result: dict = {}
    try:
        from newspaper import Article as NewspaperArticle

        article = NewspaperArticle(url)
        article.download(input_html=html)
        article.parse()

        if article.title:
            result["title"] = article.title
        if article.authors:
            result["authors"] = article.authors
        if article.publish_date:
            try:
                result["published_date"] = article.publish_date.isoformat()
            except (AttributeError, TypeError):
                pass
        if article.top_image:
            result["hero_image"] = article.top_image
        if article.images:
            result["images"] = list(article.images)
        if article.meta_lang:
            result["language"] = article.meta_lang
        # article.text is plain text — only use as last-resort fallback text
        if article.text and len(article.text) >= 300:
            result["text"] = article.text
    except ImportError:
        logger.debug("newspaper3k not installed, skipping strategy")
    except Exception as e:
        logger.warning("newspaper3k failed for %s: %s", url, e)
    return result


def _strategy_readability(html: str, url: str, source_selectors: str | None = None) -> dict:
    """Strategy 3: readability-lxml + CSS selectors with multi-layer fallback.

    Priority:
    1. source_selectors (from crawl_source DB config, per-source)
    2. SITE_SELECTORS (built-in knowledge for 44+ domains)
    3. GENERIC_SELECTORS (common content container patterns)
    4. readability-lxml Document.summary() (algorithmic fallback)
    """
    result: dict = {}
    try:
        from readability import Document

        soup = BeautifulSoup(html, "lxml")
        domain = _get_domain(url)

        # Build ordered list of selectors to try
        selectors_to_try: list[str] = []

        # 1. Source-specific selectors from DB config
        if source_selectors:
            selectors_to_try.extend(
                s.strip() for s in source_selectors.split(",") if s.strip()
            )

        # 2. Built-in site-specific selectors
        if domain in SITE_SELECTORS:
            selectors_to_try.extend(SITE_SELECTORS[domain])

        # 3. Generic content selectors
        selectors_to_try.extend(GENERIC_SELECTORS)

        # Try each selector
        for selector in selectors_to_try:
            try:
                el = soup.select_one(selector)
            except Exception:
                continue
            if el and len(el.get_text(strip=True)) >= MIN_CONTENT_LENGTH:
                result["content"] = str(el)
                result["text"] = el.get_text(separator="\n", strip=True)
                result["extraction_method"] = f"selector:{selector}"
                return result

        # 4. Fallback to readability-lxml algorithmic extraction
        doc = Document(html, url=url)
        content_html = doc.summary()
        title = doc.short_title()

        if content_html:
            text = BeautifulSoup(content_html, "lxml").get_text(strip=True)
            if len(text) >= MIN_CONTENT_LENGTH:
                result["content"] = content_html
                result["text"] = text
                result["extraction_method"] = "readability-lxml"
        if title:
            result["title"] = title
    except ImportError:
        logger.debug("readability-lxml not installed, skipping strategy")
    except Exception as e:
        logger.warning("readability strategy failed for %s: %s", url, e)
    return result


def _strategy_text_density(html: str, url: str) -> dict:
    """Strategy 4: Text-density algorithm (Readability-style).

    Scores every block element by:
      text_length + (p_tags * 50) + (img_tags * 20) - (link_length * 2)

    Penalizes nav/sidebar/footer/comment containers (0.2x).
    Boosts article/content/entry/post containers (1.5x).
    """
    result: dict = {}
    try:
        soup = BeautifulSoup(html, "lxml")
        candidates: list[tuple] = []

        for el in soup.find_all(["div", "article", "section", "main", "td"]):
            text = el.get_text(strip=True)
            text_len = len(text)
            if text_len < 200:
                continue

            p_tags = len(el.find_all("p"))
            img_tags = len(el.find_all("img"))

            link_len = sum(len(a.get_text(strip=True)) for a in el.find_all("a"))
            if text_len > 0 and (link_len / text_len) > 0.5:
                continue

            score = float(text_len + (p_tags * 50) + (img_tags * 20) - (link_len * 2))

            cls = " ".join(el.get("class", [])).lower() + " " + (el.get("id", "") or "").lower()
            for bad in ("nav", "sidebar", "footer", "header", "menu", "comment", "widget"):
                if bad in cls:
                    score *= 0.2
                    break
            for good in ("article", "content", "entry", "post", "story", "body", "text"):
                if good in cls:
                    score *= 1.5
                    break

            candidates.append((el, score))

        if not candidates:
            return result

        candidates.sort(key=lambda x: x[1], reverse=True)
        best_el = candidates[0][0]

        content_html = "".join(str(child) for child in best_el.children)
        content_text = best_el.get_text(separator="\n", strip=True)

        if len(content_text) >= 150:
            result["content"] = content_html
            result["text"] = content_text
            result["extraction_method"] = "text-density"
    except Exception as e:
        logger.warning("text-density failed for %s: %s", url, e)
    return result


# ── Result merging ────────────────────────────────────────────────

def _merge_results(
    traf: dict, news: dict, read: dict, density: dict, og_meta: dict,
) -> tuple[str, str, str, dict]:
    """Merge results from all strategies. Returns (content, text, strategy, merged_meta).

    Content priority: trafilatura > readability > density (newspaper excluded).
    Metadata priority: newspaper > OG > trafilatura > readability.
    """
    # Pick best content — prefer trafilatura, then longest among valid candidates
    content = ""
    strategy = "none"

    ordered = [("trafilatura", traf), ("readability", read), ("text-density", density)]
    candidates = []
    for label, data in ordered:
        c = data.get("content", "")
        if c:
            text_len = _text_length(c)
            if text_len >= MIN_CONTENT_LENGTH:
                method = data.get("extraction_method", label)
                img_count = len(re.findall(r"<img\b", c, re.I))
                candidates.append((label, c, text_len, method, img_count))

    if candidates:
        max_text = max(c[2] for c in candidates)
        traf_candidates = [c for c in candidates if c[0] == "trafilatura"]
        # Among candidates that keep most of the text, which preserves the most
        # inline images? trafilatura gives the cleanest prose but tends to drop
        # images, so defer to an image-richer strategy when it keeps meaningfully
        # more (>1) images without losing much text.
        rich = sorted(
            (c for c in candidates if c[2] >= 0.85 * max_text),
            key=lambda x: x[4], reverse=True,
        )
        traf_imgs = traf_candidates[0][4] if traf_candidates else -1
        if rich and rich[0][4] > traf_imgs + 1:
            _, content, _, strategy, _ = rich[0]
        elif traf_candidates:
            _, content, _, strategy, _ = traf_candidates[0]
        else:
            candidates.sort(key=lambda x: x[2], reverse=True)
            _, content, _, strategy, _ = candidates[0]

    text = traf.get("text") or read.get("text") or density.get("text") or news.get("text") or ""

    # Merge metadata
    merged_meta = {}
    merged_meta["title"] = (
        news.get("title") or traf.get("title") or og_meta.get("best_title", "") or read.get("title") or ""
    )
    merged_meta["authors"] = news.get("authors", [])
    merged_meta["published_date"] = (
        news.get("published_date") or og_meta.get("best_date", "") or traf.get("published_date") or ""
    )
    merged_meta["hero_image"] = (
        news.get("hero_image") or og_meta.get("best_image", "") or traf.get("hero_image") or ""
    )
    merged_meta["images"] = list(dict.fromkeys(news.get("images", [])))
    merged_meta["language"] = news.get("language") or ""

    return content, text, strategy, merged_meta


# ── Main extractor class ─────────────────────────────────────────

class BS4Extractor(BaseExtractor):
    """Multi-strategy BeautifulSoup content extractor.

    Runs all five strategies concurrently, picks the best content by length,
    merges metadata from all strategies, and detects paywall/truncation.
    """

    def __init__(self, max_workers: int = 4):
        self._max_workers = max_workers

    def extract(self, html: str, url: str, **kwargs) -> ExtractionResult:
        """Extract article content using all five strategies.

        Args:
            html: Raw page HTML.
            url: Article URL.
            source_selectors: CSS selectors for content container (from crawl_source config).
            strip_selectors: CSS selectors of elements to strip (from crawl_source config).

        Returns:
            ExtractionResult with the best merged content.
        """
        if not html or len(html) < 200:
            return ExtractionResult(strategy="empty")

        source_selectors = kwargs.get("source_selectors")
        strip_selectors = kwargs.get("strip_selectors")

        # Detect paywall before extraction
        is_paywall = _detect_paywall(html)

        # Run all strategies concurrently
        strategy_results: dict[str, dict] = {}
        with ThreadPoolExecutor(max_workers=self._max_workers) as executor:
            futures = {
                executor.submit(_strategy_trafilatura, html, url): "traf",
                executor.submit(_strategy_newspaper, html, url): "news",
                executor.submit(_strategy_readability, html, url, source_selectors): "read",
                executor.submit(_strategy_text_density, html, url): "density",
            }
            for future in as_completed(futures):
                key = futures[future]
                try:
                    strategy_results[key] = future.result(timeout=15)
                except Exception as e:
                    logger.warning("Strategy '%s' failed for %s: %s", key, url, e)
                    strategy_results[key] = {}

        # Extract OG/meta metadata (lightweight, no need for thread)
        og_meta = extract_metadata(html, url)

        # Merge results
        content, text, strategy, merged_meta = _merge_results(
            strategy_results.get("traf", {}),
            strategy_results.get("news", {}),
            strategy_results.get("read", {}),
            strategy_results.get("density", {}),
            og_meta,
        )

        # Clean content if we have strip_selectors
        if content and strip_selectors:
            try:
                from apps.crawler.cleaners.content_cleaner import clean_content
                content = clean_content(content, base_url=url, strip_selectors=strip_selectors)
            except ImportError:
                logger.debug("content_cleaner not available for post-extraction cleaning")

        # Detect truncation
        is_truncated = _detect_truncation(text)

        # Determine author from merged metadata
        authors = merged_meta.get("authors", [])
        author = (
            merged_meta.get("best_author", "")
            or og_meta.get("best_author", "")
            or (authors[0] if authors else "")
        )

        word_count = len(text.split()) if text else 0

        return ExtractionResult(
            content=content,
            title=merged_meta.get("title", ""),
            author=author,
            published_date=merged_meta.get("published_date", ""),
            image_url=merged_meta.get("hero_image", ""),
            language=merged_meta.get("language", "") or "en",
            word_count=word_count,
            strategy=strategy,
            is_paywall=is_paywall,
            is_truncated=is_truncated,
            metadata={
                "images": merged_meta.get("images", []),
                "authors": authors,
                "og": {k: v for k, v in og_meta.items() if k.startswith("og_")},
                "text_length": len(text),
                "source_domain": urlparse(url).netloc,
            },
        )
