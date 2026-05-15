"""Open Graph, Twitter Card, and Schema.org metadata extraction."""
import json
import logging
import re

from bs4 import BeautifulSoup
from .base import ExtractionResult

logger = logging.getLogger(__name__)


def extract_metadata(html: str, url: str) -> dict:
    """Extract all metadata from HTML page. Returns dict with all found metadata."""
    soup = BeautifulSoup(html, 'lxml')
    meta: dict[str, str] = {}

    # Open Graph
    for tag in soup.find_all('meta', attrs={'property': re.compile(r'^og:')}):
        key = tag.get('property', '').replace('og:', '')
        meta[f'og_{key}'] = tag.get('content', '')

    # Twitter Card
    for tag in soup.find_all('meta', attrs={'name': re.compile(r'^twitter:')}):
        key = tag.get('name', '').replace('twitter:', '')
        meta[f'twitter_{key}'] = tag.get('content', '')

    # Standard meta tags
    desc = soup.find('meta', attrs={'name': 'description'})
    if desc:
        meta['description'] = desc.get('content', '')

    author = soup.find('meta', attrs={'name': 'author'})
    if author:
        meta['author'] = author.get('content', '')

    # Title
    title_tag = soup.find('title')
    if title_tag:
        meta['page_title'] = title_tag.get_text(strip=True)

    # Canonical URL
    canonical = soup.find('link', attrs={'rel': 'canonical'})
    if canonical:
        meta['canonical'] = canonical.get('href', '')

    # Published date (various meta patterns)
    for name in ['article:published_time', 'datePublished', 'date', 'pubdate']:
        tag = (
            soup.find('meta', attrs={'property': name})
            or soup.find('meta', attrs={'name': name})
        )
        if tag:
            meta['published_date'] = tag.get('content', '')
            break

    # JSON-LD structured data
    for script in soup.find_all('script', type='application/ld+json'):
        try:
            ld = json.loads(script.string or '{}')
            if isinstance(ld, list):
                ld = ld[0] if ld else {}
            if ld.get('@type') in ('NewsArticle', 'Article', 'BlogPosting', 'WebPage'):
                meta['ld_headline'] = ld.get('headline', '')
                meta['ld_description'] = ld.get('description', '')

                # Author
                meta['ld_author'] = ''
                author_data = ld.get('author', {})
                if isinstance(author_data, dict):
                    meta['ld_author'] = author_data.get('name', '')
                elif isinstance(author_data, list) and author_data:
                    first = author_data[0]
                    meta['ld_author'] = (
                        first.get('name', '') if isinstance(first, dict) else str(first)
                    )

                # Image
                meta['ld_image'] = ''
                img_data = ld.get('image', '')
                if isinstance(img_data, dict):
                    meta['ld_image'] = img_data.get('url', '')
                elif isinstance(img_data, list) and img_data:
                    first_img = img_data[0]
                    meta['ld_image'] = (
                        first_img if isinstance(first_img, str) else first_img.get('url', '')
                    )
                elif isinstance(img_data, str):
                    meta['ld_image'] = img_data

                meta['ld_date'] = ld.get('datePublished', '')
        except (json.JSONDecodeError, TypeError, AttributeError):
            continue

    # Best image (priority: og:image -> twitter:image -> ld:image)
    meta['best_image'] = (
        meta.get('og_image', '')
        or meta.get('twitter_image', '')
        or meta.get('ld_image', '')
    )

    # Best title
    meta['best_title'] = (
        meta.get('og_title', '')
        or meta.get('ld_headline', '')
        or meta.get('twitter_title', '')
        or meta.get('page_title', '')
    )

    # Best author
    meta['best_author'] = (
        meta.get('author', '')
        or meta.get('ld_author', '')
    )

    # Best date
    meta['best_date'] = (
        meta.get('ld_date', '')
        or meta.get('published_date', '')
        or meta.get('og_article:published_time', '')
    )

    return meta
