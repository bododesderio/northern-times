"""
Social Monitor -- track brand/keyword mentions across public platforms.

Checks Google News RSS, Reddit JSON, Bing News RSS, and Hacker News Algolia
API for new mentions of each active SocialKeyword, deduplicates by URL,
classifies sentiment, and persists SocialMention records.
"""
import logging
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from html import unescape

import httpx
from django.utils import timezone as dj_tz

from apps.social.models import SocialKeyword, SocialMention

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

HTTP_TIMEOUT = 10.0
USER_AGENT = (
    'Mozilla/5.0 (compatible; NorthernTimesSocialMonitor/1.0; '
    '+https://northerntimes.news)'
)

POSITIVE_WORDS = frozenset({
    'praise', 'praised', 'success', 'successful', 'growth', 'win', 'won',
    'excellent', 'outstanding', 'remarkable', 'impressive', 'positive',
    'breakthrough', 'achieve', 'achieved', 'celebrates', 'milestone',
    'innovative', 'innovation', 'award', 'awarded', 'progress', 'boost',
    'boosts', 'improve', 'improved', 'thriving', 'record-breaking',
    'upgrade', 'upgraded', 'hero', 'heroic', 'triumph', 'triumphant',
})

NEGATIVE_WORDS = frozenset({
    'fail', 'failed', 'failure', 'crash', 'crashed', 'scandal', 'lawsuit',
    'attack', 'attacked', 'crisis', 'controversy', 'controversial',
    'resign', 'resigned', 'arrest', 'arrested', 'fraud', 'corruption',
    'collapse', 'collapsed', 'defeat', 'defeated', 'decline', 'declined',
    'disaster', 'tragic', 'tragedy', 'victim', 'victims', 'death', 'killed',
    'bankrupt', 'bankruptcy', 'layoff', 'layoffs', 'protest', 'protests',
    'violence', 'violent', 'warning', 'threat', 'threatened',
})


