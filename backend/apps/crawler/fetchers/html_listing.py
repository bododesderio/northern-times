"""HTML listing fetcher — article discovery for sites with no usable RSS.

Some outlets (Nile Post, Daily Monitor, New Vision) are JS SPAs or Cloudflare-
walled and expose no parseable feed. This fetcher renders their index page with
the stealth browser and harvests article links straight from the DOM, returning
the same ``FeedItem`` shape as ``RSSFetcher`` so the rest of the pipeline is
unchanged. Article *content* is then fetched per-URL by the pipeline's normal
browser-enabled page fetch.

Article links are separated from navigation/chrome with a heuristic: same host,
a slug of 3+ hyphen-joined tokens (or a ``/news/<id>/`` style path), and none of
the obvious non-article path fragments. A source may narrow this with
``content_selector`` holding a comma-separated list of required path fragments
(e.g. ``/news/,/uganda/news/``).
"""
import logging
import re
from urllib.parse import urlparse

from apps.crawler.fetchers.rss import FeedItem

logger = logging.getLogger(__name__)

_NON_ARTICLE = (
    '/login', '/subscribe', '/register', '/tag/', '/tags/', '/author', '/search',
    '/category/', '/page/', '/about', '/contact', '/privacy', '/terms', '/rss',
    '/feed', '/advertise', '/jobs', '#', '/video', '/videos', '/live',
    'facebook.com', 'twitter.com', 'x.com', 'instagram.com', 'youtube.com',
    'whatsapp', 'linkedin.com', 't.me', 'mailto:',
)
# A real headline slug: 3+ hyphen-joined word tokens, OR a /news/<digits>/ id path.
_SLUG_RE = re.compile(r'/[a-z0-9]+(?:-[a-z0-9]+){2,}')
_ID_PATH_RE = re.compile(r'/news/\d+/')
_MIN_TITLE_WORDS = 4


class HTMLListingFetcher:
    """Discover article FeedItems from a JS/CF-walled index page via the browser."""

    def fetch(self, url: str, max_items: int = 50, required_fragments=None,
              browser=None) -> list:
        # Reuse the caller's browser session when provided (avoids a per-source
        # Chrome cold-start); only own/close one we created ourselves.
        own_browser = browser is None
        if own_browser:
            from apps.crawler.fetchers.browser import BrowserFetcher
            browser = BrowserFetcher()
        try:
            pairs = browser.fetch_links(url, wait_seconds=8)
        finally:
            if own_browser:
                browser.close()

        host = urlparse(url).netloc
        seen = set()
        items = []
        for href, text in pairs:
            if not href or not href.startswith('http'):
                continue
            if urlparse(href).netloc.replace('www.', '') != host.replace('www.', ''):
                continue
            path = href.split(host, 1)[-1]
            low = href.lower()
            if any(frag in low for frag in _NON_ARTICLE):
                continue
            if not (_SLUG_RE.search(path) or _ID_PATH_RE.search(path)):
                continue
            if required_fragments and not any(f in path for f in required_fragments):
                continue
            title = (text or '').strip()
            # The anchor text is the headline; require it to look like one.
            if len(title.split()) < _MIN_TITLE_WORDS:
                continue
            key = href.split('#')[0].rstrip('/')
            if key in seen:
                continue
            seen.add(key)
            items.append(FeedItem(title=title, url=key))
            if len(items) >= max_items:
                break

        logger.info(f"HTML listing fetched {len(items)} article links from {url}")
        return items
