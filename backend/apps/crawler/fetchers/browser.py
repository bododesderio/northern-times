"""Selenium WebDriver fetcher for JavaScript-heavy sites."""
import logging

from django.conf import settings

logger = logging.getLogger(__name__)


class BrowserFetcher:
    """Fetches pages using headless Chromium via Selenium for JS-rendered content."""

    def __init__(self):
        self._driver = None

    def _get_driver(self):
        """Lazy-init Selenium WebDriver."""
        if self._driver is None:
            from selenium import webdriver
            from selenium.webdriver.chrome.options import Options
            from selenium.webdriver.chrome.service import Service

            options = Options()
            options.add_argument('--headless=new')
            options.add_argument('--no-sandbox')
            options.add_argument('--disable-dev-shm-usage')
            options.add_argument('--disable-gpu')
            options.add_argument('--window-size=1920,1080')
            options.add_argument('--disable-extensions')
            options.add_argument('--disable-infobars')
            options.add_argument(
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                'AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'
            )

            # Block images/fonts for speed
            prefs = {
                'profile.managed_default_content_settings.images': 2,
                'profile.managed_default_content_settings.fonts': 2,
            }
            options.add_experimental_option('prefs', prefs)

            remote_url = getattr(settings, 'SELENIUM_REMOTE_URL', '')
            chromedriver = getattr(settings, 'CHROMEDRIVER_PATH', None)

            if remote_url:
                self._driver = webdriver.Remote(
                    command_executor=remote_url,
                    options=options,
                )
            elif chromedriver:
                service = Service(executable_path=chromedriver)
                self._driver = webdriver.Chrome(service=service, options=options)
            else:
                self._driver = webdriver.Chrome(options=options)

            self._driver.set_page_load_timeout(30)
            self._driver.implicitly_wait(5)

        return self._driver

    def fetch(self, url: str, wait_seconds: int = 3) -> str:
        """Fetch page HTML after JavaScript rendering. Returns HTML string or ''."""
        import time
        try:
            driver = self._get_driver()
            driver.get(url)

            # Wait for JS rendering
            time.sleep(wait_seconds)

            # Scroll down to trigger lazy loading
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight / 2);")
            time.sleep(1)

            html = driver.page_source
            logger.info(f"Browser fetched {url} ({len(html)} chars)")
            return html

        except Exception as e:
            logger.error(f"Browser fetch failed for {url}: {e}")
            return ''

    def close(self):
        if self._driver:
            try:
                self._driver.quit()
            except Exception:
                pass
            self._driver = None

    def __del__(self):
        self.close()
