import csv
from datetime import timedelta

from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.db import models
from django.db.models import Count, Sum
from django.http import HttpResponse, JsonResponse
from django.shortcuts import render
from django.utils import timezone

from .models import ArticleView, DailyStats, SiteVisitor


# ── Admin views ──────────────────────────────────────────────


@login_required
def analytics_index(request):
    """Analytics dashboard with charts and summary stats."""
    today = timezone.now().date()
    thirty_days_ago = today - timedelta(days=30)
    seven_days_ago = today - timedelta(days=7)

    daily = DailyStats.objects.filter(date__gte=thirty_days_ago).order_by('date')

    # Summary totals
    totals_30d = DailyStats.objects.filter(date__gte=thirty_days_ago).aggregate(
        views=Sum('total_views'),
        visitors=Sum('unique_visitors'),
        articles=Sum('new_articles'),
        subscribers=Sum('new_subscribers'),
    )
    totals_7d = DailyStats.objects.filter(date__gte=seven_days_ago).aggregate(
        views=Sum('total_views'),
        visitors=Sum('unique_visitors'),
    )

    # Real-time today stats (live from raw tables, not waiting for Celery aggregation)
    today_visitors = SiteVisitor.objects.filter(visit_date=today).count()
    today_views = ArticleView.objects.filter(viewed_at__date=today).count()

    # Merge today's live data into 30d totals if DailyStats hasn't aggregated yet
    if not DailyStats.objects.filter(date=today).exists():
        totals_30d['views'] = (totals_30d.get('views') or 0) + today_views
        totals_30d['visitors'] = (totals_30d.get('visitors') or 0) + today_visitors

    # Chart data
    chart_labels = [s.date.isoformat() for s in daily]
    chart_views = [s.total_views for s in daily]
    chart_visitors = [s.unique_visitors for s in daily]

    # Append today's live data to chart if not yet aggregated
    if not DailyStats.objects.filter(date=today).exists() and (today_views or today_visitors):
        chart_labels.append(today.isoformat())
        chart_views.append(today_views)
        chart_visitors.append(today_visitors)

    return render(request, 'admin/analytics/index.html', {
        'daily_stats': daily,
        'totals_30d': totals_30d,
        'totals_7d': totals_7d,
        'today_visitors': today_visitors,
        'today_views': today_views,
        'chart_labels': chart_labels,
        'chart_views': chart_views,
        'chart_visitors': chart_visitors,
        'active_nav': 'analytics',
        'page_title': 'Analytics',
    })


@login_required
def analytics_export(request):
    """Export daily stats as CSV within date range."""
    date_from = request.GET.get('from', '')
    date_to = request.GET.get('to', '')

    qs = DailyStats.objects.order_by('-date')
    if date_from:
        qs = qs.filter(date__gte=date_from)
    if date_to:
        qs = qs.filter(date__lte=date_to)

    response = HttpResponse(content_type='text/csv')
    response['Content-Disposition'] = 'attachment; filename="analytics_export.csv"'

    writer = csv.writer(response)
    writer.writerow(['date', 'total_views', 'unique_visitors', 'new_articles', 'new_comments', 'new_subscribers'])

    for stat in qs.iterator():
        writer.writerow([
            stat.date.isoformat(),
            stat.total_views,
            stat.unique_visitors,
            stat.new_articles,
            stat.new_comments,
            stat.new_subscribers,
        ])

    return response


@login_required
def performance_index(request):
    """Performance monitoring dashboard: page views, top articles."""
    today = timezone.now().date()
    seven_days_ago = today - timedelta(days=7)

    top_articles = (
        ArticleView.objects
        .filter(viewed_at__date__gte=seven_days_ago)
        .values('article__title', 'article__slug', 'article_id')
        .annotate(view_count=Count('id'))
        .order_by('-view_count')[:20]
    )

    recent_stats = DailyStats.objects.filter(date__gte=seven_days_ago).order_by('date')

    return render(request, 'admin/analytics/performance.html', {
        'top_articles': top_articles,
        'recent_stats': recent_stats,
        'active_nav': 'analytics',
        'page_title': 'Performance',
    })


@login_required
def engagement_index(request):
    """Engagement metrics: visitor breakdown, devices, geography."""
    today = timezone.now().date()
    thirty_days_ago = today - timedelta(days=30)

    visitors = SiteVisitor.objects.filter(visit_date__gte=thirty_days_ago)

    # Device breakdown (DB-level aggregation)
    device_qs = (
        visitors.values('device_type')
        .annotate(count=Count('id'))
        .order_by('-count')
    )
    device_counts = {(row['device_type'] or 'unknown'): row['count'] for row in device_qs}

    # Top countries (DB-level aggregation)
    country_qs = (
        visitors.filter(country__isnull=False)
        .exclude(country='')
        .values('country')
        .annotate(count=Count('id'))
        .order_by('-count')[:15]
    )
    top_countries = [(row['country'], row['count']) for row in country_qs]

    return render(request, 'admin/analytics/engagement.html', {
        'device_counts': device_counts,
        'top_countries': top_countries,
        'total_visitors': visitors.count(),
        'active_nav': 'analytics',
        'page_title': 'Engagement',
    })


