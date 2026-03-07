"""
Multi-strategy article extraction pipeline.

Three engines run on each URL, best results merged:
1. trafilatura (primary) - best for news article body HTML + plain text
2. newspaper3k (metadata) - best for authors, dates, images, language
3. readability-lxml + BeautifulSoup (fallback) - custom CSS selectors, manual cleaning

Speed: persistent HTTP client, concurrent extraction via ThreadPoolExecutor.
"""

import logging
import random
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urljoin, urlparse

import httpx
import trafilatura
from bs4 import BeautifulSoup
from newspaper import Article as NewspaperArticle
from readability import Document

logger = logging.getLogger("extractor")

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
]

FETCH_TIMEOUT = 12
MIN_CONTENT_LENGTH = 200

STRIP_TAGS = {"script", "style", "noscript", "svg", "form", "input", "button",
              "select", "textarea", "nav", "header", "footer", "aside"}
STRIP_CLASSES = re.compile(
    r"sidebar|widget|ads?[-_]|social[-_]?share|sharing|related[-_]?posts?|"
    r"comments?|newsletter|breadcrumb|author[-_]?box|popup|modal|cookie[-_]?banner|"
    r"share[-_]?button|promo|advertisement",
    re.IGNORECASE,
)

# Persistent HTTP client for connection reuse
_http_client = httpx.Client(
    follow_redirects=True,
    timeout=FETCH_TIMEOUT,
    verify=False,
    limits=httpx.Limits(max_connections=20, max_keepalive_connections=10),
)

# Thread pool for running extractors concurrently
_executor = ThreadPoolExecutor(max_workers=3)


def fetch_page(url: str) -> str | None:
    """Fetch page HTML with browser-like headers using persistent client."""
    headers = {
        "User-Agent": random.choice(USER_AGENTS),
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9",
        "Accept-Encoding": "gzip, deflate",
        "DNT": "1",
    }
    try:
        resp = _http_client.get(url, headers=headers)
        if resp.status_code >= 400:
            return None
        return resp.text
    except Exception:
        return None


def extract_with_trafilatura(html: str, url: str) -> dict:
    """Extract using trafilatura - best for article body."""
    result = {}
    try:
        body_html = trafilatura.extract(
            html, url=url, include_comments=False,
            include_tables=True, include_images=True,
            output_format="html", favor_precision=False,
        )
        body_text = trafilatura.extract(
            html, url=url, include_comments=False,
            output_format="txt",
        )
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
    except Exception:
        pass
    return result


def extract_with_newspaper(html: str, url: str) -> dict:
    """Extract using newspaper3k - best for metadata."""
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
            result["published_date"] = article.publish_date.isoformat()
        if article.top_image:
            result["hero_image"] = article.top_image
        if article.images:
            result["images"] = list(article.images)
        if article.text:
            result["text"] = article.text
        if article.meta_lang:
            result["language"] = article.meta_lang
        if article.html:
            result["content"] = article.html
    except Exception:
        pass
    return result


def extract_with_readability(html: str, url: str, source_selectors: str | None = None) -> dict:
    """Extract using readability-lxml + BeautifulSoup - fallback with custom selectors."""
    result = {}
    try:
        if source_selectors:
            soup = BeautifulSoup(html, "lxml")
            for selector in source_selectors.split(","):
                selector = selector.strip()
                if not selector:
                    continue
                el = soup.select_one(selector)
                if el and len(el.get_text(strip=True)) >= MIN_CONTENT_LENGTH:
                    result["content"] = str(el)
                    result["text"] = el.get_text(separator="\n", strip=True)
                    return result

        doc = Document(html, url=url)
        content_html = doc.summary()
        title = doc.short_title()

        if content_html:
            text = BeautifulSoup(content_html, "lxml").get_text(strip=True)
            if len(text) >= MIN_CONTENT_LENGTH:
                result["content"] = content_html
                result["text"] = text
        if title:
            result["title"] = title
    except Exception:
        pass
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
    except Exception:
        pass
    return result


