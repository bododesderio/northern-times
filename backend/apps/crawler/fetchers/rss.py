"""RSS/Atom feed fetcher with adaptive browser fallback."""
import logging
import random
from dataclasses import dataclass, field
from datetime import datetime

import feedparser
import httpx
from django.conf import settings

logger = logging.getLogger(__name__)

BROWSER_USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
]


@dataclass
class FeedItem:
    """A single item from an RSS/Atom feed."""
    title: str
    url: str
    published_at: datetime | None = None
    author: str = ''
    summary: str = ''
    categories: list[str] = field(default_factory=list)
    image_url: str = ''


class RSSFetcher:
    """Fetches and parses RSS/Atom feeds with adaptive browser fallback."""

    def __init__(self):
        self.timeout = getattr(settings, 'CRAWLER_FETCH_TIMEOUT', 15)
        self.bot_name = getattr(settings, 'APP_BOT_NAME', 'NewsCrawlerBot')
        self.verify_ssl = getattr(settings, 'VERIFY_SSL', True)

    def fetch(self, url: str, max_items: int = 50, use_browser: bool = False) -> list[FeedItem]:
        """Fetch and parse an RSS/Atom feed.

        If use_browser=True, skips HTTP and goes straight to Selenium.
        Otherwise tries HTTP first, falls back to browser on 403/timeout.
        """
        if use_browser:
            logger.info(f"Using browser for {url} (auto-detected)")
            return self._fetch_with_browser(url, max_items)

        # Try normal HTTP first
        try:
            items = self._fetch_http(url, max_items)
            if items:
                return items
        except _BrowserFallbackNeeded:
            logger.info(f"HTTP blocked for {url}, trying browser fallback")
            return self._fetch_with_browser(url, max_items)

        return []

    def _fetch_http(self, url: str, max_items: int) -> list[FeedItem]:
        """Fetch via HTTP with browser-like headers (SSRF-guarded, per-hop)."""
        from apps.crawler.fetchers.url_guard import safe_get

        try:
            with httpx.Client(
                timeout=self.timeout, follow_redirects=False, verify=self.verify_ssl,
            ) as client:
                response = safe_get(client, url, headers={
                    'User-Agent': random.choice(BROWSER_USER_AGENTS),
                    'Accept': 'application/rss+xml, application/xml, text/xml, */*',
                    'Accept-Language': 'en-US,en;q=0.9',
                })
            if response is None:
                logger.warning(f"Blocked or unreachable feed URL (SSRF guard): {url}")
                return []
            response.raise_for_status()
            return self._parse_feed(response.text, url, max_items)

        except httpx.HTTPStatusError as e:
            if e.response.status_code in (403, 429, 503):
                raise _BrowserFallbackNeeded(str(e))
            logger.error(f"HTTP error fetching feed {url}: {e}")
            return []
        except (httpx.TimeoutException, httpx.ConnectError) as e:
            raise _BrowserFallbackNeeded(str(e))
        except Exception as e:
            logger.error(f"Error fetching feed {url}: {e}")
            return []

    def _fetch_with_browser(self, url: str, max_items: int) -> list[FeedItem]:
        """Fetch RSS feed content using Selenium headless browser."""
        try:
            from apps.crawler.fetchers.browser import BrowserFetcher
            browser = BrowserFetcher()
            try:
                html = browser.fetch(url, wait_seconds=2)
                if not html:
                    return []
                return self._parse_feed(html, url, max_items)
            finally:
                browser.close()
        except Exception as e:
            logger.error(f"Browser fetch failed for feed {url}: {e}")
            return []

    def _parse_feed(self, content: str, url: str, max_items: int) -> list[FeedItem]:
        """Parse feed content into FeedItems."""
        feed = feedparser.parse(content)

        items = []
        for entry in feed.entries[:max_items]:
            # Parse published date
            published = None
            for date_field in ('published_parsed', 'updated_parsed', 'created_parsed'):
                parsed = getattr(entry, date_field, None)
                if parsed:
                    try:
                        published = datetime(*parsed[:6])
                    except (TypeError, ValueError):
                        pass
                    break

            # Extract image from media/enclosure
            image_url = ''
            if hasattr(entry, 'media_content') and entry.media_content:
                for media in entry.media_content:
                    if media.get('medium') == 'image' or media.get('type', '').startswith('image'):
                        image_url = media.get('url', '')
                        break
            if not image_url and hasattr(entry, 'enclosures'):
                for enc in entry.enclosures:
                    if enc.get('type', '').startswith('image'):
                        image_url = enc.get('href', '')
                        break
            if not image_url and hasattr(entry, 'media_thumbnail') and entry.media_thumbnail:
                image_url = entry.media_thumbnail[0].get('url', '')

            # Extract categories/tags
            categories = []
            if hasattr(entry, 'tags'):
                categories = [t.get('term', '') for t in entry.tags if t.get('term')]

            items.append(FeedItem(
                title=entry.get('title', '').strip(),
                url=entry.get('link', '').strip(),
                published_at=published,
                author=entry.get('author', ''),
                summary=entry.get('summary', ''),
                categories=categories,
                image_url=image_url,
            ))

        logger.info(f"Fetched {len(items)} items from {url}")
        return items


class _BrowserFallbackNeeded(Exception):
    """Signal to retry with browser."""
    pass
