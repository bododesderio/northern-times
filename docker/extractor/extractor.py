"""
Multi-strategy article extraction pipeline.

Five engines run on each URL, best results merged:
1. trafilatura (primary) - best for news article body HTML + plain text
2. newspaper3k (metadata) - best for authors, dates, images, language
3. readability-lxml + BeautifulSoup (fallback) - custom CSS selectors, manual cleaning
4. Site-specific CSS selectors (44 domains) - known content containers
5. Text-density algorithm (Readability-style) - last-resort heuristic

Plus:
- Paywall / truncation detection
- Unified content cleaning via content_cleaner module

Speed: persistent HTTP client, concurrent extraction via ThreadPoolExecutor.
"""

import logging
import os
import random
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urljoin, urlparse

import httpx
import trafilatura
from bs4 import BeautifulSoup, Tag
from newspaper import Article as NewspaperArticle
from readability import Document

from content_cleaner import clean_and_normalize

logger = logging.getLogger("extractor")

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
]

FETCH_TIMEOUT = int(os.getenv("EXTRACTOR_FETCH_TIMEOUT", "15"))
MIN_CONTENT_LENGTH = int(os.getenv("EXTRACTOR_MIN_CONTENT_LENGTH", "400"))
MIN_ARTICLE_CHARS = 400  # Minimum plain-text chars to consider a candidate valid

# ── Site-specific CSS selectors (ported from ArticleScraper.php) ────

SITE_SELECTORS: dict[str, list[str]] = {
    "aljazeera.com":          [".wysiwyg", ".article__body-content", "#main-content-area"],
    "theguardian.com":        [".article-body-commercial-selector", ".content--article-body", '[data-gu-name="body"]'],
    "bbc.com":                ['[data-component="text-block"]', ".ssrcss-11r1m41-RichTextComponentWrapper", ".story-body__inner"],
    "bbc.co.uk":              ['[data-component="text-block"]', ".ssrcss-11r1m41-RichTextComponentWrapper", ".story-body__inner"],
    "reuters.com":            [".article-body__content", ".ArticleBody__content"],
    "cnn.com":                [".article__content", ".zn-body__paragraph"],
    "nytimes.com":            [".StoryBodyCompanionColumn", ".story-body", '[name="articleBody"]'],
    "apnews.com":             [".RichTextStoryBody", ".Article"],
    "npr.org":                ["#storytext", ".storytext"],
    "france24.com":           [".t-content__body", ".article__text"],
    "dw.com":                 [".rich-text", ".longText"],
    # East Africa
    "monitor.co.ug":          [".article-body", ".body-text"],
    "observer.ug":            [".entry-content", ".post-content"],
    "independent.co.ug":      [".entry-content", ".tdb-block-inner"],
    "nilepost.co.ug":         [".entry-content", ".post-content"],
    "newvision.co.ug":        [".article-body", ".field-name-body"],
    "ugandaradionetwork.net": [".entry-content", ".post-content"],
    "dokolopost.com":         [".entry-content", ".post-content"],
    "nation.africa":          [".article-body", ".paragraph-wrapper"],
    "theeastafrican.co.ke":   [".article-body", ".paragraph-wrapper"],
    "citizen.digital":        [".article-body", ".entry-content"],
    "thecitizen.co.tz":       [".article-body", ".field-name-body"],
    # Nigeria
    "punchng.com":            [".entry-content", ".post-content"],
    "premiumtimesng.com":     [".entry-content", ".post-content"],
    # South Africa
    "news24.com":             [".article__body", ".article_body"],
    "timeslive.co.za":        [".article-widgets", ".text"],
    # International
    "africanews.com":         [".article__text", ".article-content"],
    "independent.co.uk":      ["#main-content article", ".article-body"],
    "telegraph.co.uk":        [".article-body-text", ".articleBodyText"],
    "skynews.com":            [".sdc-article-body", ".article-body"],
    "mirror.co.uk":           [".article-body", ".body-content"],
    "nbcnews.com":            [".article-body", ".article-body__content"],
    "abcnews.go.com":         [".Article__Content", ".article-copy"],
    "cbsnews.com":            [".content__body", ".article-body"],
    "usatoday.com":           [".gnt_ar_b", ".article-body"],
    "foxnews.com":            [".article-body", ".body-text"],
    "thehindu.com":           [".article", '#content-body-14269002-0'],
    "scmp.com":               [".article-body", ".body-output"],
    "smh.com.au":             ['[data-testid="article-body"]', ".article__body"],
}

