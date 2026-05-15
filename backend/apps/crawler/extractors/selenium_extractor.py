"""Selenium-based extractor for JavaScript-rendered pages."""
import logging
from .base import BaseExtractor, ExtractionResult

logger = logging.getLogger(__name__)


class SeleniumExtractor(BaseExtractor):
    """Extracts content from JS-heavy pages using Selenium + BS4."""

    def __init__(self):
        self._browser = None

    def extract(self, html: str, url: str, **kwargs) -> ExtractionResult:
        """Fetch page via Selenium, then extract with BS4."""
        if not self._browser:
            from apps.crawler.fetchers.browser import BrowserFetcher
            self._browser = BrowserFetcher()

        rendered_html = self._browser.fetch(url, wait_seconds=kwargs.get('wait', 3))
        if not rendered_html:
            return ExtractionResult(strategy='selenium')

        # Use BS4 extractor on rendered HTML
        from .bs4_extractor import BS4Extractor
        bs4 = BS4Extractor()
        result = bs4.extract(
            rendered_html, url,
            source_selectors=kwargs.get('source_selectors'),
            strip_selectors=kwargs.get('strip_selectors'),
        )
        result.strategy = f'selenium+{result.strategy}'
        return result

    def close(self):
        if self._browser:
            self._browser.close()
            self._browser = None
