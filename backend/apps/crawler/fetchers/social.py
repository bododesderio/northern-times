"""Social platform mention fetcher."""
import logging
from dataclasses import dataclass
from datetime import datetime

import feedparser
import httpx
from django.conf import settings

logger = logging.getLogger(__name__)


@dataclass
class SocialMentionItem:
    """A mention found on a social platform."""
    source: str
    title: str
    text: str
    url: str
    mentioned_at: datetime


class SocialFetcher:
    """Fetches mentions from social platforms for keyword monitoring."""

    def __init__(self):
        self.timeout = 10
        self.bot_name = getattr(settings, 'APP_BOT_NAME', 'NewsCrawlerBot')

    def fetch_all(self, keyword: str) -> list[SocialMentionItem]:
        """Fetch mentions from all platforms for a keyword."""
        results = []
        for method in [self._google_news, self._reddit, self._bing_news, self._hacker_news]:
            try:
                results.extend(method(keyword))
            except Exception as e:
                logger.warning(f"Social fetch failed ({method.__name__}): {e}")
        return results

    def _google_news(self, keyword: str) -> list[SocialMentionItem]:
        url = f'https://news.google.com/rss/search?q={keyword}&hl=en'
        response = httpx.get(url, timeout=self.timeout, headers={'User-Agent': self.bot_name})
        feed = feedparser.parse(response.text)
        items = []
        for entry in feed.entries[:25]:
            pub = None
            if hasattr(entry, 'published_parsed') and entry.published_parsed:
                try:
                    pub = datetime(*entry.published_parsed[:6])
                except (TypeError, ValueError):
                    pub = datetime.utcnow()
            items.append(SocialMentionItem(
                source='google_news',
                title=entry.get('title', ''),
                text=entry.get('summary', ''),
                url=entry.get('link', ''),
                mentioned_at=pub or datetime.utcnow(),
            ))
        return items

    def _reddit(self, keyword: str) -> list[SocialMentionItem]:
        url = f'https://www.reddit.com/search.json?q={keyword}&sort=new&limit=25'
        response = httpx.get(
            url,
            timeout=self.timeout,
            headers={'User-Agent': f'{self.bot_name}/2.0 (news aggregator)'},
        )
        data = response.json()
        items = []
        for child in data.get('data', {}).get('children', []):
            post = child.get('data', {})
            items.append(SocialMentionItem(
                source='reddit',
                title=post.get('title', ''),
                text=post.get('selftext', '')[:500],
                url=f"https://reddit.com{post.get('permalink', '')}",
                mentioned_at=datetime.utcfromtimestamp(post.get('created_utc', 0)),
            ))
        return items

    def _bing_news(self, keyword: str) -> list[SocialMentionItem]:
        url = f'https://www.bing.com/news/search?q={keyword}&format=rss'
        response = httpx.get(url, timeout=self.timeout, headers={'User-Agent': self.bot_name})
        feed = feedparser.parse(response.text)
        items = []
        for entry in feed.entries[:25]:
            pub = None
            if hasattr(entry, 'published_parsed') and entry.published_parsed:
                try:
                    pub = datetime(*entry.published_parsed[:6])
                except (TypeError, ValueError):
                    pub = datetime.utcnow()
            items.append(SocialMentionItem(
                source='bing_news',
                title=entry.get('title', ''),
                text=entry.get('summary', ''),
                url=entry.get('link', ''),
                mentioned_at=pub or datetime.utcnow(),
            ))
        return items

    def _hacker_news(self, keyword: str) -> list[SocialMentionItem]:
        url = f'https://hn.algolia.com/api/v1/search_by_date?query={keyword}&tags=story&hitsPerPage=25'
        response = httpx.get(url, timeout=self.timeout)
        data = response.json()
        items = []
        for hit in data.get('hits', []):
            mentioned_at = datetime.utcnow()
            if hit.get('created_at'):
                try:
                    mentioned_at = datetime.fromisoformat(
                        hit['created_at'].replace('Z', '+00:00')
                    )
                except (ValueError, TypeError):
                    pass
            items.append(SocialMentionItem(
                source='hacker_news',
                title=hit.get('title', ''),
                text=hit.get('story_text', '') or hit.get('title', ''),
                url=hit.get('url', '') or f"https://news.ycombinator.com/item?id={hit.get('objectID', '')}",
                mentioned_at=mentioned_at,
            ))
        return items