# Generic content selectors tried after site-specific ones fail
GENERIC_SELECTORS = [
    "article .entry-content", "article .post-content",
    "article .article-content", "article .article-body",
    "article .story-body", "article .td-post-content",
    ".entry-content", ".post-content", ".article-content",
    ".article-body", ".story-body", ".td-post-content",
    ".post-body", ".field-name-body", ".node-content", ".body-text",
    '[itemprop="articleBody"]', '[role="main"] article',
    "article", "main .content", "main",
]

# ── Paywall / truncation detection ──────────────────────────────

PAYWALL_PATTERNS = [
    re.compile(r"subscribe\s+to\s+(?:continue|read|access|unlock)", re.I),
    re.compile(r"sign\s+in\s+to\s+(?:read|continue|access)", re.I),
    re.compile(r"create\s+(?:a\s+)?(?:free\s+)?account\s+to", re.I),
    re.compile(r"premium\s+(?:content|article|subscriber)", re.I),
    re.compile(r"(?:this|full)\s+(?:article|story|content)\s+is\s+(?:for|available\s+to)\s+(?:subscribers?|members?|premium)", re.I),
    re.compile(r"already\s+a\s+subscriber\?\s*(?:sign|log)\s*in", re.I),
    re.compile(r"you(?:'ve|'ve| have)\s+reached\s+(?:your|the)\s+(?:free|monthly)\s+(?:article|story)\s+limit", re.I),
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
    re.compile(r"…\s*$"),
]

# ── Lazy-initialized HTTP client (fork-safe for multi-worker uvicorn) ──

_http_client = None
_executor = None
_client_lock = __import__("threading").Lock()

def _get_http_client() -> httpx.Client:
    global _http_client
    if _http_client is None:
        with _client_lock:
            if _http_client is None:
                _http_client = httpx.Client(
                    follow_redirects=True,
                    timeout=FETCH_TIMEOUT,
                    verify=True,
                    limits=httpx.Limits(max_connections=20, max_keepalive_connections=10),
                )
    return _http_client

def _get_executor() -> ThreadPoolExecutor:
    global _executor
    if _executor is None:
        with _client_lock:
            if _executor is None:
                _executor = ThreadPoolExecutor(max_workers=int(os.getenv("EXTRACTOR_STRATEGY_WORKERS", "4")))
    return _executor


# ── SSRF Protection ────────────────────────────────────────────

import ipaddress
import socket

def _is_safe_url(url: str) -> bool:
    """Validate URL is safe to fetch (no internal/private IPs, only http/https)."""
    try:
        parsed = urlparse(url)
        if parsed.scheme not in ("http", "https"):
            return False
        hostname = parsed.hostname
        if not hostname:
            return False
        # Resolve hostname to IP and check against private ranges
        try:
            addr_info = socket.getaddrinfo(hostname, None, socket.AF_UNSPEC, socket.SOCK_STREAM)
            for family, _, _, _, sockaddr in addr_info:
                ip = ipaddress.ip_address(sockaddr[0])
                if ip.is_private or ip.is_loopback or ip.is_link_local or ip.is_reserved:
                    logger.warning("SSRF blocked: %s resolves to private IP %s", url, ip)
                    return False
        except (socket.gaierror, ValueError):
            return False
        return True
    except Exception:
        return False


# ── robots.txt compliance ───────────────────────────────────────

import urllib.robotparser
import functools

