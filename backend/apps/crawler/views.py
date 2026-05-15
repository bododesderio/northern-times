from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.http import JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from .models import CrawlLog, CrawlSource


@login_required
@role_required(2)
def source_list(request):
    """List all crawl sources with stats."""
    from apps.articles.models import Article
    from apps.core.models import Setting

    sources = CrawlSource.objects.select_related('category').order_by('name')
    today = timezone.now().date()

    # Annotate article counts per source
    for source in sources:
        source.article_count = Article.objects.filter(source_name=source.name).count()

    stats = {
        'total_sources': sources.count(),
        'active_sources': CrawlSource.objects.filter(is_active=True).count(),
    }
    today_stats = {
        'published_today': Article.objects.filter(
            status='published', published_at__date=today,
        ).count(),
        'crawls_today': CrawlLog.objects.filter(created_at__date=today).count(),
        'errors_today': CrawlLog.objects.filter(
            created_at__date=today, status='error',
        ).count(),
    }

    return render(request, 'admin/crawler/index.html', {
        'sources': sources,
        'stats': stats,
        'today': today_stats,
        'crawler_enabled': Setting.get('crawler_enabled', 'true') == 'true',
        'recent_logs': CrawlLog.objects.select_related('source').order_by('-created_at')[:5],
        'active_nav': 'crawler',
        'page_title': 'Crawler Sources',
    })


@login_required
@role_required(2)
def source_create(request):
    """Create a new crawl source."""
    if request.method == 'POST':
        CrawlSource.objects.create(
            name=request.POST.get('name', ''),
            url=request.POST.get('url', ''),
            feed_type=request.POST.get('feed_type', 'rss'),
            category_id=request.POST.get('category') or None,
            content_selector=request.POST.get('content_selector', ''),
            strip_selectors=request.POST.get('strip_selectors', ''),
            crawl_interval=int(request.POST.get('crawl_interval', 60)),
            region=request.POST.get('region', 'international'),
            auto_rewrite=bool(request.POST.get('auto_rewrite')),
            editorial_review=bool(request.POST.get('editorial_review')),
            download_images=bool(request.POST.get('download_images', True)),
        )
        return redirect('admin_crawler')
    from apps.articles.models import Category
    return render(request, 'admin/crawler/source_form.html', {
        'categories': Category.objects.all(),
        'active_nav': 'crawler',
        'page_title': 'Add Source',
    })


@login_required
@role_required(2)
def source_edit(request, pk):
    """Edit an existing crawl source."""
    source = get_object_or_404(CrawlSource, pk=pk)
    if request.method == 'POST':
        for field in ['name', 'url', 'feed_type', 'content_selector', 'strip_selectors', 'region', 'attribution_text']:
            setattr(source, field, request.POST.get(field, getattr(source, field)))
        source.category_id = request.POST.get('category') or None
        source.crawl_interval = int(request.POST.get('crawl_interval', source.crawl_interval))
        source.auto_rewrite = bool(request.POST.get('auto_rewrite'))
        source.editorial_review = bool(request.POST.get('editorial_review'))
        source.download_images = bool(request.POST.get('download_images'))
        source.is_active = bool(request.POST.get('is_active'))
        source.max_articles = int(request.POST.get('max_articles', source.max_articles))
        source.save()
        return redirect('admin_crawler')
    from apps.articles.models import Category
    return render(request, 'admin/crawler/source_form.html', {
        'source': source,
        'categories': Category.objects.all(),
        'active_nav': 'crawler',
        'page_title': 'Edit Source',
    })


@login_required
@role_required(2)
@require_POST
def source_delete(request, pk):
    """Delete a crawl source."""
    get_object_or_404(CrawlSource, pk=pk).delete()
    return redirect('admin_crawler')


@login_required
@role_required(2)
@require_POST
def crawl_now(request, pk):
    """Trigger immediate crawl for a source."""
    source = get_object_or_404(CrawlSource, pk=pk)
    from apps.crawler.tasks import run_crawler
    run_crawler.delay(source_id=str(source.pk))
    return redirect('admin_crawler')


@login_required
@role_required(2)
def logs(request):
    """View crawl logs with optional source filter."""
    logs_qs = CrawlLog.objects.select_related('source').order_by('-created_at')
    source_id = request.GET.get('source')
    if source_id:
        logs_qs = logs_qs.filter(source_id=source_id)
    paginator = Paginator(logs_qs, 30)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/crawler/logs.html', {
        'logs': page,
        'sources': CrawlSource.objects.all(),
        'source_id': source_id,
        'active_nav': 'crawler-logs',
        'page_title': 'Crawl Logs',
    })


@login_required
@role_required(2)
@require_POST
def crawl_all(request):
    """Trigger crawl for all active sources."""
    from apps.crawler.tasks import run_crawler
    run_crawler.delay()
    messages.success(request, 'Crawler started for all active sources.')
    return redirect('admin_crawler')


