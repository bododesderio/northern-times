"""
Unified content cleaning and normalization for The Northern Times.

Consolidates all HTML cleaning, ad stripping, boilerplate removal,
image normalization, and content sanitization into a single pipeline.

Replaces duplicated logic previously split across:
  - Python extractor.py::clean_html()
  - PHP CrawlerEngine::cleanContent()
  - PHP ContentNormalizer::normalize()
  - PHP ArticleScraper::cleanExtractedHtml()
"""

import re
import logging
from urllib.parse import urljoin

from bs4 import BeautifulSoup, Tag

logger = logging.getLogger(__name__)

# ── Inline ad patterns (regex, applied before DOM parsing) ────

INLINE_AD_PATTERNS = [
    # Ad insertion markers
    re.compile(r'<div[^>]*(?:class|id)=["\'][^"\']*(?:ad[-_]?insert|in[-_]?article[-_]?ad|mid[-_]?article|inline[-_]?ad|native[-_]?ad|sponsored[-_]?content)[^"\']*["\'][^>]*>.*?</div>', re.I | re.S),
    # Google AdSense / DFP
    re.compile(r'<ins[^>]*class=["\']adsbygoogle["\'][^>]*>.*?</ins>', re.I | re.S),
    re.compile(r'<div[^>]*id=["\']div-gpt-ad[^"\']*["\'][^>]*>.*?</div>', re.I | re.S),
    # Outbrain / Taboola / mgid recommendation widgets
    re.compile(r'<div[^>]*class=["\'][^"\']*(?:OUTBRAIN|outbrain|taboola|mgid|revcontent|content-ad)[^"\']*["\'][^>]*>.*?</div>', re.I | re.S),
    # Newsletter signup blocks
    re.compile(r'<(?:div|section|aside)[^>]*(?:class|id)=["\'][^"\']*(?:newsletter|subscribe|email[-_]?signup|opt[-_]?in|mailing[-_]?list)[^"\']*["\'][^>]*>.*?</(?:div|section|aside)>', re.I | re.S),
    # "Follow us on..." blocks
    re.compile(r'<(?:div|p|section)[^>]*>(?:\s*<[^>]*>)*\s*(?:Follow|Like|Join)\s+(?:us\s+)?(?:on|at)\s+(?:Twitter|Facebook|Instagram|TikTok|YouTube|X|Telegram|WhatsApp).*?</(?:div|p|section)>', re.I | re.S),
    # "Download our app" blocks
    re.compile(r'<(?:div|p)[^>]*>.*?(?:Download\s+(?:our|the)\s+app|Get\s+(?:it\s+)?on\s+(?:Google\s+Play|App\s+Store)).*?</(?:div|p)>', re.I | re.S),
    # "Read also / See also" blocks
    re.compile(r'<(?:div|p|aside)[^>]*(?:class|id)=["\'][^"\']*(?:read[-_]?also|see[-_]?also|also[-_]?read|related[-_]?link)[^"\']*["\'][^>]*>.*?</(?:div|p|aside)>', re.I | re.S),
    # Donation / support blocks
    re.compile(r'<(?:div|section)[^>]*(?:class|id)=["\'][^"\']*(?:donat|support[-_]?us|fundrais|patron|ko[-_]?fi)[^"\']*["\'][^>]*>.*?</(?:div|section)>', re.I | re.S),
]

# WordPress boilerplate patterns
WP_PATTERNS = [
    re.compile(r'<p[^>]*>\s*The post\s+<a[^>]*>.*?</a>\s+appeared first on\s+<a[^>]*>.*?</a>\.\s*</p>', re.I | re.S),
    re.compile(r'<p[^>]*>\s*The post\s+.{5,300}\s+appeared first on\s+.{3,100}\.\s*</p>', re.I | re.S),
    re.compile(r'<h[2-6][^>]*>\s*Share\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)', re.I | re.S),
    re.compile(r'<h[2-6][^>]*>\s*Like\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)', re.I | re.S),
    re.compile(r'<h[2-6][^>]*>\s*Related\s*:?\s*</h[2-6]>.*$', re.I | re.S),
    re.compile(r'<ul[^>]*>\s*(?:<li[^>]*>\s*<a[^>]*>Share\s+on\s+\w+[^<]*</a>\s*</li>\s*){2,}</ul>', re.I | re.S),
    re.compile(r'<a[^>]*>Share\s+on\s+\w+\s*\([^)]*\)\s*\w*</a>', re.I | re.S),
    re.compile(r'<p[^>]*>\s*Like\s+Loading\s*\.{0,3}\s*</p>', re.I | re.S),
    re.compile(r'<p[^>]*>\s*<a[^>]*>\s*See\s+also\s+[^<]+</a>\s*</p>', re.I | re.S),
]

# Tags to completely remove
STRIP_TAGS = {
    "script", "style", "noscript", "svg", "form", "input", "button",
    "select", "textarea", "nav", "header", "footer", "aside",
}