@functools.lru_cache(maxsize=256)
def _fetch_robots(robots_url: str) -> urllib.robotparser.RobotFileParser:
    """Fetch and parse robots.txt for a host. Cached per process (LRU 256 hosts)."""
    import urllib.request
    rp = urllib.robotparser.RobotFileParser()
    rp.set_url(robots_url)
    try:
        # Use urllib directly with a timeout to prevent hanging
        req = urllib.request.Request(robots_url, headers={"User-Agent": "NorthernTimesBot/1.0"})
        with urllib.request.urlopen(req, timeout=5) as resp:
            raw = resp.read(100_000).decode("utf-8", errors="replace")
        rp.parse(raw.splitlines())
    except Exception:
        pass  # fail open — return empty parser (allows everything)
    return rp

def _is_robots_allowed(url: str) -> bool:
    """Return True if NorthernTimesBot is allowed to crawl this URL.

    can_fetch already falls back to wildcard rules if no agent-specific rule exists.
    """
    try:
        parsed = urlparse(url)
        robots_url = f"{parsed.scheme}://{parsed.netloc}/robots.txt"
        rp = _fetch_robots(robots_url)
        return rp.can_fetch("NorthernTimesBot", url)
    except Exception:
        return True  # fail open


# ── Utilities ───────────────────────────────────────────────────

def _get_domain(url: str) -> str:
    """Extract bare domain (without www.) from a URL."""
    host = urlparse(url).netloc or ""
    return re.sub(r"^www\.", "", host).lower()


def _text_length(html: str) -> int:
    """Get plain text length from HTML string."""
    return len(BeautifulSoup(html, "lxml").get_text(strip=True))


def detect_paywall(html: str) -> bool:
    """Check if page HTML contains paywall indicators."""
    # Check for paywall CSS classes in the DOM
    soup = BeautifulSoup(html[:50000], "lxml")
    for el in soup.find_all(True, limit=500):
        classes = " ".join(el.get("class", []))
        el_id = el.get("id", "")
        if PAYWALL_CLASSES.search(classes) or PAYWALL_CLASSES.search(el_id):
            return True

    # Check for paywall text patterns
    text_sample = soup.get_text(" ", strip=True)[:5000]
    for pattern in PAYWALL_PATTERNS:
        if pattern.search(text_sample):
            return True

    return False


def detect_truncation(text: str) -> bool:
    """Check if extracted text appears truncated (paywall or incomplete extraction)."""
    if not text:
        return False
    # Check truncation markers anywhere in the text (not just short content)
    # Common truncation signals appear at the end of paywalled articles
    tail = text[-500:] if len(text) > 500 else text
    for pattern in TRUNCATION_PATTERNS:
        if pattern.search(tail):
            return True
    # Very short content is suspicious regardless of markers
    if len(text) < 500:
        return True
    return False


# ── Page fetcher ────────────────────────────────────────────────

def fetch_page(url: str, retries: int = 2) -> str | None:
    """Fetch page HTML with browser-like headers, retrying on transient failures."""
    parsed = urlparse(url)
    origin = f"{parsed.scheme}://{parsed.netloc}"
    headers = {
        "User-Agent": random.choice(USER_AGENTS),
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9",
        "Accept-Encoding": "gzip, deflate, br",
        "DNT": "1",
        "Referer": origin + "/",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "same-origin",
        "Upgrade-Insecure-Requests": "1",
        "X-Crawler-Identity": "NorthernTimesBot/1.0",
    }
    import time
    for attempt in range(1, retries + 1):
        try:
            resp = _get_http_client().get(url, headers=headers)
            if resp.status_code >= 500 and attempt < retries:
                logger.warning("fetch_page HTTP %d for %s (attempt %d/%d, retrying)", resp.status_code, url, attempt, retries)
                time.sleep(1 * attempt)
                continue
            if resp.status_code >= 400:
                logger.warning("fetch_page HTTP %d for %s", resp.status_code, url)
                return None
            return resp.text
        except (httpx.ConnectTimeout, httpx.ReadTimeout, httpx.ConnectError) as e:
            if attempt < retries:
                logger.warning("fetch_page transient error for %s (attempt %d/%d): %s", url, attempt, retries, e)
                time.sleep(1 * attempt)
                continue
            logger.warning("fetch_page failed after %d attempts for %s: %s", retries, url, e)
            return None
        except Exception as e:
            logger.warning("fetch_page failed for %s: %s", url, e)
            return None
    return None