class SocialMonitor:
    """Fetch and persist social mentions for tracked keywords."""

    def __init__(self):
        self._client: httpx.Client | None = None

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def monitor_all_keywords(self) -> None:
        """Process every active SocialKeyword."""
        keywords = SocialKeyword.objects.filter(is_active=True)
        total = 0
        for kw in keywords:
            mentions = self.monitor_keyword(kw)
            total += len(mentions)
        logger.info('Social monitor complete: %d new mentions across %d keywords',
                     total, keywords.count())

    def monitor_keyword(self, keyword: SocialKeyword) -> list[SocialMention]:
        """Fetch mentions from all platforms, deduplicate, classify sentiment,
        and save new SocialMention records.  Returns the newly created list."""
        raw_mentions: list[dict] = []

        with httpx.Client(
            timeout=HTTP_TIMEOUT,
            headers={'User-Agent': USER_AGENT},
            follow_redirects=True,
        ) as client:
            self._client = client
            raw_mentions.extend(self._fetch_google_news(keyword.keyword))
            raw_mentions.extend(self._fetch_reddit(keyword.keyword))
            raw_mentions.extend(self._fetch_bing_news(keyword.keyword))
            raw_mentions.extend(self._fetch_hacker_news(keyword.keyword))
            self._client = None

        # Deduplicate by URL
        existing_urls = set(
            SocialMention.objects.filter(keyword=keyword)
            .values_list('url', flat=True)
        )
        seen_urls: set[str] = set()
        unique: list[dict] = []
        for m in raw_mentions:
            url = m.get('url', '')
            if url and url not in existing_urls and url not in seen_urls:
                seen_urls.add(url)
                unique.append(m)

        # Classify sentiment and build model instances
        created: list[SocialMention] = []
        for m in unique:
            sentiment = self._classify_sentiment(
                f"{m.get('title', '')} {m.get('text', '')}"
            )
            mention = SocialMention.objects.create(
                keyword=keyword,
                source=m['source'],
                title=m.get('title', '')[:500],
                text=m.get('text', ''),
                url=m.get('url', '')[:500],
                sentiment=sentiment,
                mentioned_at=m.get('mentioned_at', dj_tz.now()),
            )
            created.append(mention)

        logger.info('Keyword "%s": %d new mentions', keyword.keyword, len(created))
        return created

    # ------------------------------------------------------------------
    # Platform fetchers
    # ------------------------------------------------------------------

    def _fetch_google_news(self, query: str) -> list[dict]:
        """Google News RSS feed."""
        url = f'https://news.google.com/rss/search?q={_urlencode(query)}'
        try:
            resp = self._client.get(url)
            resp.raise_for_status()
        except httpx.HTTPError:
            logger.warning('Google News fetch failed for "%s"', query)
            return []

        items: list[dict] = []
        try:
            root = ET.fromstring(resp.text)
            for item in root.iter('item'):
                title = _xml_text(item, 'title')
                link = _xml_text(item, 'link')
                pub_date = _parse_rss_date(_xml_text(item, 'pubDate'))
                description = _xml_text(item, 'description')
                items.append({
                    'source': 'google_news',
                    'title': unescape(title),
                    'text': unescape(description),
                    'url': link,
                    'mentioned_at': pub_date,
                })
        except ET.ParseError:
            logger.warning('Google News XML parse error for "%s"', query)
        return items

    def _fetch_reddit(self, query: str) -> list[dict]:
        """Reddit search JSON API."""
        url = (
            f'https://www.reddit.com/search.json'
            f'?q={_urlencode(query)}&sort=new&limit=25'
        )
        try:
            resp = self._client.get(url)
            resp.raise_for_status()
            data = resp.json()
        except (httpx.HTTPError, ValueError):
            logger.warning('Reddit fetch failed for "%s"', query)
            return []

        items: list[dict] = []
        for child in data.get('data', {}).get('children', []):
            post = child.get('data', {})
            created_utc = post.get('created_utc', 0)
            items.append({
                'source': 'reddit',
                'title': post.get('title', ''),
                'text': post.get('selftext', '')[:2000],
                'url': f"https://reddit.com{post.get('permalink', '')}",
                'mentioned_at': datetime.fromtimestamp(
                    created_utc, tz=timezone.utc,
                ) if created_utc else dj_tz.now(),
            })
        return items

    def _fetch_bing_news(self, query: str) -> list[dict]:
        """Bing News RSS feed."""
        url = (
            f'https://www.bing.com/news/search'
            f'?q={_urlencode(query)}&format=rss'
        )
        try:
            resp = self._client.get(url)
            resp.raise_for_status()
        except httpx.HTTPError:
            logger.warning('Bing News fetch failed for "%s"', query)
            return []

        items: list[dict] = []
        try:
            root = ET.fromstring(resp.text)
            for item in root.iter('item'):
                title = _xml_text(item, 'title')
                link = _xml_text(item, 'link')
                pub_date = _parse_rss_date(_xml_text(item, 'pubDate'))
                description = _xml_text(item, 'description')
                items.append({
                    'source': 'bing_news',
                    'title': unescape(title),
                    'text': unescape(description),
                    'url': link,
                    'mentioned_at': pub_date,
                })
        except ET.ParseError:
            logger.warning('Bing News XML parse error for "%s"', query)
        return items

    def _fetch_hacker_news(self, query: str) -> list[dict]:
        """Hacker News Algolia API."""
        url = (
            f'https://hn.algolia.com/api/v1/search_by_date'
            f'?query={_urlencode(query)}&tags=story'
        )
        try:
            resp = self._client.get(url)
            resp.raise_for_status()
            data = resp.json()
        except (httpx.HTTPError, ValueError):
            logger.warning('Hacker News fetch failed for "%s"', query)
            return []

        items: list[dict] = []
        for hit in data.get('hits', []):
            story_url = hit.get('url') or f"https://news.ycombinator.com/item?id={hit.get('objectID', '')}"
            created_str = hit.get('created_at', '')
            try:
                mentioned_at = datetime.fromisoformat(
                    created_str.replace('Z', '+00:00')
                )
            except (ValueError, AttributeError):
                mentioned_at = dj_tz.now()

            items.append({
                'source': 'hacker_news',
                'title': hit.get('title', ''),
                'text': hit.get('story_text', '') or '',
                'url': story_url,
                'mentioned_at': mentioned_at,
            })
        return items

    # ------------------------------------------------------------------
    # Sentiment classification
    # ------------------------------------------------------------------

    @staticmethod
    def _classify_sentiment(text: str) -> str:
        """Simple keyword-based sentiment classification."""
        words = set(text.lower().split())
        pos = len(words & POSITIVE_WORDS)
        neg = len(words & NEGATIVE_WORDS)
        if pos > neg:
            return 'positive'
        if neg > pos:
            return 'negative'
        return 'neutral'


# ---------------------------------------------------------------------------
# Module-level helpers
# ---------------------------------------------------------------------------

def _urlencode(text: str) -> str:
    """Percent-encode a query string value."""
    from urllib.parse import quote_plus
    return quote_plus(text)


def _xml_text(element, tag: str) -> str:
    """Safely extract text from an XML child element."""
    child = element.find(tag)
    return child.text.strip() if child is not None and child.text else ''


def _parse_rss_date(date_str: str) -> datetime:
    """Parse an RFC-822 date from RSS or fall back to now."""
    if not date_str:
        return dj_tz.now()
    from email.utils import parsedate_to_datetime
    try:
        return parsedate_to_datetime(date_str)
    except (ValueError, TypeError):
        return dj_tz.now()
