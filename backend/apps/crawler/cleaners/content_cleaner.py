"""
Unified content cleaning and normalization pipeline.

11-phase DOM-based cleaner that strips ads, boilerplate, tracking,
dangerous attributes, and normalizes images/embeds/URLs.

Ported from docker/extractor/content_cleaner.py for use in the
Django crawler app.
"""

import re
import logging
from urllib.parse import urljoin

from bs4 import BeautifulSoup, Tag

logger = logging.getLogger(__name__)

# ── Phase 1: Inline ad patterns (regex, applied before DOM parsing) ──

INLINE_AD_PATTERNS = [
    # Ad insertion markers
    re.compile(
        r'<div[^>]*(?:class|id)=["\'][^"\']*(?:ad[-_]?insert|in[-_]?article[-_]?ad|'
        r'mid[-_]?article|inline[-_]?ad|native[-_]?ad|sponsored[-_]?content)[^"\']*["\'][^>]*>.*?</div>',
        re.I | re.S,
    ),
    # Google AdSense / DFP
    re.compile(r'<ins[^>]*class=["\']adsbygoogle["\'][^>]*>.*?</ins>', re.I | re.S),
    re.compile(r'<div[^>]*id=["\']div-gpt-ad[^"\']*["\'][^>]*>.*?</div>', re.I | re.S),
    # Outbrain / Taboola / mgid recommendation widgets
    re.compile(
        r'<div[^>]*class=["\'][^"\']*(?:OUTBRAIN|outbrain|taboola|mgid|revcontent|content-ad)'
        r'[^"\']*["\'][^>]*>.*?</div>',
        re.I | re.S,
    ),
    # Newsletter signup blocks
    re.compile(
        r'<(?:div|section|aside)[^>]*(?:class|id)=["\'][^"\']*(?:newsletter|subscribe|'
        r'email[-_]?signup|opt[-_]?in|mailing[-_]?list)[^"\']*["\'][^>]*>.*?</(?:div|section|aside)>',
        re.I | re.S,
    ),
    # "Follow us on..." blocks
    re.compile(
        r'<(?:div|p|section)[^>]*>(?:\s*<[^>]*>)*\s*(?:Follow|Like|Join)\s+(?:us\s+)?'
        r'(?:on|at)\s+(?:Twitter|Facebook|Instagram|TikTok|YouTube|X|Telegram|WhatsApp)'
        r'.*?</(?:div|p|section)>',
        re.I | re.S,
    ),
    # "Download our app" blocks
    re.compile(
        r'<(?:div|p)[^>]*>.*?(?:Download\s+(?:our|the)\s+app|Get\s+(?:it\s+)?on\s+'
        r'(?:Google\s+Play|App\s+Store)).*?</(?:div|p)>',
        re.I | re.S,
    ),
    # "Read also / See also" blocks
    re.compile(
        r'<(?:div|p|aside)[^>]*(?:class|id)=["\'][^"\']*(?:read[-_]?also|see[-_]?also|'
        r'also[-_]?read|related[-_]?link)[^"\']*["\'][^>]*>.*?</(?:div|p|aside)>',
        re.I | re.S,
    ),
    # Donation / support blocks
    re.compile(
        r'<(?:div|section)[^>]*(?:class|id)=["\'][^"\']*(?:donat|support[-_]?us|fundrais|'
        r'patron|ko[-_]?fi)[^"\']*["\'][^>]*>.*?</(?:div|section)>',
        re.I | re.S,
    ),
    # "Recommended Stories" / "More from..." — strip heading + everything after
    re.compile(
        r'<h2[^>]*>\s*(?:Recommended\s+Stories|More\s+(?:from|on|stories)|'
        r'Related\s+(?:Stories|Articles|Coverage)|You\s+(?:May|Might)\s+(?:Also|Like))'
        r'.*',
        re.I | re.S,
    ),
    # Al Jazeera "list of N items" + "list X of Y" text noise
    re.compile(r'list\s+of\s+\d+\s+items?\s*', re.I),
    re.compile(r'list\s+\d+\s+of\s+\d+', re.I),
    re.compile(r'end\s+of\s+list\s*', re.I),
]