# ── Extraction strategies ───────────────────────────────────────

def extract_with_trafilatura(html: str, url: str) -> dict:
    """Extract using trafilatura - best for article body. Tries recall mode first, then default."""
    result = {}
    try:
        body_html = trafilatura.extract(
            html, url=url, include_comments=False,
            include_tables=True, include_images=True,
            output_format="html", favor_recall=True,
        )
        body_text = trafilatura.extract(
            html, url=url, include_comments=False,
            output_format="txt", favor_recall=True,
        )

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
    except Exception as e:
        logger.warning("extract_with_trafilatura failed for %s: %s", url, e)
    return result


def extract_with_newspaper(html: str, url: str) -> dict:
    """Extract using newspaper3k - metadata ONLY (title, authors, date, images, language).
    Never use article.html as content — it is the full page, not the article body.
    """
    result = {}
    try:
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
        # NOTE: article.text is plain text — only use as last-resort fallback text,
        # never as HTML content. article.html is the full page — never use it.
        if article.text and len(article.text) >= 300:
            result["text"] = article.text
    except Exception as e:
        logger.warning("extract_with_newspaper failed for %s: %s", url, e)
    return result


def extract_with_readability(html: str, url: str, source_selectors: str | None = None) -> dict:
    """
    Extract using readability-lxml + BeautifulSoup with multi-layer selector fallback.

    Priority:
    1. source_selectors (from crawl_source DB config, per-source)
    2. SITE_SELECTORS (built-in knowledge for 44 domains)
    3. GENERIC_SELECTORS (common content container patterns)
    4. readability-lxml Document.summary() (algorithmic fallback)
    """
    result = {}
    try:
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
            except Exception as e:
                logger.warning("CSS selector '%s' failed: %s", selector, e)
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
    except Exception as e:
        logger.warning("extract_with_readability failed for %s: %s", url, e)
    return result


def extract_with_text_density(html: str, url: str) -> dict:
    """
    Text-density extraction algorithm (Readability-style).

    Scores every block element by:
      text_length + (p_tags * 50) + (img_tags * 20) - (link_length * 2)

    Penalizes nav/sidebar/footer/comment containers (0.2x).
    Boosts article/content/entry/post containers (1.5x).
    """
    result = {}
    try:
        soup = BeautifulSoup(html, "lxml")
        candidates = []

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

            score = text_len + (p_tags * 50) + (img_tags * 20) - (link_len * 2)

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
        logger.warning("extract_with_text_density failed for %s: %s", url, e)
    return result


def extract_og_metadata(html: str) -> dict:
    """Extract Open Graph and meta tag metadata."""
    result = {}
    try:
        soup = BeautifulSoup(html, "lxml")

        og_img = soup.find("meta", property="og:image")
        if og_img and og_img.get("content"):
            result["hero_image"] = og_img["content"]

        og_date = soup.find("meta", property="article:published_time")
        if og_date and og_date.get("content"):
            result["published_date"] = og_date["content"]

        title_tag = soup.find("title")
        if title_tag and title_tag.string:
            result["title"] = title_tag.string.strip()

        tw_img = soup.find("meta", attrs={"name": "twitter:image"})
        if tw_img and tw_img.get("content"):
            result.setdefault("hero_image", tw_img["content"])
    except Exception as e:
        logger.warning("extract_og_metadata failed: %s", e)
    return result


# ── Result merging ──────────────────────────────────────────────

