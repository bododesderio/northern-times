import re

from django import template
from django.utils import timezone
from django.utils.html import format_html
from django.utils.safestring import mark_safe

from apps.core.models import Setting

register = template.Library()


# ---------------------------------------------------------------------------
# Template tags
# ---------------------------------------------------------------------------

@register.simple_tag
def site_setting(key, default=''):
    """Render a site setting value: {% site_setting 'key' 'default' %}"""
    return Setting.get(key, default)


@register.simple_tag
def theme_css():
    """Emit <style id="nt-theme"> with CSS custom properties from site settings."""

    def san(v):
        v = re.sub(r'<[^>]*>', '', v)
        v = re.sub(r'expression\s*\(', '', v, flags=re.IGNORECASE)
        return v.strip()

    def c(key, default):
        return san(str(Setting.get(key, default)))

    # Theme mode
    mode = c('theme_mode', 'light')
    if mode == 'auto':
        mode = 'system'
    if mode not in ('light', 'dark', 'system'):
        mode = 'light'

    # Light tokens
    ink = c('theme_ink', '#121212')
    paper = c('theme_paper', '#fdfdfd')
    surface = c('theme_surface', '#ffffff')
    border = c('theme_border', '#e2e2e2')
    muted = c('theme_muted', '#666666')
    accent = c('theme_accent', '#cc0000')
    accent_dark = c('theme_accent_dark', '#aa0000')
    accent_text = c('theme_accent_text', '#ffffff')
    mast_color = c('theme_mast_color', '')
    selection = c('theme_selection_bg', 'rgba(204,0,0,.12)')

    font_serif = c('theme_font_serif', 'Georgia, "Times New Roman", Times, serif')
    font_ui = c('theme_font_ui', '"Libre Franklin", system-ui, sans-serif')
    font_mast = c('theme_font_mast', '"UnifrakturMaguntia", Georgia, serif')
    font_base = c('theme_font_base', '18px')
    font_article = c('theme_font_article', '21px')
    line_height = c('theme_line_height', '1.7')

    mast_color_val = mast_color or 'var(--accent)'
    radius = c('theme_radius', '16px')
    max_width = c('theme_max_width', '1180px')
    content_max = c('theme_content_max', '1000px')
    card_pad = c('theme_card_pad', '20px')

    speed = c('theme_speed', '0.18s')
    speed_slow = c('theme_speed_slow', '0.35s')
    ease = c('theme_ease', 'cubic-bezier(.2,.8,.2,1)')

    # Dark tokens
    dark_ink = c('theme_ink_dark', '#e0e0e0')
    dark_paper = c('theme_paper_dark', '#1a1a1a')
    dark_surface = c('theme_surface_dark', '#242424')
    dark_border = c('theme_border_dark', '#3a3a3a')
    dark_muted = c('theme_muted_dark', '#999999')
    dark_sel_bg = c('theme_selection_bg_dark', 'rgba(204,0,0,.22)')

    root = (
        f':root {{\n'
        f'  --ink: {ink};\n  --paper: {paper};\n  --surface: {surface};\n'
        f'  --border: {border};\n  --muted: {muted};\n'
        f'  --accent: {accent};\n  --accent-dark: {accent_dark};\n'
        f'  --accent-text: {accent_text};\n  --selection-bg: {selection};\n'
        f'  --mast-color: {mast_color_val};\n\n'
        f'  --serif: {font_serif};\n  --ui: {font_ui};\n  --mast: {font_mast};\n'
        f'  --font-base: {font_base};\n  --font-article: {font_article};\n'
        f'  --line-height: {line_height};\n\n'
        f'  --radius: {radius};\n  --max: {max_width};\n'
        f'  --content-max: {content_max};\n  --card-pad: {card_pad};\n\n'
        f'  --speed: {speed};\n  --speed-slow: {speed_slow};\n  --ease: {ease};\n\n'
        f'  color-scheme: light;\n}}\n'
        f'::selection {{ background: {selection}; }}'
    )

    dark_vars = (
        f'  --ink: {dark_ink};\n  --paper: {dark_paper};\n'
        f'  --surface: {dark_surface};\n  --border: {dark_border};\n'
        f'  --muted: {dark_muted};\n  --selection-bg: {dark_sel_bg};\n'
        f'  color-scheme: dark;'
    )
    dark_extra = (
        f'::selection {{ background: {dark_sel_bg}; }}\n'
        f'img {{ filter: brightness(.95) contrast(1.05); }}'
    )

    dark_block = ''
    if mode == 'dark':
        dark_block = f'\n:root {{\n{dark_vars}\n}}\n{dark_extra}'
    elif mode == 'system':
        dark_block = (
            f'\n@media (prefers-color-scheme: dark) {{\n'
            f'  :root {{\n  {dark_vars}\n  }}\n  {dark_extra}\n}}'
        )

    return mark_safe(f'<style id="nt-theme">\n{root}{dark_block}\n</style>')


