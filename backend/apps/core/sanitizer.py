"""
HTML sanitizer with profile-based tag/attribute whitelisting.

Two profiles:
- ``article``: rich content (headings, images, embeds, tables)
- ``comment``: minimal safe HTML (text formatting only)
"""
import re
from urllib.parse import urlparse

import bleach
from bleach.css_sanitizer import CSSSanitizer


# ---------------------------------------------------------------------------
# Whitelisted embed domains (scheme-agnostic)
# ---------------------------------------------------------------------------
_EMBED_DOMAINS = {
    'www.youtube.com',
    'youtube.com',
    'www.youtube-nocookie.com',
    'player.vimeo.com',
    'vimeo.com',
    'platform.twitter.com',
    'www.twitter.com',
    'twitter.com',
    'x.com',
}


def _filter_iframe_src(tag: str, name: str, value: str) -> str | None:
    """Allow src on iframes only when the host is a whitelisted embed domain."""
    if tag != 'iframe':
        return value
    if name != 'src':
        return value
    try:
        parsed = urlparse(value)
        if parsed.hostname and parsed.hostname in _EMBED_DOMAINS:
            return value
    except Exception:
        pass
    return None


def _article_attr_filter(tag: str, name: str, value: str) -> str | None:
    """Attribute filter callback for the *article* profile."""
    # iframe: only src from whitelisted domains
    if tag == 'iframe':
        if name == 'src':
            return _filter_iframe_src(tag, name, value)
        return None  # strip all other iframe attributes

    # a: only href
    if tag == 'a' and name == 'href':
        return value

    # img: explicit allowed set
    if tag == 'img' and name in ('src', 'alt', 'width', 'height', 'loading'):
        return value

    # video: src + controls
    if tag == 'video':
        if name in ('src', 'controls'):
            return value
        return None

    # For all other allowed tags the attribute is passed through only if
    # it appears in the per-tag allowlist below (handled by bleach's own
    # ``attributes`` dict).  Returning the value here is safe because bleach
    # will have already stripped unknown tags/attributes.
    return value


# ---------------------------------------------------------------------------
# Profile definitions
# ---------------------------------------------------------------------------

_ARTICLE_TAGS = [
    'h2', 'h3', 'h4', 'h5', 'h6',
    'p', 'a', 'img',
    'ul', 'ol', 'li',
    'blockquote',
    'figure', 'figcaption',
    'strong', 'em', 'br',
    'iframe',
    'video',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
]

_ARTICLE_ATTRIBUTES = {
    'a': ['href'],
    'img': ['src', 'alt', 'width', 'height', 'loading'],
    'iframe': ['src'],
    'video': ['src', 'controls'],
}

_COMMENT_TAGS = ['p', 'a', 'strong', 'em', 'br']

_COMMENT_ATTRIBUTES = {
    'a': ['href', 'rel'],
}


_PROFILES = {
    'article': {
        'tags': _ARTICLE_TAGS,
        'attributes': _ARTICLE_ATTRIBUTES,
        'strip': True,
    },
    'comment': {
        'tags': _COMMENT_TAGS,
        'attributes': _COMMENT_ATTRIBUTES,
        'strip': True,
    },
}


def _add_nofollow_to_comment_links(html: str) -> str:
    """Ensure every <a> tag in comment HTML carries rel="nofollow"."""
    def _add_rel(match: re.Match) -> str:
        tag = match.group(0)
        if 'rel=' in tag:
            return tag
        return tag.replace('<a ', '<a rel="nofollow" ', 1)

    return re.sub(r'<a\s[^>]*>', _add_rel, html, flags=re.IGNORECASE)


# ---------------------------------------------------------------------------
# Public API
# ---------------------------------------------------------------------------

# Raw-text elements whose *content* must be removed, not just their tags.
# Matches balanced <script>/<style> blocks (any attributes, any inner body).
_RAW_TEXT_ELEMENT_RE = re.compile(
    r'<(script|style)\b[^>]*>.*?</\1\s*>',
    re.IGNORECASE | re.DOTALL,
)


def sanitize(html: str, profile: str = 'article') -> str:
    """
    Sanitize *html* using the named profile.

    Parameters
    ----------
    html : str
        Raw (untrusted) HTML string.
    profile : str
        ``'article'`` or ``'comment'``.

    Returns
    -------
    str
        Cleaned HTML with only the allowed tags and attributes.
    """
    if not html:
        return ''

    cfg = _PROFILES.get(profile)
    if cfg is None:
        raise ValueError(f"Unknown sanitizer profile: {profile!r}. "
                         f"Choose from {list(_PROFILES)}")

    # bleach strips the <script>/<style> *tags* but keeps their raw-text
    # content (e.g. "<script>alert(1)</script>" -> "alert(1)"). Drop these
    # elements wholesale first so no inline script/style body survives.
    html = _RAW_TEXT_ELEMENT_RE.sub('', html)

    cleaned = bleach.clean(
        html,
        tags=cfg['tags'],
        attributes=cfg['attributes'],
        strip=cfg['strip'],
        protocols=['http', 'https', 'mailto'],
    )

    if profile == 'comment':
        cleaned = _add_nofollow_to_comment_links(cleaned)

    return cleaned


def strip_tags(html: str) -> str:
    """Remove *all* HTML tags, returning plain text."""
    if not html:
        return ''
    html = _RAW_TEXT_ELEMENT_RE.sub('', html)
    return bleach.clean(html, tags=[], strip=True).strip()