def merge_results(
    traf: dict, news: dict, read: dict, density: dict, og: dict, base_url: str,
    strip_selectors: str | None = None,
) -> dict:
    """Merge results from all extractors, picking best for each field."""

    title = news.get("title") or traf.get("title") or og.get("title") or read.get("title")
    authors = news.get("authors", [])
    published_date = news.get("published_date") or og.get("published_date") or traf.get("published_date")

    # Pick the best content: trafilatura > readability > density (newspaper excluded from content)
    # Prefer trafilatura if it meets the minimum threshold, then fall through in order.
    content = ""
    extraction_method = "none"

    ordered = [("traf", traf), ("read", read), ("density", density)]
    candidates = []
    for label, data in ordered:
        c = data.get("content", "")
        if c:
            text_len = _text_length(c)
            if text_len >= MIN_ARTICLE_CHARS:
                method = data.get("extraction_method", label)
                candidates.append((label, c, text_len, method))

    if candidates:
        # Among valid candidates, prefer trafilatura; otherwise take longest
        traf_candidates = [c for c in candidates if c[0] == "traf"]
        if traf_candidates:
            _, content, _, extraction_method = traf_candidates[0]
        else:
            candidates.sort(key=lambda x: x[2], reverse=True)
            _, content, _, extraction_method = candidates[0]

    # Run unified content cleaning
    if content:
        content = clean_and_normalize(content, base_url, strip_selectors)

    text = traf.get("text") or read.get("text") or density.get("text") or news.get("text") or ""
    hero_image = news.get("hero_image") or og.get("hero_image") or traf.get("hero_image")
    images = list(dict.fromkeys(news.get("images", [])))
    language = news.get("language") or None
    excerpt = text[:280].strip() if text else ""
    source_domain = urlparse(base_url).netloc
    text_length = len(text) if text else 0

    return {
        "title": title,
        "authors": authors,
        "published_date": published_date,
        "content": content,
        "text": text,
        "excerpt": excerpt,
        "hero_image": hero_image,
        "images": images,
        "text_length": text_length,
        "source_domain": source_domain,
        "language": language,
        "extraction_method": extraction_method,
    }


# ── Main extraction pipeline ───────────────────────────────────

def extract_article(
    url: str,
    source_selectors: str | None = None,
    strip_selectors: str | None = None,
) -> dict | None:
    """
    Run the full extraction pipeline on a URL with concurrent extractors.

    Args:
        url: Article URL to extract
        source_selectors: CSS selectors for content container (from crawl_source config)
        strip_selectors: CSS selectors of elements to strip (from crawl_source config)

    Returns:
        Merged extraction result dict, or None if extraction failed
    """
    # SSRF protection: validate URL before fetching
    if not _is_safe_url(url):
        logger.warning("Blocked unsafe URL: %s", url)
        return None

    # robots.txt compliance check
    if not _is_robots_allowed(url):
        logger.info("robots.txt disallows extraction of %s", url)
        return None

    html = fetch_page(url)
    if not html or len(html) < 500:
        logger.warning("fetch_page failed or too short for %s (got %d bytes)", url, len(html) if html else 0)
        return None

    # Detect paywall before extraction
    paywall_detected = detect_paywall(html)
    if paywall_detected:
        logger.warning("Paywall detected for %s", url)

    # Run all extractors concurrently via thread pool
    futures = {
        _get_executor().submit(extract_with_trafilatura, html, url): "traf",
        _get_executor().submit(extract_with_newspaper, html, url): "news",
        _get_executor().submit(extract_with_readability, html, url, source_selectors): "read",
        _get_executor().submit(extract_with_text_density, html, url): "density",
        _get_executor().submit(extract_og_metadata, html): "og",
    }

    results = {}
    for future in as_completed(futures):
        key = futures[future]
        try:
            results[key] = future.result(timeout=10)
        except Exception as e:
            logger.warning("Extraction strategy '%s' failed for %s: %s", key, url, e)
            results[key] = {}

    merged = merge_results(
        results.get("traf", {}),
        results.get("news", {}),
        results.get("read", {}),
        results.get("density", {}),
        results.get("og", {}),
        url,
        strip_selectors,
    )

    # Add paywall/truncation flags
    merged["paywall_detected"] = paywall_detected
    merged["truncated"] = detect_truncation(merged.get("text", ""))

    if not merged.get("content") or merged["text_length"] < 400:
        logger.warning("Extraction too short for %s: text_length=%d", url, merged.get("text_length", 0))
        return None

    logger.info(
        "Extracted '%s' (%d chars, method=%s) from %s",
        (merged.get("title") or "?")[:60],
        merged["text_length"],
        merged.get("extraction_method", "?"),
        url,
    )
    return merged