@register.simple_tag
def share_buttons(url='', title='', excerpt=''):
    """Render social share buttons: {% share_buttons url title excerpt %}"""
    from django.utils.http import urlencode as ue
    enc_url = url or ''
    enc_title = title or ''
    tw = f'https://twitter.com/intent/tweet?url={enc_url}&text={enc_title}'
    fb = f'https://www.facebook.com/sharer/sharer.php?u={enc_url}'
    wa = f'https://wa.me/?text={enc_title}%20{enc_url}'
    li = f'https://www.linkedin.com/sharing/share-offsite/?url={enc_url}'
    em = f'mailto:?subject={enc_title}&body={enc_url}'
    return mark_safe(
        f'<div class="np-share-icons">'
        f'<a href="{tw}" target="_blank" rel="noopener" class="np-share-icon-btn" aria-label="Share on X" data-tip="Share on X">'
        f'<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>'
        f'<a href="{fb}" target="_blank" rel="noopener" class="np-share-icon-btn" aria-label="Share on Facebook" data-tip="Facebook">'
        f'<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>'
        f'<a href="{wa}" target="_blank" rel="noopener" class="np-share-icon-btn" aria-label="Share on WhatsApp" data-tip="WhatsApp">'
        f'<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>'
        f'<a href="{li}" target="_blank" rel="noopener" class="np-share-icon-btn" aria-label="Share on LinkedIn" data-tip="LinkedIn">'
        f'<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>'
        f'<a href="{em}" class="np-share-icon-btn" aria-label="Share via email" data-tip="Email">'
        f'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></a>'
        f'</div>'
    )


@register.simple_tag
def render_ad(ads, slot_name, css_class=''):
    """Render an ad slot: {% render_ad ads 'slot_name' 'css_class' %}"""
    if not ads:
        return ''
    ad = None
    if isinstance(ads, dict):
        ad = ads.get(slot_name)
    elif hasattr(ads, 'filter'):
        # QuerySet — find the matching slot
        try:
            ad = ads.filter(slot_name=slot_name).first()
        except Exception:
            return ''
    if ad is None:
        return ''
    extra_class = f' {css_class}' if css_class else ''
    return mark_safe(
        f'<div class="ad-slot ad-slot--{slot_name}{extra_class}" '
        f'data-ad-id="{ad.id}">{ad.html_content}</div>'
    )


# ---------------------------------------------------------------------------
# Filters
# ---------------------------------------------------------------------------

@register.filter
def startswith(value, arg):
    """Check if a string starts with the given prefix: {{ value|startswith:'prefix' }}"""
    try:
        return str(value).startswith(str(arg))
    except (TypeError, AttributeError):
        return False


@register.filter
def relative_time(value):
    """Return a human-readable relative timestamp: {{ value|relative_time }}"""
    if value is None:
        return ''

    now = timezone.now()
    try:
        diff = now - value
    except TypeError:
        return str(value)

    seconds = int(diff.total_seconds())
    if seconds < 0:
        return 'just now'
    if seconds < 60:
        return 'just now'
    minutes = seconds // 60
    if minutes < 60:
        return f'{minutes}m ago'
    hours = minutes // 60
    if hours < 24:
        return f'{hours}h ago'
    days = hours // 24
    if days < 7:
        return f'{days}d ago'
    weeks = days // 7
    if weeks < 5:
        return f'{weeks}w ago'
    months = days // 30
    if months < 12:
        return f'{months}mo ago'
    years = days // 365
    return f'{years}y ago'


@register.filter
def thumbnail_url(image_url):
    """Return a thumbnail-sized variant of an image URL.

    If the URL points to an uploaded media file, insert '_thumb' before the
    extension.  For external URLs just return as-is (the original will be
    used; CSS handles sizing).
    """
    if not image_url:
        return ''
    url = str(image_url)
    # Only transform local /media/ uploads
    if '/media/' in url:
        dot = url.rfind('.')
        if dot != -1:
            return f'{url[:dot]}_thumb{url[dot:]}'
    return url
