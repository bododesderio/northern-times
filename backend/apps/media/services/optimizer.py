import logging

from bs4 import BeautifulSoup

logger = logging.getLogger(__name__)


def optimize_html(html: str) -> str:
    """Process HTML content to optimize image loading and semantics.

    - Adds loading="lazy" and decoding="async" to all img tags.
    - First image gets fetchpriority="high" and loading="eager" instead.
    - Wraps images in <figure> tags if not already wrapped.

    Args:
        html: Raw HTML content string.

    Returns:
        Optimized HTML string.
    """
    if not html:
        return html

    soup = BeautifulSoup(html, 'html.parser')
    images = soup.find_all('img')

    if not images:
        return html

    for index, img in enumerate(images):
        is_first = index == 0

        # Loading strategy
        if is_first:
            img['loading'] = 'eager'
            img['fetchpriority'] = 'high'
        else:
            img['loading'] = 'lazy'

        # Async decoding for all images
        img['decoding'] = 'async'

        # Wrap in <figure> if not already inside one
        if not img.find_parent('figure'):
            figure = soup.new_tag('figure')

            # Preserve alt text as figcaption if present
            alt_text = img.get('alt', '').strip()

            # Replace the img with the figure, then nest the img inside
            img.replace_with(figure)
            figure.append(img)

            if alt_text:
                figcaption = soup.new_tag('figcaption')
                figcaption.string = alt_text
                figure.append(figcaption)

    return str(soup)
