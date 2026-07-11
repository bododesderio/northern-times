"""Selenium WebDriver fetcher for JavaScript-heavy sites.

Includes a lightweight stealth layer (injected before every page load) that hides
the usual headless/automation tells — ``navigator.webdriver``, empty plugins,
software WebGL renderer, missing ``window.chrome`` — so Cloudflare's JS challenge
resolves and JS-SPA news sites (Nile Post, Daily Monitor) render real content
instead of an app shell / "just a moment" page.
"""
import logging

from django.conf import settings

logger = logging.getLogger(__name__)

# Injected into every new document before the page's own scripts run.
_STEALTH_JS = r"""
Object.defineProperty(navigator, 'webdriver', {get: () => undefined});
Object.defineProperty(navigator, 'plugins', {get: () => [1, 2, 3, 4, 5]});
Object.defineProperty(navigator, 'languages', {get: () => ['en-US', 'en']});
window.chrome = window.chrome || {runtime: {}};
try {
  const gp = WebGLRenderingContext.prototype.getParameter;
  WebGLRenderingContext.prototype.getParameter = function (p) {
    if (p === 37445) return 'Intel Inc.';            // UNMASKED_VENDOR_WEBGL
    if (p === 37446) return 'Intel Iris OpenGL Engine';  // UNMASKED_RENDERER_WEBGL
    return gp.call(this, p);
  };
} catch (e) {}
"""


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
            # Removes the "Chrome is being controlled by automated software" tell.
            options.add_argument('--disable-blink-features=AutomationControlled')
            # Don't fetch images — heavy news pages otherwise hit the 30s renderer
            # timeout before the DOM (which is all we need) settles.
            options.add_argument('--blink-settings=imagesEnabled=false')
            # 'eager' returns control at DOMContentLoaded instead of waiting for
            # every sub-resource, so a slow ad/tracker can't hang driver.get().
            options.page_load_strategy = 'eager'
            options.add_argument(
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
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

            # Inject stealth patches before any page script runs.
            try:
                self._driver.execute_cdp_cmd(
                    'Page.addScriptToEvaluateOnNewDocument', {'source': _STEALTH_JS},
                )
            except Exception as e:
                logger.debug("stealth CDP injection unavailable: %s", e)

        return self._driver

    def fetch_links(self, url: str, wait_seconds: int = 6) -> list:
        """Render *url* and return [(href, anchor_text)] for on-page links.

        Used by the HTML-listing fetcher to harvest article links from a
        JS-rendered / Cloudflare-protected index page.
        """
        import time
        from apps.crawler.fetchers.url_guard import is_safe_public_url
        if not is_safe_public_url(url):
            logger.warning(f"Browser link fetch blocked by SSRF guard: {url}")
            return []
        try:
            driver = self._get_driver()
            driver.get(url)
            time.sleep(wait_seconds)
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight / 2);")
            time.sleep(1)
            pairs = driver.execute_script(
                "return Array.from(document.querySelectorAll('a[href]'))"
                ".map(a => [a.href, (a.textContent||'').trim()]);"
            )
            return pairs or []
        except Exception as e:
            logger.error(f"Browser link fetch failed for {url}: {e}")
            return []

    def fetch(self, url: str, wait_seconds: int = 3) -> str:
        """Fetch page HTML after JavaScript rendering. Returns HTML string or ''."""
        import time
        from apps.crawler.fetchers.url_guard import is_safe_public_url
        if not is_safe_public_url(url):
            logger.warning(f"Browser fetch blocked by SSRF guard: {url}")
            return ''
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
