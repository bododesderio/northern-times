import hashlib
import logging
import mimetypes
from pathlib import Path

import httpx
from django.conf import settings

logger = logging.getLogger(__name__)

# Supported image MIME types and their extensions
SUPPORTED_TYPES = {
    'image/jpeg': '.jpg',
    'image/png': '.png',
    'image/gif': '.gif',
    'image/webp': '.webp',
    'image/avif': '.avif',
    'image/heic': '.heic',
    'image/heif': '.heif',
    'image/tiff': '.tiff',
    'image/svg+xml': '.svg',
}

BOT_USER_AGENT = (
    'NorthernTimesBot/1.0 (+https://northerntimes.news/bot; '
    'image downloader)'
)


def _url_hash(url: str) -> str:
    """Generate a SHA-256 hash of the URL for use as a filename."""
    return hashlib.sha256(url.encode()).hexdigest()


def _extension_for_content_type(content_type: str, url: str) -> str | None:
    """Determine file extension from Content-Type header, falling back to URL."""
    # Strip charset and parameters
    mime = content_type.split(';')[0].strip().lower()
    ext = SUPPORTED_TYPES.get(mime)
    if ext:
        return ext

    # Fallback: guess from URL path
    guessed = mimetypes.guess_type(url.split('?')[0])[0]
    if guessed:
        return SUPPORTED_TYPES.get(guessed)

    return None


def download_image(url: str, folder: str = 'Articles') -> str | None:
    """Download a remote image and save it locally.

    Args:
        url: The remote image URL.
        folder: Subfolder within MEDIA_ROOT to save into (default 'Articles').

    Returns:
        Relative path like '/uploads/Articles/abc123.jpg' on success, None on failure.
    """
    if not url:
        return None

    url_hash = _url_hash(url)
    dest_dir = Path(settings.MEDIA_ROOT) / folder
    max_bytes = settings.MEDIA_UPLOAD_MAX_MB * 1024 * 1024

    # Check if already downloaded (any extension)
    for ext in SUPPORTED_TYPES.values():
        existing = dest_dir / f'{url_hash}{ext}'
        if existing.exists():
            media_url = settings.MEDIA_URL.rstrip('/')
            relative = f'{media_url}/{folder}/{url_hash}{ext}'
            logger.debug(f"Image already exists: {relative}")
            return relative

    try:
        with httpx.Client(
            timeout=httpx.Timeout(30.0, connect=10.0),
            follow_redirects=True,
            verify=True,
            headers={'User-Agent': BOT_USER_AGENT},
        ) as client:
            response = client.get(url)
            response.raise_for_status()

            # Validate content type
            content_type = response.headers.get('content-type', '')
            if not content_type.startswith('image/'):
                logger.warning(
                    f"Skipping non-image content-type '{content_type}' for {url}"
                )
                return None

            extension = _extension_for_content_type(content_type, url)
            if not extension:
                logger.warning(
                    f"Unsupported image type '{content_type}' for {url}"
                )
                return None

            # Check file size
            content = response.content
            if len(content) > max_bytes:
                logger.warning(
                    f"Image too large ({len(content)} bytes, max {max_bytes}) for {url}"
                )
                return None

            # Ensure destination directory exists
            dest_dir.mkdir(parents=True, exist_ok=True)

            filename = f'{url_hash}{extension}'
            dest_path = dest_dir / filename
            dest_path.write_bytes(content)

            media_url = settings.MEDIA_URL.rstrip('/')
            relative = f'{media_url}/{folder}/{filename}'
            logger.info(f"Downloaded image: {url} -> {relative}")
            return relative

    except httpx.TimeoutException:
        logger.warning(f"Timeout downloading image: {url}")
        return None
    except httpx.HTTPStatusError as e:
        logger.warning(f"HTTP {e.response.status_code} downloading image: {url}")
        return None
    except Exception as e:
        logger.error(f"Failed to download image {url}: {e}")
        return None