@login_required
@role_required(2)
def crawler_settings(request):
    """Global crawler configuration."""
    from apps.core.models import Setting
    if request.method == 'POST':
        Setting.set('crawler_fetch_timeout', request.POST.get('fetch_timeout', '15'))
        Setting.set('crawler_dedup_threshold', request.POST.get('dedup_threshold', '0.82'))
        Setting.set('crawler_download_images', request.POST.get('download_images', 'true'))
        Setting.set('crawler_max_articles_per_source', request.POST.get('max_articles', '50'))
        messages.success(request, 'Crawler settings saved.')
        return redirect('admin_crawler_settings')
    return render(request, 'admin/crawler/settings.html', {
        'fetch_timeout': Setting.get('crawler_fetch_timeout', '15'),
        'dedup_threshold': Setting.get('crawler_dedup_threshold', '0.82'),
        'download_images': Setting.get('crawler_download_images', 'true'),
        'max_articles': Setting.get('crawler_max_articles_per_source', '50'),
        'active_nav': 'crawler',
        'page_title': 'Crawler Settings',
    })


@login_required
@role_required(2)
def test_feed(request, pk):
    """Test/validate an RSS feed URL."""
    source = get_object_or_404(CrawlSource, pk=pk)
    results = []
    error = None
    try:
        import feedparser
        feed = feedparser.parse(source.url)
        for entry in feed.entries[:10]:
            results.append({
                'title': getattr(entry, 'title', 'No title'),
                'link': getattr(entry, 'link', ''),
                'published': getattr(entry, 'published', ''),
            })
    except Exception as e:
        error = str(e)
    return JsonResponse({'source': source.name, 'items': results, 'error': error})


@login_required
@role_required(2)
def robots_check(request, pk):
    """Check if source URL is allowed by robots.txt."""
    source = get_object_or_404(CrawlSource, pk=pk)
    from urllib.parse import urlparse
    from urllib.robotparser import RobotFileParser
    parsed = urlparse(source.url)
    robots_url = f"{parsed.scheme}://{parsed.netloc}/robots.txt"
    rp = RobotFileParser()
    try:
        rp.set_url(robots_url)
        rp.read()
        allowed = rp.can_fetch('*', source.url)
    except Exception:
        allowed = True  # If robots.txt unreachable, assume allowed
    return JsonResponse({'source': source.name, 'url': source.url, 'robots_url': robots_url, 'allowed': allowed})


@login_required
@role_required(2)
@require_POST
def archive_source_articles(request, pk):
    """Soft-delete all articles from a specific source."""
    source = get_object_or_404(CrawlSource, pk=pk)
    from apps.articles.models import Article
    count = Article.objects.filter(source_name=source.name, deleted_at__isnull=True).update(deleted_at=timezone.now())
    messages.success(request, f'{count} articles from "{source.name}" archived.')
    return redirect('admin_crawler')


@login_required
@role_required(2)
@require_POST
def restore_source_articles(request, pk):
    """Restore all soft-deleted articles from a specific source."""
    source = get_object_or_404(CrawlSource, pk=pk)
    from apps.articles.models import Article
    count = Article.objects.with_deleted().filter(
        source_name=source.name, deleted_at__isnull=False,
    ).update(deleted_at=None)
    messages.success(request, f'{count} articles from "{source.name}" restored.')
    return redirect('admin_crawler')


@login_required
@role_required(3)
@require_POST
def delete_source_articles(request, pk):
    """Permanently delete all articles from a specific source (irreversible)."""
    source = get_object_or_404(CrawlSource, pk=pk)
    from apps.articles.models import Article
    count = Article.objects.with_deleted().filter(source_name=source.name).delete()[0]
    messages.success(request, f'{count} articles from "{source.name}" permanently deleted.')
    return redirect('admin_crawler')


@login_required
@role_required(2)
def source_articles(request, pk):
    """View all articles from a specific source."""
    source = get_object_or_404(CrawlSource, pk=pk)
    from apps.articles.models import Article
    articles_qs = Article.objects.with_deleted().filter(
        source_name=source.name,
    ).order_by('-created_at')
    paginator = Paginator(articles_qs, 50)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/crawler/source_articles.html', {
        'source': source,
        'articles': page,
        'active_nav': 'crawler',
        'page_title': f'Articles from {source.name}',
    })


@login_required
@role_required(2)
def crawler_stats_api(request):
    """JSON stats for crawler dashboard."""
    total = CrawlSource.objects.count()
    active = CrawlSource.objects.filter(is_active=True).count()
    recent_logs = CrawlLog.objects.order_by('-created_at')[:10]
    stats = {
        'total_sources': total,
        'active_sources': active,
        'recent_crawls': [
            {'source': log.source.name if log.source else 'Unknown', 'status': log.status, 'new': log.new_count, 'dupes': log.duplicate_count, 'at': log.created_at.isoformat()}
            for log in recent_logs
        ],
    }
    return JsonResponse(stats)