# WordPress boilerplate patterns
WP_PATTERNS = [
    re.compile(
        r'<p[^>]*>\s*The post\s+<a[^>]*>.*?</a>\s+appeared first on\s+<a[^>]*>.*?</a>\.\s*</p>',
        re.I | re.S,
    ),
    re.compile(
        r'<p[^>]*>\s*The post\s+.{5,300}\s+appeared first on\s+.{3,100}\.\s*</p>',
        re.I | re.S,
    ),
    re.compile(r'<h[2-6][^>]*>\s*Share\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)', re.I | re.S),
    re.compile(r'<h[2-6][^>]*>\s*Like\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)', re.I | re.S),
    re.compile(r'<h[2-6][^>]*>\s*Related\s*:?\s*</h[2-6]>.*$', re.I | re.S),
    re.compile(
        r'<ul[^>]*>\s*(?:<li[^>]*>\s*<a[^>]*>Share\s+on\s+\w+[^<]*</a>\s*</li>\s*){2,}</ul>',
        re.I | re.S,
    ),
    re.compile(r'<a[^>]*>Share\s+on\s+\w+\s*\([^)]*\)\s*\w*</a>', re.I | re.S),
    re.compile(r'<p[^>]*>\s*Like\s+Loading\s*\.{0,3}\s*</p>', re.I | re.S),
    re.compile(r'<p[^>]*>\s*<a[^>]*>\s*See\s+also\s+[^<]+</a>\s*</p>', re.I | re.S),
    # Credit / photo credit lines
    re.compile(r'<p[^>]*>\s*(?:Credit|Photo\s+Credit|Image\s+Credit)\s*:\s*[^<]{3,120}\s*</p>', re.I | re.S),
    # Email contact blocks
    re.compile(
        r'<p[^>]*>.*?[\w.+-]+@[\w-]+\.[\w.]+.*?</p>',
        re.I | re.S,
    ),
    # "If you would like your article/opinion" CTAs
    re.compile(
        r'<p[^>]*>\s*If\s+you\s+would\s+like\s+(?:your|to\s+submit|to\s+have).*?</p>',
        re.I | re.S,
    ),
    # WhatsApp / Telegram follow prompts
    re.compile(
        r'<p[^>]*>.*?(?:Join\s+(?:our|us\s+on)|Follow\s+us\s+on)\s+(?:WhatsApp|Telegram).*?</p>',
        re.I | re.S,
    ),
    # "Download our app" / "Click here" / "Read more at" prompts
    re.compile(
        r'<p[^>]*>.*?(?:Download\s+our\s+app|Click\s+here\s+to|Read\s+more\s+at).*?</p>',
        re.I | re.S,
    ),
    # "Source: / Via:" attribution lines
    re.compile(
        r'<p[^>]*>\s*(?:Source|Via)\s*:\s*(?:<a[^>]*>)?[^<]{3,100}(?:</a>)?\s*</p>',
        re.I | re.S,
    ),
]

# ── Phase 2: Tags to completely remove ───────────────────────────

STRIP_TAGS = {
    "script", "style", "noscript", "svg", "form", "input", "button",
    "select", "textarea", "nav", "header", "footer", "aside",
}

# ── Phase 3: Class/ID patterns indicating junk elements (82 patterns) ──