# Class/ID patterns indicating junk elements
STRIP_CLASSES_RE = re.compile(
    r"sidebar|widget|ads?[-_]|ad[-_]?container|ad[-_]?wrapper|ad[-_]?slot|"
    r"social[-_]?share|sharing|share[-_]?button|share[-_]?bar|social[-_]?media|"
    r"related[-_]?posts?|related[-_]?articles?|more[-_]?stories|recommended|"
    r"comments?|comment[-_]?section|disqus|"
    r"newsletter|subscribe|signup|sign[-_]?up|opt[-_]?in|email[-_]?capture|"
    r"breadcrumb|author[-_]?box|popup|modal|cookie[-_]?banner|cookie[-_]?consent|"
    r"promo|promoti|advertis|sponsor|paid[-_]?content|native[-_]?ad|"
    r"outbrain|taboola|mgid|revcontent|content[-_]?ad|"
    r"breaking[-_]?news[-_]?ticker|ticker|marquee|"
    r"google[-_]?ad|doubleclick|adsense|amp[-_]?ad|"
    r"follow[-_]?us|social[-_]?links|share[-_]?this|"
    r"read[-_]?more[-_]?link|see[-_]?also|also[-_]?read|"
    r"donation|donate|patreon|ko[-_]?fi|buy[-_]?me|"
    r"app[-_]?download|play[-_]?store|app[-_]?store|download[-_]?app",
    re.I,
)

# Ad network image URL patterns
AD_IMAGE_PATTERNS = (
    "doubleclick", "googlesyndication", "googleads", "adservice",
    "facebook.com/tr", "pixel", "beacon", "track", "analytics",
    "scorecardresearch", "quantserve", "outbrain", "taboola",
)

# Video/social embed whitelist
EMBED_WHITELIST_RE = re.compile(
    r"youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|"
    r"twitter\.com|x\.com|instagram\.com|facebook\.com|fb\.watch|"
    r"tiktok\.com|rumble\.com|bitchute\.com|odysee\.com|"
    r"spotify\.com|soundcloud\.com|streamable\.com|"
    r"jwplatform\.com|brightcove|kaltura|vidyard|wistia|"
    r"twitch\.tv|reddit\.com|threads\.net|mastodon",
    re.I,
)


