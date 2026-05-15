"""
Pull-quote extractor — extracts notable quotes from article HTML content.

Extracts:
1. HTML blockquotes
2. Quoted speech (text between quotation marks with attribution)
"""
import re
import logging

from bs4 import BeautifulSoup

logger = logging.getLogger(__name__)


class QuoteExtractor:
    """Extract notable quotes from article content."""

    # Matches "quoted text" said/says/told/according to Speaker Name
    SPEECH_PATTERN = re.compile(
        r'["\u201c]([^"\u201d]{20,300})["\u201d]'
        r'[\s,]*'
        r'(?:said|says|told|stated|noted|added|explained|remarked|according\s+to)'
        r'\s+'
        r'([A-Z][a-zA-Z\s\.\-]{2,50})',
        re.UNICODE,
    )

    def extract(self, html_content: str, max_quotes: int = 5) -> list[dict]:
        """Extract quotes from HTML content.

        Returns list of dicts: [{'text': '...', 'source': '...', 'type': 'blockquote'|'speech'}]
        """
        quotes = []

        # 1. Extract blockquotes from HTML
        soup = BeautifulSoup(html_content, 'html.parser')
        for bq in soup.find_all('blockquote'):
            text = bq.get_text(strip=True)
            if len(text) >= 20:
                quotes.append({
                    'text': text[:500],
                    'source': '',
                    'type': 'blockquote',
                })

        # 2. Extract quoted speech from plain text
        plain_text = soup.get_text(' ', strip=True)
        for match in self.SPEECH_PATTERN.finditer(plain_text):
            quote_text = match.group(1).strip()
            speaker = match.group(2).strip().rstrip('.')
            # Skip duplicates
            if any(q['text'] == quote_text for q in quotes):
                continue
            quotes.append({
                'text': quote_text,
                'source': speaker,
                'type': 'speech',
            })

        # Deduplicate and limit
        seen = set()
        unique = []
        for q in quotes:
            key = q['text'][:50]
            if key not in seen:
                seen.add(key)
                unique.append(q)

        return unique[:max_quotes]