STRIP_CLASSES_RE = re.compile(
    r"sidebar|widget|ads?[-_]|ad[-_]?container|ad[-_]?wrapper|ad[-_]?slot|"
    r"social[-_]?share|sharing|share[-_]?button|share[-_]?bar|social[-_]?media|"
    r"related[-_]?posts?|related[-_]?articles?|more[-_]?stories|recommended|"
    r"comments?|comment[-_]?section|disqus|"
    r"newsletter|subscribe|signup|sign[-_]?up|opt[-_]?in|email[-_]?capture|"
    r"breadcrumb|author[-_]?box|author[-_]?bio|author[-_]?card|author[-_]?info|byline[-_]?box|"
    r"popup|modal|overlay|cookie[-_]?banner|cookie[-_]?consent|"
    r"promo|promoti|advertis|sponsor|paid[-_]?content|native[-_]?ad|"
    r"outbrain|taboola|mgid|revcontent|content[-_]?ad|"
    r"breaking[-_]?news[-_]?ticker|ticker|marquee|"
    r"google[-_]?ad|doubleclick|adsense|amp[-_]?ad|"
    r"follow[-_]?us|social[-_]?links|share[-_]?this|addtoany|addthis|sharedaddy|"
    r"read[-_]?more[-_]?link|see[-_]?also|also[-_]?read|yarpp|jp[-_]?relatedposts|"
    r"donation|donate|patreon|ko[-_]?fi|buy[-_]?me|"
    r"app[-_]?download|play[-_]?store|app[-_]?store|download[-_]?app|"
    r"post[-_]?tags|entry[-_]?tags|tag[-_]?list|post[-_]?navigation|nav[-_]?links|"
    r"wp[-_]?caption(?!\s)|gallery[-_]?caption|heateor|ssba|sd[-_]?sharing|"
    r"jetpack|likes[-_]?widget|sd[-_]?like|sd[-_]?content|sd[-_]?block",
    re.I,
)

# ── Phase 5: Ad network image URL patterns ───────────────────────

AD_IMAGE_PATTERNS = (
    "doubleclick", "googlesyndication", "googleads", "adservice",
    "facebook.com/tr", "pixel", "beacon", "track", "analytics",
    "scorecardresearch", "quantserve", "outbrain", "taboola",
)

# ── Phase 8: Video/social embed whitelist ─────────────────────────

EMBED_WHITELIST_RE = re.compile(
    r"youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|"
    r"twitter\.com|x\.com|instagram\.com|facebook\.com|fb\.watch|"
    r"tiktok\.com|rumble\.com|bitchute\.com|odysee\.com|"
    r"spotify\.com|soundcloud\.com|streamable\.com|"
    r"jwplatform\.com|brightcove|kaltura|vidyard|wistia|"
    r"twitch\.tv|reddit\.com|threads\.net|mastodon",
    re.I,
)

# ── Phase 9 helper: Junk anchor link text patterns ────────────────

JUNK_LINK_RE = re.compile(
    r"(?:share|tweet|whatsapp|telegram|linkedin|pinterest|reddit|email\s+this|"
    r"print\s+this|copy\s+link|subscribe|sign\s+up|log\s+in|register|download\s+app|"
    r"follow\s+us|join\s+us|support\s+us|donate|advertise|contact\s+us)",
    re.I,
)

# Safe URL schemes
_SAFE_SCHEMES = ("http://", "https://", "data:", "mailto:", "#")
_SAFE_SCHEMES_RESOLVE = ("http://", "https://", "mailto:")

_TRACKING_IMG_RE = re.compile(r"\b(pixel|track|beacon|spacer|1x1)\b", re.I)


def _looks_like_content_img(img) -> bool:
    """True if an <img> is a real content image (has a usable, non-tracking src).

    Used to rescue legitimate images that happen to sit inside a wrapper whose
    class matches the junk regex (e.g. WordPress ``wp-caption`` / ``wp-block-image``
    figures), so they aren't discarded with the wrapper.
    """
    src = (
        img.get("src") or img.get("data-src")
        or img.get("data-lazy-src") or img.get("data-original") or ""
    )
    if not src:
        return False
    low = src.lower()
    if any(ad in low for ad in AD_IMAGE_PATTERNS):
        return False
    if "data:image/svg" in low or _TRACKING_IMG_RE.search(low):
        return False
    return True


