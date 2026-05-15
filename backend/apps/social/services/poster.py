"""
Social Poster -- publish articles to Facebook, X/Twitter, and Telegram.

Each platform method is isolated: one failing does not block the others.
Credentials are read from the Setting model (DB) with environment variable
fallback.
"""
import logging
import os

import httpx
from django.conf import settings as django_settings

from apps.core.models import Setting

logger = logging.getLogger(__name__)

HTTP_TIMEOUT = 15.0


def _setting(key: str) -> str | None:
    """Read a setting from the DB, falling back to an environment variable."""
    value = Setting.get(key)
    if value:
        return str(value)
    return os.environ.get(key)


class SocialPoster:
    """Post article links to configured social platforms."""

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def post_article(self, article) -> dict[str, bool]:
        """Attempt to post the article to every enabled platform.

        Returns a dict like ``{'facebook': True, 'twitter': False, ...}``
        indicating per-platform success.
        """
        results: dict[str, bool] = {}

        if _setting('FACEBOOK_PAGE_TOKEN'):
            results['facebook'] = self.post_to_facebook(article)
        if _setting('TWITTER_BEARER_TOKEN'):
            results['twitter'] = self.post_to_twitter(article)
        if _setting('TELEGRAM_BOT_TOKEN') and _setting('TELEGRAM_CHANNEL_ID'):
            results['telegram'] = self.post_to_telegram(article)

        logger.info(
            'Social post results for "%s": %s',
            article.title[:60], results,
        )
        return results

    # ------------------------------------------------------------------
    # Facebook
    # ------------------------------------------------------------------

    def post_to_facebook(self, article) -> bool:
        """Post a link to a Facebook Page via the Graph API.

        Requires a Page Access Token with ``pages_manage_posts`` permission.
        """
        token = _setting('FACEBOOK_PAGE_TOKEN')
        page_id = _setting('FACEBOOK_PAGE_ID') or 'me'
        if not token:
            logger.warning('Facebook posting skipped: no FACEBOOK_PAGE_TOKEN')
            return False

        url = f'https://graph.facebook.com/v19.0/{page_id}/feed'
        payload = {
            'message': self._build_caption(article),
            'link': self._article_url(article),
            'access_token': token,
        }

        try:
            with httpx.Client(timeout=HTTP_TIMEOUT) as client:
                resp = client.post(url, data=payload)
                resp.raise_for_status()
                post_id = resp.json().get('id', '')
                logger.info('Facebook post created: %s', post_id)
                return True
        except httpx.HTTPError as exc:
            logger.error('Facebook post failed: %s', exc)
            return False

    # ------------------------------------------------------------------
    # X / Twitter
    # ------------------------------------------------------------------

    def post_to_twitter(self, article) -> bool:
        """Post a tweet via the X/Twitter API v2.

        Requires a Bearer Token (OAuth 2.0 App-Only or User Context) with
        ``tweet.write`` scope.
        """
        bearer = _setting('TWITTER_BEARER_TOKEN')
        if not bearer:
            logger.warning('Twitter posting skipped: no TWITTER_BEARER_TOKEN')
            return False

        url = 'https://api.twitter.com/2/tweets'
        text = self._build_tweet(article)
        headers = {
            'Authorization': f'Bearer {bearer}',
            'Content-Type': 'application/json',
        }
        payload = {'text': text}

        try:
            with httpx.Client(timeout=HTTP_TIMEOUT) as client:
                resp = client.post(url, json=payload, headers=headers)
                resp.raise_for_status()
                tweet_id = resp.json().get('data', {}).get('id', '')
                logger.info('Tweet created: %s', tweet_id)
                return True
        except httpx.HTTPError as exc:
            logger.error('Twitter post failed: %s', exc)
            return False

    # ------------------------------------------------------------------
    # Telegram
    # ------------------------------------------------------------------

    def post_to_telegram(self, article) -> bool:
        """Send a message to a Telegram channel via the Bot API."""
        bot_token = _setting('TELEGRAM_BOT_TOKEN')
        channel = _setting('TELEGRAM_CHANNEL_ID')
        if not bot_token or not channel:
            logger.warning('Telegram posting skipped: missing credentials')
            return False

        url = f'https://api.telegram.org/bot{bot_token}/sendMessage'
        text = self._build_telegram_message(article)
        payload = {
            'chat_id': channel,
            'text': text,
            'parse_mode': 'HTML',
            'disable_web_page_preview': False,
        }

        try:
            with httpx.Client(timeout=HTTP_TIMEOUT) as client:
                resp = client.post(url, json=payload)
                resp.raise_for_status()
                ok = resp.json().get('ok', False)
                if ok:
                    logger.info('Telegram message sent to %s', channel)
                else:
                    logger.warning(
                        'Telegram API returned ok=false: %s',
                        resp.json().get('description', ''),
                    )
                return ok
        except httpx.HTTPError as exc:
            logger.error('Telegram post failed: %s', exc)
            return False

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _article_url(article) -> str:
        """Build the public URL for an article."""
        try:
            from django.contrib.sites.models import Site
            domain = Site.objects.get_current().domain
            scheme = 'https' if not domain.startswith('localhost') else 'http'
        except Exception:
            domain = 'northerntimes.news'
            scheme = 'https'
        return f'{scheme}://{domain}/article/{article.slug}'

    @staticmethod
    def _build_caption(article) -> str:
        """Caption for Facebook / generic use."""
        excerpt = article.excerpt[:200] if article.excerpt else ''
        parts = [article.title]
        if excerpt:
            parts.append(excerpt)
        return '\n\n'.join(parts)

    @staticmethod
    def _build_tweet(article) -> str:
        """Build a tweet (max 280 chars) with title + link."""
        from django.contrib.sites.models import Site
        try:
            domain = Site.objects.get_current().domain
            scheme = 'https' if not domain.startswith('localhost') else 'http'
        except Exception:
            domain = 'northerntimes.news'
            scheme = 'https'
        url = f'{scheme}://{domain}/article/{article.slug}'
        # URL takes ~23 chars (t.co wrapping)
        max_title = 280 - 24  # 23 for URL + 1 space
        title = article.title
        if len(title) > max_title:
            title = title[: max_title - 1] + '\u2026'
        return f'{title} {url}'

    @staticmethod
    def _build_telegram_message(article) -> str:
        """Build an HTML-formatted Telegram message."""
        from django.contrib.sites.models import Site
        try:
            domain = Site.objects.get_current().domain
            scheme = 'https' if not domain.startswith('localhost') else 'http'
        except Exception:
            domain = 'northerntimes.news'
            scheme = 'https'
        url = f'{scheme}://{domain}/article/{article.slug}'
        excerpt = article.excerpt[:300] if article.excerpt else ''
        parts = [f'<b>{article.title}</b>']
        if excerpt:
            parts.append(excerpt)
        parts.append(f'\n<a href="{url}">Read more</a>')
        return '\n\n'.join(parts)
