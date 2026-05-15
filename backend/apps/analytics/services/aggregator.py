import logging
from datetime import date, timedelta
from typing import Optional

from django.db.models import Count, Sum, Q
from django.db.models.functions import TruncDate
from django.utils import timezone

logger = logging.getLogger(__name__)


def aggregate_today() -> 'DailyStats':
    """Aggregate statistics for today and upsert into DailyStats."""
    return aggregate_date(timezone.localdate())


def aggregate_date(target_date: date) -> 'DailyStats':
    """Aggregate statistics for a specific date and upsert into DailyStats.

    Counts:
    - total_views: ArticleView records for the date
    - unique_visitors: distinct visitor IPs from SiteVisitor
    - new_articles: articles published on the date
    - new_comments: comments created on the date
    - new_subscribers: newsletter subscribers created on the date
    """
    from apps.analytics.models import ArticleView, DailyStats, SiteVisitor
    from apps.articles.models import Article
    from apps.newsletter.models import Subscriber

    day_start = timezone.datetime.combine(target_date, timezone.datetime.min.time())
    day_end = timezone.datetime.combine(target_date, timezone.datetime.max.time())
    if timezone.is_aware(timezone.now()):
        tz = timezone.get_current_timezone()
        day_start = timezone.make_aware(day_start, tz)
        day_end = timezone.make_aware(day_end, tz)

    total_views = ArticleView.objects.filter(
        viewed_at__range=(day_start, day_end),
    ).count()

    unique_visitors = SiteVisitor.objects.filter(
        visit_date=target_date,
    ).values('ip_address').distinct().count()

    new_articles = Article.objects.filter(
        created_at__range=(day_start, day_end),
    ).count()

    # Comments model may not exist yet — handle gracefully
    new_comments = _count_new_comments(day_start, day_end)

    new_subscribers = Subscriber.objects.filter(
        created_at__range=(day_start, day_end),
    ).count()

    stats, created = DailyStats.objects.update_or_create(
        date=target_date,
        defaults={
            'total_views': total_views,
            'unique_visitors': unique_visitors,
            'new_articles': new_articles,
            'new_comments': new_comments,
            'new_subscribers': new_subscribers,
        },
    )

    action = 'Created' if created else 'Updated'
    logger.info(
        '%s DailyStats for %s: views=%d visitors=%d articles=%d comments=%d subs=%d',
        action,
        target_date,
        total_views,
        unique_visitors,
        new_articles,
        new_comments,
        new_subscribers,
    )
    return stats


def get_dashboard_stats(days: int = 30) -> dict:
    """Return aggregated statistics for the admin dashboard.

    Returns:
        {
            'total_views': int,
            'unique_visitors': int,
            'top_articles': list[dict],   # top 10 by view count
            'views_by_day': list[dict],   # [{date, views}, ...]
        }
    """
    from apps.analytics.models import ArticleView, DailyStats

    end_date = timezone.localdate()
    start_date = end_date - timedelta(days=days - 1)

    # Totals from DailyStats
    totals = DailyStats.objects.filter(
        date__range=(start_date, end_date),
    ).aggregate(
        total_views=Sum('total_views'),
        unique_visitors=Sum('unique_visitors'),
    )

    # Views by day for chart
    views_by_day = list(
        DailyStats.objects
        .filter(date__range=(start_date, end_date))
        .order_by('date')
        .values_list('date', 'total_views')
    )

    # Top 10 articles by views in the period
    day_start = timezone.datetime.combine(start_date, timezone.datetime.min.time())
    if timezone.is_aware(timezone.now()):
        day_start = timezone.make_aware(day_start, timezone.get_current_timezone())

    top_articles = list(
        ArticleView.objects
        .filter(viewed_at__gte=day_start)
        .values('article_id', 'article__title')
        .annotate(view_count=Count('id'))
        .order_by('-view_count')[:10]
    )

    return {
        'total_views': totals['total_views'] or 0,
        'unique_visitors': totals['unique_visitors'] or 0,
        'top_articles': [
            {
                'article_id': str(a['article_id']),
                'title': a['article__title'],
                'views': a['view_count'],
            }
            for a in top_articles
        ],
        'views_by_day': [
            {'date': str(d), 'views': v}
            for d, v in views_by_day
        ],
    }


# ---------------------------------------------------------------------------
# Internal helpers
# ---------------------------------------------------------------------------

def _count_new_comments(day_start, day_end) -> int:
    """Count new comments, returning 0 if the Comment model doesn't exist yet."""
    try:
        from apps.articles.models import Comment
        return Comment.objects.filter(
            created_at__range=(day_start, day_end),
        ).count()
    except (ImportError, Exception):
        return 0