def clean_content(html: str, base_url: str = '', strip_selectors: str = '') -> str:
    """
    Full 11-phase content cleaning and normalization pipeline.

    Takes raw extracted HTML and returns clean, normalized HTML ready for
    database storage. Combines all cleaning that was previously split
    across Python extractor and PHP CrawlerEngine.

    Args:
        html: Raw extracted HTML content.
        base_url: Article URL for resolving relative URLs.
        strip_selectors: Comma-separated CSS selectors to strip (from crawl_source config).

    Returns:
        Clean, normalized HTML string.
    """
    if not html or not html.strip():
        return "<p>No content available.</p>"

    # ── Phase 1: Regex pre-cleaning (before DOM parsing) ─────────

    # Strip inline ad patterns
    for pattern in INLINE_AD_PATTERNS:
        html = pattern.sub("", html)

    # Strip WordPress boilerplate
    for pattern in WP_PATTERNS:
        html = pattern.sub("", html)

    # ── Phase 2: DOM-based tag stripping ─────────────────────────

    soup = BeautifulSoup(html, "lxml")

    for tag_name in STRIP_TAGS:
        for el in soup.find_all(tag_name):
            el.decompose()

    # ── Phase 3: Class/ID pattern removal ────────────────────────

    to_remove = []
    for el in soup.find_all(True):
        if not hasattr(el, "attrs") or el.attrs is None:
            continue
        classes = " ".join(el.get("class", []))
        el_id = el.get("id", "")
        if STRIP_CLASSES_RE.search(classes) or STRIP_CLASSES_RE.search(el_id):
            to_remove.append(el)
    for el in to_remove:
        if getattr(el, "decomposed", False) or el.parent is None:
            continue  # already removed as part of an ancestor
        # Rescue legitimate content images before dropping a junk wrapper — many
        # inline article images live inside figure/wp-caption/gallery containers
        # whose class matches the strip regex.
        content_imgs = [im for im in el.find_all("img") if _looks_like_content_img(im)]
        if content_imgs:
            fig = soup.new_tag("figure")
            for im in content_imgs:
                fig.append(im.extract())
            el.replace_with(fig)
        else:
            el.decompose()

    # ── Phase 4: Source-specific CSS stripping ───────────────────

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

    # ── Phase 4b: Byline removal ──────────────────────────────────
    # Strip "By [Author Name]" patterns from the start of article content.
    # These leak the original author's name into the body text.

    BYLINE_RE = re.compile(
        r'^\s*(?:By|BY|Written\s+by|Reported\s+by|Story\s+by)\s+'
        r'[A-Z][a-zA-Z\'\-]+(?:\s+[A-Z][a-zA-Z\'\-]+){0,4}'
        r'(?:\s*[,|]\s*[A-Za-z\s,]+)?\s*$',
        re.M,
    )

    # Check first few block elements for byline patterns
    for tag in list(soup.find_all(['p', 'div', 'span'], limit=5)):
        text = tag.get_text(strip=True)
        if not text or len(text) > 200:
            break
        if BYLINE_RE.match(text):
            tag.decompose()
            continue
        # Also catch standalone "By Name" as first line (not in a tag)
        if text and not text[0].islower():
            break  # Stop scanning once we hit real content

    # ── Phase 5: Image normalization ─────────────────────────────

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

        # Remove tracking pixels (1x1 or 0x0)
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

        # Skip tracking pixels by name pattern
        if re.search(r"\b(pixel|track|beacon|spacer|blank|1x1)\b", src, re.I):
            img.decompose()
            continue

        # Normalize image attributes — keep only safe ones
        alt = img.get("alt", "")
        new_attrs: dict = {
            "src": src,
            "alt": alt,
            "loading": "lazy",
            "referrerpolicy": "no-referrer",
        }
        # Preserve dimensions if present and meaningful
        if img.get("width") and img["width"] not in ("0", "1", "2"):
            new_attrs["width"] = img["width"]
        if img.get("height") and img["height"] not in ("0", "1", "2"):
            new_attrs["height"] = img["height"]
        # Preserve srcset
        if img.get("srcset"):
            new_attrs["srcset"] = img["srcset"]

        img.attrs = new_attrs
        img["style"] = "max-width:100%;height:auto"
        img["crossorigin"] = "anonymous"

    # ── Phase 6: Dangerous attribute stripping ───────────────────

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

        # Strip inline styles (except on media elements)
        if tag.name not in ("img", "iframe", "video", "audio", "figure", "source"):
            tag.attrs.pop("style", None)

    # ── Phase 7: H1 demotion (H1 -> H2) ─────────────────────────

    for h1 in soup.find_all("h1"):
        h1.name = "h2"

    # ── Phase 8: iframe handling (whitelist embeds, remove others) ─

    for iframe in soup.find_all("iframe"):
        src = iframe.get("src", "")
        if EMBED_WHITELIST_RE.search(src):
            # Wrap in responsive container
            wrapper_div = soup.new_tag(
                "div",
                style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%",
            )
            wrapper_fig = soup.new_tag("figure")
            wrapper_fig["class"] = "embed-responsive"
            iframe.wrap(wrapper_div)
            wrapper_div.wrap(wrapper_fig)
        else:
            iframe.decompose()

    # ── Phase 9: Native video/audio (add controls + preload) ─────

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

    # ── Phase 10: URL resolution (relative -> absolute) ──────────

    for tag in soup.find_all(["a", "img", "source", "iframe"]):
        for attr in ("href", "src"):
            val = tag.get(attr)
            if not val:
                continue
            # Block data: URIs on <a> href (XSS vector) — only allow on img/source src
            if val.startswith("data:") and (tag.name == "a" or attr == "href"):
                del tag[attr]
                continue
            if not val.startswith(_SAFE_SCHEMES):
                if base_url:
                    resolved = urljoin(base_url, val)
                    # Only keep URLs with safe protocols
                    if resolved.startswith(_SAFE_SCHEMES_RESOLVE) or (
                        resolved.startswith("data:") and tag.name != "a"
                    ):
                        tag[attr] = resolved
                    else:
                        del tag[attr]
                else:
                    # No base URL — can't resolve, remove unsafe
                    del tag[attr]

    # Strip junk anchor links (share/social/promo, not editorial)
    for a in soup.find_all("a"):
        text = a.get_text(strip=True)
        href = a.get("href", "")
        # Block javascript: and vbscript: URLs
        if href.strip().lower().startswith(("javascript:", "vbscript:")):
            a["href"] = "#"
        # Remove share/social links
        if JUNK_LINK_RE.search(text):
            a.decompose()
            continue
        # Remove links that are just icons (no text, no meaningful content)
        if not text and not a.find("img"):
            a.decompose()
            continue

    # ── Phase 11: Empty element removal (3 passes) ───────────────

    empty_tags = {
        "p", "div", "span", "li", "ul", "ol",
        "h2", "h3", "h4", "h5", "h6",
        "figure", "figcaption", "section",
    }
    media_tags = {"img", "iframe", "video", "audio", "svg"}

    for _ in range(3):
        for tag in soup.find_all(list(empty_tags)):
            if not tag.get_text(strip=True) and not tag.find(list(media_tags)):
                tag.decompose()

    # ── Extract body content ─────────────────────────────────────

    body = soup.find("body")
    if body:
        result = "".join(str(child) for child in body.children)
    else:
        result = str(soup)

    # Final whitespace cleanup
    result = re.sub(r"\n{3,}", "\n\n", result)
    result = result.strip()

    # If content is plain text (no tags), wrap in paragraph tags
    if result and result == BeautifulSoup(result, "lxml").get_text():
        result = "<p>" + result.replace("\n\n", "</p>\n<p>").replace("\n", "<br>") + "</p>"

    return result if result else "<p>No content available.</p>"