def clean_and_normalize(
    html: str,
    base_url: str,
    strip_selectors: str | None = None,
) -> str:
    """
    Full content cleaning and normalization pipeline.

    Takes raw extracted HTML and returns clean, normalized HTML ready for DB storage.
    Combines all cleaning that was previously split across Python and PHP.

    Args:
        html: Raw extracted HTML content
        base_url: Article URL for resolving relative URLs
        strip_selectors: Comma-separated CSS selectors to strip (from crawl_source config)

    Returns:
        Clean, normalized HTML string
    """
    if not html or not html.strip():
        return "<p>No content available.</p>"

    # ── Phase 1: Regex pre-cleaning (before DOM parsing) ──────

    # Strip inline ad patterns
    for pattern in INLINE_AD_PATTERNS:
        html = pattern.sub("", html)

    # Strip WordPress boilerplate
    for pattern in WP_PATTERNS:
        html = pattern.sub("", html)

    # ── Phase 2: DOM-based cleaning ───────────────────────────

    soup = BeautifulSoup(html, "lxml")

    # Strip unwanted tags entirely
    for tag_name in STRIP_TAGS:
        for el in soup.find_all(tag_name):
            el.decompose()

    # Strip elements by class/ID patterns
    to_remove = []
    for el in soup.find_all(True):
        if not hasattr(el, "attrs") or el.attrs is None:
            continue
        classes = " ".join(el.get("class", []))
        el_id = el.get("id", "")
        if STRIP_CLASSES_RE.search(classes) or STRIP_CLASSES_RE.search(el_id):
            to_remove.append(el)
    for el in to_remove:
        el.decompose()

    # Strip source-specific CSS selectors
    if strip_selectors:
        for selector in strip_selectors.split(","):
            selector = selector.strip()
            if not selector:
                continue
            try:
                for el in soup.select(selector):
                    el.decompose()
            except (ValueError, AttributeError, TypeError):
                pass

    # ── Phase 3: Image normalization ──────────────────────────

    for img in soup.find_all("img"):
        src = img.get("src", "") or ""

        # Fix lazy-loaded images FIRST
        is_placeholder = (
            not src
            or "data:" in src
            or "placeholder" in src
            or "1x1" in src
            or "blank" in src
        )
        if is_placeholder:
            for attr in ("data-src", "data-lazy-src", "data-original", "data-full-src"):
                if img.get(attr):
                    img["src"] = img[attr]
                    src = img[attr]
                    break

        # Remove tracking pixels
        w = str(img.get("width", ""))
        h = str(img.get("height", ""))
        if w in ("0", "1", "2") and h in ("0", "1", "2"):
            img.decompose()
            continue

        # Remove ad network images
        src_lower = src.lower()
        if any(ad in src_lower for ad in AD_IMAGE_PATTERNS):
            img.decompose()
            continue

        # Skip SVG data URIs
        if "data:image/svg" in src_lower:
            img.decompose()
            continue

        # Skip icons/logos/avatars
        if re.search(r"\b(pixel|track|beacon|spacer|blank|1x1)\b", src, re.I):
            img.decompose()
            continue

        # Normalize image attributes
        alt = img.get("alt", "")
        new_attrs = {"src": src, "alt": alt, "loading": "lazy", "referrerpolicy": "no-referrer"}
        # Preserve dimensions if present
        if img.get("width") and img["width"] not in ("0", "1", "2"):
            new_attrs["width"] = img["width"]
        if img.get("height") and img["height"] not in ("0", "1", "2"):
            new_attrs["height"] = img["height"]
        # Preserve srcset
        if img.get("srcset"):
            new_attrs["srcset"] = img["srcset"]

        img.attrs = new_attrs
        img["style"] = "max-width:100%;height:auto"

    # ── Phase 4: Strip dangerous attributes ───────────────────

    for tag in soup.find_all(True):
        if not hasattr(tag, "attrs") or tag.attrs is None:
            continue
        # Remove event handlers, data attrs, aria, role
        attrs_to_remove = []
        for attr_name in list(tag.attrs.keys()):
            if attr_name.startswith("on"):  # onclick, onmouseover, etc.
                attrs_to_remove.append(attr_name)
            elif attr_name.startswith("data-"):
                attrs_to_remove.append(attr_name)
            elif attr_name.startswith("aria-"):
                attrs_to_remove.append(attr_name)
            elif attr_name in ("role", "tabindex"):
                attrs_to_remove.append(attr_name)
        for attr_name in attrs_to_remove:
            del tag[attr_name]

        # Strip class and id (except on img which we already normalized)
        if tag.name != "img":
            tag.attrs.pop("class", None)
            tag.attrs.pop("id", None)

        # Strip inline styles (except on img, iframe, video, audio, figure)
        if tag.name not in ("img", "iframe", "video", "audio", "figure", "source"):
            tag.attrs.pop("style", None)

    # ── Phase 5: Demote h1 -> h2 ─────────────────────────────

    for h1 in soup.find_all("h1"):
        h1.name = "h2"

    # ── Phase 6: Handle iframes (video embeds vs junk) ────────

    for iframe in soup.find_all("iframe"):
        src = iframe.get("src", "")
        if EMBED_WHITELIST_RE.search(src):
            # Wrap in responsive container
            wrapper_div = soup.new_tag("div", style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%")
            wrapper_fig = soup.new_tag("figure")
            wrapper_fig["class"] = "embed-responsive"
            iframe.wrap(wrapper_div)
            wrapper_div.wrap(wrapper_fig)
        else:
            iframe.decompose()

    # ── Phase 7: Handle native video/audio ────────────────────

    for video in soup.find_all("video"):
        if not video.get("controls"):
            video["controls"] = ""
        video["style"] = "max-width:100%;height:auto"
        video["preload"] = "metadata"

    for audio in soup.find_all("audio"):
        if not audio.get("controls"):
            audio["controls"] = ""
        audio["style"] = "width:100%"
        audio["preload"] = "metadata"

    # ── Phase 8: Resolve relative URLs ────────────────────────

    for tag in soup.find_all(["a", "img", "source", "iframe"]):
        for attr in ("href", "src"):
            val = tag.get(attr)
            if val and not val.startswith(("http://", "https://", "data:", "mailto:", "#")):
                tag[attr] = urljoin(base_url, val)

    # ── Phase 9: Remove empty elements (3 passes) ────────────

    empty_tags = {"p", "div", "span", "li", "ul", "ol", "h2", "h3", "h4", "h5", "h6", "figure", "figcaption", "section"}
    media_tags = {"img", "iframe", "video", "audio", "svg"}

    for _ in range(3):
        for tag in soup.find_all(list(empty_tags)):
            if not tag.get_text(strip=True) and not tag.find(list(media_tags)):
                tag.decompose()

    # ── Phase 10: Extract body content ────────────────────────

    body = soup.find("body")
    if body:
        result = "".join(str(child) for child in body.children)
    else:
        result = str(soup)

    # Final whitespace cleanup
    result = re.sub(r"\n{3,}", "\n\n", result)
    result = result.strip()

    # If content is plain text (no tags), wrap in paragraph
    if result and result == BeautifulSoup(result, "lxml").get_text():
        result = "<p>" + result.replace("\n\n", "</p>\n<p>").replace("\n", "<br>") + "</p>"

    return result if result else "<p>No content available.</p>"