def clean_html(html: str, base_url: str) -> str:
    """Clean final HTML: strip junk, fix images, resolve URLs, remove empties."""
    soup = BeautifulSoup(html, "lxml")

    for tag_name in STRIP_TAGS:
        for el in soup.find_all(tag_name):
            el.decompose()

    to_remove = []
    for el in soup.find_all(True):
        if el.attrs is None:
            continue
        classes = " ".join(el.get("class", []))
        el_id = el.get("id", "")
        if STRIP_CLASSES.search(classes) or STRIP_CLASSES.search(el_id):
            to_remove.append(el)
    for el in to_remove:
        el.decompose()

    for img in soup.find_all("img"):
        for attr in ("data-src", "data-lazy-src", "data-original", "data-full-src"):
            if img.get(attr):
                img["src"] = img[attr]
                del img[attr]
                break

    for tag in soup.find_all(["a", "img"]):
        for attr in ("href", "src"):
            val = tag.get(attr)
            if val and not val.startswith(("http://", "https://", "data:", "mailto:")):
                tag[attr] = urljoin(base_url, val)

    for tag in soup.find_all(["p", "div", "span"]):
        if not tag.get_text(strip=True) and not tag.find(["img", "iframe", "video"]):
            tag.decompose()

    body = soup.find("body")
    if body:
        return "".join(str(child) for child in body.children)
    return str(soup)


def merge_results(
    traf: dict, news: dict, read: dict, og: dict, base_url: str
) -> dict:
    """Merge results from all extractors, picking best for each field."""

    title = news.get("title") or traf.get("title") or og.get("title") or read.get("title")
    authors = news.get("authors", [])
    published_date = news.get("published_date") or og.get("published_date") or traf.get("published_date")

    traf_content = traf.get("content", "")
    read_content = read.get("content", "")
    traf_len = len(BeautifulSoup(traf_content, "lxml").get_text(strip=True)) if traf_content else 0
    read_len = len(BeautifulSoup(read_content, "lxml").get_text(strip=True)) if read_content else 0

    if traf_len >= MIN_CONTENT_LENGTH and traf_len >= read_len:
        content = traf_content
    elif read_len >= MIN_CONTENT_LENGTH:
        content = read_content
    elif traf_content:
        content = traf_content
    else:
        content = read_content or news.get("content", "")

    if content:
        content = clean_html(content, base_url)

    text = traf.get("text") or read.get("text") or news.get("text") or ""
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
    }


def extract_article(url: str, source_selectors: str | None = None) -> dict | None:
    """Run the full extraction pipeline on a URL with concurrent extractors."""
    html = fetch_page(url)
    if not html or len(html) < 500:
        logger.warning("fetch_page failed or too short for %s (got %d bytes)", url, len(html) if html else 0)
        return None

    # Run all extractors concurrently via thread pool
    futures = {
        _executor.submit(extract_with_trafilatura, html, url): "traf",
        _executor.submit(extract_with_newspaper, html, url): "news",
        _executor.submit(extract_with_readability, html, url, source_selectors): "read",
        _executor.submit(extract_og_metadata, html): "og",
    }

    results = {}
    for future in as_completed(futures):
        key = futures[future]
        try:
            results[key] = future.result(timeout=10)
        except Exception:
            results[key] = {}

    merged = merge_results(
        results.get("traf", {}),
        results.get("news", {}),
        results.get("read", {}),
        results.get("og", {}),
        url,
    )

    if not merged.get("content") or merged["text_length"] < 100:
        logger.warning("Extraction too short for %s: text_length=%d", url, merged.get("text_length", 0))
        return None

    logger.info("Extracted '%s' (%d chars) from %s", (merged.get("title") or "?")[:60], merged["text_length"], url)
    return merged
