from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.http import JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from apps.articles.models import Article
from apps.crawler.models import CrawlSource


@login_required
@role_required(2)
def rewriter_index(request):
    """Rewriter dashboard: list articles with rewrite status."""
    status_filter = request.GET.get('status', '')
    qs = Article.objects.select_related('category').filter(
        rewrite_status__isnull=False,
    ).order_by('-created_at')

    if status_filter:
        qs = qs.filter(rewrite_status=status_filter)

    paginator = Paginator(qs, 25)
    page = paginator.get_page(request.GET.get('page'))

    counts = {
        'queued': Article.objects.filter(rewrite_status='queued').count(),
        'processing': Article.objects.filter(rewrite_status='processing').count(),
        'completed': Article.objects.filter(rewrite_status='completed').count(),
        'approved': Article.objects.filter(rewrite_status='approved').count(),
        'rejected': Article.objects.filter(rewrite_status='rejected').count(),
        'failed': Article.objects.filter(rewrite_status='failed').count(),
    }

    from django.conf import settings as django_settings

    stats_cards = [
        {'bg': '#fff3e0', 'color': '#e65100', 'value': counts['queued'], 'label': 'Queued'},
        {'bg': '#e3f2fd', 'color': '#1565c0', 'value': counts['processing'], 'label': 'Processing'},
        {'bg': '#e8f5e9', 'color': '#2e7d32', 'value': counts['completed'], 'label': 'Completed'},
        {'bg': '#fce4ec', 'color': '#c62828', 'value': counts['failed'], 'label': 'Failed'},
        {'bg': '#f3e5f5', 'color': '#6a1b9a', 'value': counts['approved'], 'label': 'Approved'},
    ]

    return render(request, 'admin/rewriter/index.html', {
        'articles': page,
        'jobs': page,
        'current_status': status_filter,
        'counts': counts,
        'stats_cards': stats_cards,
        'rewriter_enabled': getattr(django_settings, 'REWRITER_ENABLED', False),
        'crawl_sources': CrawlSource.objects.filter(is_active=True).order_by('name'),
        'page': page.number,
        'total_pages': page.paginator.num_pages,
        'active_nav': 'rewriter',
        'page_title': 'AI Rewriter',
    })


@login_required
@role_required(2)
@require_POST
def rewriter_queue(request):
    """Batch-queue articles for rewriting."""
    article_ids = request.POST.getlist('article_ids')
    source_id = request.POST.get('source_id')

    if article_ids:
        Article.objects.filter(pk__in=article_ids).update(rewrite_status='queued')
        messages.success(request, f'{len(article_ids)} article(s) queued for rewriting.')
    elif source_id:
        count = Article.objects.filter(
            source_name=source_id,
            rewrite_status__isnull=True,
        ).update(rewrite_status='queued')
        messages.success(request, f'{count} article(s) from source queued for rewriting.')
    else:
        messages.warning(request, 'No articles selected.')

    return redirect('admin_rewriter')


@login_required
@role_required(2)
@require_POST
def rewriter_approve(request, pk):
    """Approve a rewritten article — copy rewritten fields to live fields."""
    article = get_object_or_404(Article, pk=pk)
    if article.rewrite_status == 'completed':
        if article.rewritten_title:
            article.title = article.rewritten_title
        if article.rewritten_content:
            article.content = article.rewritten_content
        if article.rewritten_excerpt:
            article.excerpt = article.rewritten_excerpt
        article.rewrite_status = 'approved'
        article.save()
        messages.success(request, f'Rewrite approved for "{article.title}".')
    else:
        messages.error(request, 'Only completed rewrites can be approved.')
    return redirect('admin_rewriter')


@login_required
@role_required(2)
@require_POST
def rewriter_reject(request, pk):
    """Reject a rewritten article — clear rewritten fields."""
    article = get_object_or_404(Article, pk=pk)
    article.rewrite_status = 'rejected'
    article.rewritten_title = ''
    article.rewritten_content = ''
    article.rewritten_excerpt = ''
    article.save()
    messages.success(request, f'Rewrite rejected for "{article.title}".')
    return redirect('admin_rewriter')


@login_required
@role_required(2)
def rewriter_settings(request):
    """Rewriter settings: toggle auto_rewrite per source."""
    sources = CrawlSource.objects.order_by('name')
    if request.method == 'POST':
        enabled_ids = request.POST.getlist('auto_rewrite_sources')
        CrawlSource.objects.all().update(auto_rewrite=False)
        if enabled_ids:
            CrawlSource.objects.filter(pk__in=enabled_ids).update(auto_rewrite=True)
        messages.success(request, 'Rewriter settings saved.')
        return redirect('admin_rewriter_settings')
    return render(request, 'admin/rewriter/settings.html', {
        'sources': sources,
        'active_nav': 'rewriter',
        'page_title': 'Rewriter Settings',
    })


@login_required
@role_required(2)
@require_POST
def rewriter_queue_bulk(request):
    """Queue all un-rewritten articles for rewriting."""
    count = Article.objects.filter(
        rewrite_status__isnull=True,
    ).update(rewrite_status='queued')
    messages.success(request, f'{count} article(s) queued for rewriting.')
    return redirect('admin_rewriter')


@login_required
@role_required(2)
def rewriter_review(request, pk):
    """Side-by-side review of original vs rewritten content."""
    article = get_object_or_404(Article, pk=pk)
    return render(request, 'admin/rewriter/review.html', {
        'article': article,
        'active_nav': 'rewriter',
        'page_title': f'Review: {article.title[:50]}',
    })


@login_required
@role_required(2)
@require_POST
def rewriter_retry(request, pk):
    """Retry a failed rewrite by resetting to queued."""
    article = get_object_or_404(Article, pk=pk)
    if article.rewrite_status == 'failed':
        article.rewrite_status = 'queued'
        article.save(update_fields=['rewrite_status'])
        messages.success(request, f'Article "{article.title}" re-queued for rewriting.')
    else:
        messages.error(request, 'Only failed rewrites can be retried.')
    return redirect('admin_rewriter')


@login_required
@role_required(2)
@require_POST
def rewriter_revert(request, pk):
    """Revert an approved rewrite back to original."""
    article = get_object_or_404(Article, pk=pk)
    if article.rewrite_status == 'approved':
        # Check if there's a revision to restore from
        revision = article.revisions.order_by('-created_at').first()
        if revision:
            article.title = revision.title
            article.content = revision.content
        article.rewrite_status = 'rejected'
        article.rewritten_title = ''
        article.rewritten_content = ''
        article.rewritten_excerpt = ''
        article.save()
        messages.success(request, f'Rewrite reverted for "{article.title}".')
    else:
        messages.error(request, 'Only approved rewrites can be reverted.')
    return redirect('admin_rewriter')


@login_required
@role_required(2)
def rewriter_status_api(request):
    """JSON API with rewrite queue counts by status."""
    counts = {}
    for status, label in Article.REWRITE_STATUS_CHOICES:
        counts[status] = Article.objects.filter(rewrite_status=status).count()
    counts['total'] = sum(counts.values())
    return JsonResponse(counts)
