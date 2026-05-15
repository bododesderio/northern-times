import logging
import re
from datetime import timedelta

import httpx
from django.utils import timezone

logger = logging.getLogger(__name__)

PLACEHOLDER_URL = '/static/images/placeholder.jpg'
IMG_SRC_PATTERN = re.compile(r'<img[^>]+src=["\']([^"\']+)["\']', re.IGNORECASE)
HEALTH_CHECK_TIMEOUT = 10.0


def _extract_image_urls(html: str) -> list[str]:
    """Extract all image src URLs from HTML content."""
    if not html:
        return []
    return IMG_SRC_PATTERN.findall(html)


def check_article_images(article) -> list[dict]:
    """Check all images in an article's content via HEAD requests.

    Args:
        article: An Article model instance with a `content` attribute.

    Returns:
        List of dicts with keys: url, status_code, is_healthy.
    """
    urls = _extract_image_urls(article.content or '')
    if not urls:
        return []

    results = []
    with httpx.Client(
        timeout=httpx.Timeout(HEALTH_CHECK_TIMEOUT),
        follow_redirects=True,
        verify=True,
    ) as client:
        for url in urls:
            # Skip relative paths (local images) and data URIs
            if url.startswith('data:') or url == PLACEHOLDER_URL:
                results.append({
                    'url': url,
                    'status_code': None,
                    'is_healthy': True,
                })
                continue

            # Skip relative URLs that aren't full HTTP(S) links
            if not url.startswith(('http://', 'https://')):
                results.append({
                    'url': url,
                    'status_code': None,
                    'is_healthy': True,
                })
                continue

            try:
                response = client.head(url)
                is_healthy = 200 <= response.status_code < 400
                results.append({
                    'url': url,
                    'status_code': response.status_code,
                    'is_healthy': is_healthy,
                })
            except httpx.TimeoutException:
                results.append({
                    'url': url,
                    'status_code': None,
                    'is_healthy': False,
                })
            except Exception as e:
                logger.debug(f"Error checking image {url}: {e}")
                results.append({
                    'url': url,
                    'status_code': None,
                    'is_healthy': False,
                })

    return results


def check_all_articles(days: int = 7) -> dict:
    """Check images in all articles published within the last N days.

    Logs results to ImageHealthLog and replaces broken images with placeholder.

    Args:
        days: Number of days to look back for articles.

    Returns:
        Summary dict with total_articles, total_images, broken_count.
    """
    from apps.articles.models import Article
    from apps.system.models import ImageHealthLog

    cutoff = timezone.now() - timedelta(days=days)
    articles = Article.objects.filter(
        published_at__gte=cutoff,
        deleted_at__isnull=True,
    ).only('id', 'content')

    total_images = 0
    broken_count = 0

    for article in articles.iterator(chunk_size=100):
        results = check_article_images(article)
        if not results:
            continue

        content_modified = False
        current_content = article.content or ''

        for result in results:
            total_images += 1

            # Log to ImageHealthLog
            ImageHealthLog.objects.create(
                article=article,
                url=result['url'],
                status_code=result['status_code'],
                is_healthy=result['is_healthy'],
            )

            # Replace broken images with placeholder
            if not result['is_healthy'] and result['url'].startswith('http'):
                broken_count += 1
                current_content = current_content.replace(
                    result['url'], PLACEHOLDER_URL
                )
                content_modified = True
                logger.warning(
                    f"Broken image in article {article.id}: "
                    f"{result['url']} (status: {result['status_code']})"
                )

        if content_modified:
            article.content = current_content
            article.save(update_fields=['content'])

    summary = {
        'total_articles': articles.count(),
        'total_images': total_images,
        'broken_count': broken_count,
    }
    logger.info(
        f"Image health check complete: {summary['total_articles']} articles, "
        f"{total_images} images, {broken_count} broken"
    )
    return summary
