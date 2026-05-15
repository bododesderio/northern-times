"""Direct web page fetcher using httpx."""
import logging
import random

import httpx
from django.conf import settings

logger = logging.getLogger(__name__)

USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
]


class WebFetcher:
    """Fetches web page HTML content with browser-like headers."""

    def __init__(self):
        self.timeout = getattr(settings, 'CRAWLER_FETCH_TIMEOUT', 15)
        self.verify_ssl = getattr(settings, 'VERIFY_SSL', True)
        self._client = None

    @property
    def client(self):
        if self._client is None:
            self._client = httpx.Client(
                timeout=self.timeout,
                follow_redirects=True,
                verify=self.verify_ssl,
                limits=httpx.Limits(max_connections=20, max_keepalive_connections=10),
            )
        return self._client

    def fetch(self, url: str) -> tuple[str, int]:
        """Fetch page HTML. Returns (html_content, status_code). Returns ('', 0) on error."""
        headers = {
            'User-Agent': random.choice(USER_AGENTS),
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Accept-Encoding': 'gzip, deflate',
            'DNT': '1',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
        }

        try:
            response = self.client.get(url, headers=headers)
            response.raise_for_status()

            # Handle encoding — httpx auto-detects, but fallback to utf-8
            content_type = response.headers.get('content-type', '')
            if 'charset' not in content_type and response.encoding is None:
                response.encoding = 'utf-8'

            return response.text, response.status_code

        except httpx.HTTPError as e:
            logger.warning(f"HTTP error fetching {url}: {e}")
            return '', 0
        except Exception as e:
            logger.error(f"Error fetching {url}: {e}")
            return '', 0

    def fetch_head(self, url: str) -> dict:
        """HEAD request -- returns headers dict or empty dict on error."""
        try:
            headers = {'User-Agent': random.choice(USER_AGENTS)}
            response = self.client.head(url, headers=headers)
            return dict(response.headers)
        except Exception:
            return {}

    def close(self):
        if self._client:
            self._client.close()
            self._client = None

    def __del__(self):
        self.close()