# ── API views ────────────────────────────────────────────────


def ad_click(request, pk):
    """Track an ad click."""
    from apps.ads.models import AdSlot
    try:
        AdSlot.objects.filter(pk=pk).update(clicks=models.F('clicks') + 1)
    except Exception:
        pass
    return JsonResponse({'status': 'ok'})


def ad_impression(request):
    """Track ad impressions (batch)."""
    from apps.ads.models import AdSlot
    import json
    try:
        data = json.loads(request.body)
        slot_ids = data.get('slots', [])
        if slot_ids:
            AdSlot.objects.filter(pk__in=slot_ids).update(impressions=models.F('impressions') + 1)
    except Exception:
        pass
    return JsonResponse({'status': 'ok'})


@login_required
def visitor_location(request):
    """Return visitor location data for mapping."""
    today = timezone.now().date()
    thirty_days_ago = today - timedelta(days=30)

    locations = list(
        SiteVisitor.objects
        .filter(visit_date__gte=thirty_days_ago, latitude__isnull=False)
        .values('country', 'city', 'latitude', 'longitude')
        .distinct()[:200]
    )
    return JsonResponse({'locations': locations})


# ── Dashboard API views ─────────────────────────────────────


@login_required
def badges_api(request):
    """Achievement badges based on content metrics."""
    from apps.articles.models import Article, Comment
    from apps.newsletter.models import Subscriber

    badges = []
    article_count = Article.objects.filter(status='published').count()
    if article_count >= 100:
        badges.append({'name': 'Century', 'icon': 'trophy', 'desc': '100+ published articles'})
    if article_count >= 500:
        badges.append({'name': 'Newsroom', 'icon': 'star', 'desc': '500+ published articles'})
    if article_count >= 1000:
        badges.append({'name': 'Press Empire', 'icon': 'crown', 'desc': '1000+ published articles'})

    comment_count = Comment.objects.filter(status='approved').count()
    if comment_count >= 50:
        badges.append({'name': 'Engaged', 'icon': 'chat', 'desc': '50+ approved comments'})
    if comment_count >= 500:
        badges.append({'name': 'Community', 'icon': 'users', 'desc': '500+ approved comments'})

    sub_count = Subscriber.objects.filter(status='active').count()
    if sub_count >= 100:
        badges.append({'name': 'Broadcaster', 'icon': 'mail', 'desc': '100+ subscribers'})

    return JsonResponse({'badges': badges, 'stats': {
        'articles': article_count,
        'comments': comment_count,
        'subscribers': sub_count,
    }})


@login_required
def engagement_radar_api(request):
    """Engagement metrics for radar/spider chart."""
    from apps.articles.models import Article, Comment

    last_30 = timezone.now() - timedelta(days=30)

    total_views = ArticleView.objects.filter(viewed_at__gte=last_30).count()
    total_comments = Comment.objects.filter(created_at__gte=last_30).count()
    total_articles = Article.objects.filter(published_at__gte=last_30, status='published').count()
    total_visitors = SiteVisitor.objects.filter(visit_date__gte=last_30.date()).count()

    # Normalize to 0-100 scale (cap at reasonable values)
    def norm(val, cap):
        return min(100, int(val / cap * 100)) if cap > 0 else 0

    return JsonResponse({
        'labels': ['Views', 'Comments', 'Articles', 'Visitors'],
        'values': [
            norm(total_views, 10000),
            norm(total_comments, 500),
            norm(total_articles, 100),
            norm(total_visitors, 5000),
        ],
        'raw': {
            'views': total_views,
            'comments': total_comments,
            'articles': total_articles,
            'visitors': total_visitors,
        }
    })


@login_required
def traffic_chart_api(request):
    """Daily views/visitors for last 30 days for chart rendering."""
    try:
        days = min(int(request.GET.get('days', 30)), 365)
    except (ValueError, TypeError):
        days = 30
    stats = DailyStats.objects.order_by('-date')[:days]
    data = [{
        'date': s.date.isoformat(),
        'views': s.total_views,
        'visitors': s.unique_visitors,
    } for s in reversed(list(stats))]
    return JsonResponse({'data': data})


@login_required
def daily_stats_api(request):
    """Today's stats breakdown."""
    today = timezone.now().date()
    try:
        stats = DailyStats.objects.get(date=today)
        data = {
            'date': today.isoformat(),
            'total_views': stats.total_views,
            'unique_visitors': stats.unique_visitors,
            'new_articles': stats.new_articles,
            'new_comments': stats.new_comments,
            'new_subscribers': stats.new_subscribers,
        }
    except DailyStats.DoesNotExist:
        data = {
            'date': today.isoformat(),
            'total_views': 0,
            'unique_visitors': 0,
            'new_articles': 0,
            'new_comments': 0,
            'new_subscribers': 0,
        }
    return JsonResponse(data)
