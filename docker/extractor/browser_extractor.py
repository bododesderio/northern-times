"""
browser_extractor.py — Playwright-based article extraction for Cloudflare/JS-protected sites.

Uses headless Chromium to render the full page, then passes the resulting HTML
through the same extraction pipeline as the standard extractor.
"""

import logging
import os
from typing import Optional

logger = logging.getLogger(__name__)

_BROWSER_TIMEOUT = int(os.getenv("BROWSER_TIMEOUT_MS", "20000"))  # 20s default
_BOT_NAME = os.getenv("APP_BOT_NAME", "NewsCrawlerBot")


def extract_article_browser(
    url: str,
    source_selectors: Optional[list] = None,
    strip_selectors: Optional[list] = None,
) -> Optional[dict]:
    """
    Fetch article page using headless Chromium, then extract content.
    Falls back to standard extraction pipeline once HTML is obtained.
    Returns same dict shape as extract_article().
    """
    from extractor import (
        _is_safe_url, detect_paywall, detect_truncation, merge_results,
        extract_with_trafilatura, extract_with_newspaper,
        extract_with_readability, extract_with_text_density, extract_og_metadata,
    )
    from concurrent.futures import ThreadPoolExecutor, as_completed

    if not _is_safe_url(url):
        logger.warning("Browser extractor blocked unsafe URL: %s", url)
        return None

    html = _fetch_with_browser(url)
    if not html or len(html) < 500:
        logger.warning("Browser fetch returned empty/short content for %s", url)
        return None

    logger.info("Browser fetch succeeded for %s (%d bytes)", url, len(html))

    # Run the same extraction strategies on browser-fetched HTML
    paywall_detected = detect_paywall(html)
    with ThreadPoolExecutor(max_workers=4) as executor:
        futures = {
            executor.submit(extract_with_trafilatura, html, url): "traf",
            executor.submit(extract_with_newspaper, html, url): "news",
            executor.submit(extract_with_readability, html, url, source_selectors): "read",
            executor.submit(extract_with_text_density, html, url): "density",
            executor.submit(extract_og_metadata, html): "og",
        }
        results = {}
        for future in as_completed(futures):
            key = futures[future]
            try:
                results[key] = future.result(timeout=10)
            except Exception as e:
                logger.warning("Browser extraction strategy '%s' failed for %s: %s", key, url, e)
                results[key] = {}

    merged = merge_results(
        results.get("traf", {}), results.get("news", {}),
        results.get("read", {}), results.get("density", {}),
        results.get("og", {}), url, strip_selectors,
    )
    merged["paywall_detected"] = paywall_detected
    merged["truncated"] = detect_truncation(merged.get("text", ""))
    merged["extraction_method"] = "browser+" + merged.get("extraction_method", "unknown")

    if not merged.get("content") or merged.get("text_length", 0) < 200:
        logger.warning("Browser extraction too short for %s", url)
        return None

    return merged


def _fetch_with_browser(url: str) -> Optional[str]:
    """Launch headless Chromium, navigate to URL, wait for network idle, return HTML."""
    try:
        from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeout

        with sync_playwright() as p:
            browser = p.chromium.launch(
                headless=True,
                args=[
                    "--no-sandbox",
                    "--disable-setuid-sandbox",
                    "--disable-dev-shm-usage",
                    "--disable-gpu",
                    "--single-process",
                    "--no-zygote",
                ],
            )
            context = browser.new_context(
                user_agent=(
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                    "AppleWebKit/537.36 (KHTML, like Gecko) "
                    "Chrome/124.0.0.0 Safari/537.36"
                ),
                extra_http_headers={
                    "Accept-Language": "en-US,en;q=0.9",
                    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                    "X-Crawler-Identity": f"{_BOT_NAME}/1.0",
                },
                ignore_https_errors=True,
            )
            page = context.new_page()

            try:
                page.goto(url, wait_until="networkidle", timeout=_BROWSER_TIMEOUT)
            except PlaywrightTimeout:
                # On timeout, still try to get whatever was rendered
                logger.warning("Browser networkidle timeout for %s — using partial content", url)

            # Wait a moment for any JS-rendered content to settle
            try:
                page.wait_for_timeout(1500)
            except Exception:
                pass

            html = page.content()
            browser.close()
            return html

    except ImportError:
        logger.error("Playwright not installed — browser extraction unavailable")
        return None
    except Exception as e:
        logger.error("Browser fetch failed for %s: %s", url, e)
        return None
