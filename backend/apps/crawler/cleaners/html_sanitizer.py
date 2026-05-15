"""Final safety-net HTML sanitizer -- runs after content_cleaner."""
import re
from bs4 import BeautifulSoup


def sanitize_html(html: str) -> str:
    """Remove any remaining dangerous content after cleaning."""
    if not html:
        return ''
    soup = BeautifulSoup(html, 'lxml')

    # Remove any remaining script/style tags
    for tag in soup.find_all(['script', 'style', 'noscript']):
        tag.decompose()

    # Remove event handlers
    for tag in soup.find_all(True):
        attrs_to_remove = [a for a in tag.attrs if a.startswith('on')]
        for attr in attrs_to_remove:
            del tag[attr]

    # Remove javascript: and data: URLs from links
    for a in soup.find_all('a', href=True):
        href = a['href'].strip().lower()
        if href.startswith(('javascript:', 'vbscript:', 'data:')):
            a['href'] = '#'

    return str(soup.body) if soup.body else str(soup)
