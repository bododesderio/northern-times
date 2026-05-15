import json
from datetime import timedelta

from django.contrib.auth.decorators import login_required
from django.db.models import Count, Q, Sum
from django.http import JsonResponse, HttpResponse
from django.shortcuts import render, get_object_or_404, redirect
from django.utils import timezone
from django.utils.text import slugify
from django.views.decorators.http import require_POST
from django.core.paginator import Paginator

from django.contrib import messages

from apps.accounts.decorators import role_required
from apps.articles.models import Article, Category, Tag, Comment, ArticleRevision
from apps.analytics.models import ArticleView, DailyStats, SiteVisitor
from apps.newsletter.models import Subscriber
from apps.system.models import Notification


@login_required
def admin_dashboard(request):
    """Main admin dashboard with stats, charts, recent activity."""
    now = timezone.now()
    today = now.date()
    week_ago = now - timedelta(days=7)
    month_ago = now - timedelta(days=30)

    # Quick stats
    total_articles = Article.objects.count()
    published_articles = Article.objects.published().count()
    draft_articles = Article.objects.filter(status='draft').count()
    pending_review = Article.objects.filter(status='pending_review').count()

    today_views = ArticleView.objects.filter(viewed_at__date=today).count()
    total_subscribers = Subscriber.objects.filter(status='active').count()
    pending_comments = Comment.objects.filter(status='pending').count()
    unread_notifications = Notification.objects.filter(user=request.user, is_read=False).count()

    # Recent articles
    recent_articles = Article.objects.select_related('category', 'author').order_by('-created_at')[:10]

    # Daily stats for chart (last 30 days)
    daily_stats = list(
        DailyStats.objects.filter(date__gte=month_ago.date())
        .order_by('date')
        .values('date', 'total_views', 'unique_visitors')
    )

    # Time-based greeting
    hour = timezone.localtime(now).hour
    if hour < 12:
        greeting = 'Good morning'
    elif hour < 17:
        greeting = 'Good afternoon'
    else:
        greeting = 'Good evening'

    # Recent visitor locations for live map
    recent_visitors = list(
        SiteVisitor.objects.filter(
            created_at__gte=now - timedelta(hours=24),
            latitude__isnull=False,
            longitude__isnull=False,
        ).values('latitude', 'longitude', 'city', 'country', 'created_at')
        .order_by('-created_at')[:200]
    )

    context = {
        'greeting': greeting,
        'active_nav': 'dashboard',
        'page_title': 'Dashboard',
        'total_articles': total_articles,
        'published_articles': published_articles,
        'draft_articles': draft_articles,
        'pending_review': pending_review,
        'today_views': today_views,
        'total_subscribers': total_subscribers,
        'pending_comments': pending_comments,
        'unread_notifications': unread_notifications,
        'recent_articles': recent_articles,
        'daily_stats_json': json.dumps(daily_stats, default=str),
        'visitor_locations_json': json.dumps(recent_visitors, default=str),
    }
    return render(request, 'admin/dashboard.html', context)


@login_required
def article_list(request):
    """Admin article listing with filters."""
    articles = Article.objects.with_deleted().select_related('category', 'author')

    # Filters
    status = request.GET.get('status')
    if status:
        articles = articles.filter(status=status)

    category_id = request.GET.get('category')
    if category_id:
        articles = articles.filter(category_id=category_id)

    q = request.GET.get('q', '').strip()
    if q:
        articles = articles.filter(Q(title__icontains=q) | Q(content__icontains=q))

    articles = articles.order_by('-created_at')
    paginator = Paginator(articles, 20)
    page = paginator.get_page(request.GET.get('page'))

    categories = Category.objects.all()

    context = {
        'active_nav': 'articles',
        'page_title': 'Articles',
        'articles': page,
        'categories': categories,
        'current_status': status,
        'current_category': category_id,
        'query': q,
    }
    return render(request, 'admin/articles.html', context)


@login_required
def article_create(request):
    """Create new article form."""
    if request.method == 'POST':
        slug = slugify(request.POST.get('title', ''))[:300]
        # Ensure unique slug
        base_slug = slug
        counter = 1
        while Article.objects.filter(slug=slug).exists():
            slug = f"{base_slug}-{counter}"
            counter += 1

        article = Article(
            title=request.POST.get('title', ''),
            slug=slug,
            excerpt=request.POST.get('excerpt', ''),
            content=request.POST.get('content', ''),
            featured_image=request.POST.get('featured_image', ''),
            status=request.POST.get('status', 'draft'),
            category_id=request.POST.get('category_id') or None,
            author=request.user,
            display_author=request.POST.get('display_author', ''),
        )
        if article.status == 'scheduled':
            article.published_at = request.POST.get('published_at')
        elif article.status == 'published':
            article.published_at = timezone.now()
        article.save()

        # Handle tags
        tag_names = request.POST.get('tags', '').split(',')
        for name in tag_names:
            name = name.strip()
            if name:
                tag_obj, _ = Tag.objects.get_or_create(name=name, defaults={'slug': slugify(name)})
                article.tags.add(tag_obj)

        return redirect('admin_articles')

    categories = Category.objects.all()
    is_editor = hasattr(request.user, 'role') and request.user.role and request.user.role.level >= 2
    return render(request, 'admin/article_form.html', {
        'active_nav': 'articles',
        'page_title': 'New Article',
        'categories': categories,
        'is_edit': False,
        'is_editor': is_editor,
    })


@login_required
def article_edit(request, pk):
    """Edit existing article."""
    article = get_object_or_404(Article.objects.with_deleted(), pk=pk)

    if request.method == 'POST':
        # Save revision before editing
        ArticleRevision.objects.create(
            article=article,
            title=article.title,
            content=article.content,
            created_by=request.user,
        )

        article.title = request.POST.get('title', article.title)
        article.excerpt = request.POST.get('excerpt', article.excerpt)
        article.content = request.POST.get('content', article.content)
        article.featured_image = request.POST.get('featured_image', article.featured_image)
        article.status = request.POST.get('status', article.status)
        article.category_id = request.POST.get('category_id') or None
        article.display_author = request.POST.get('display_author', '')

        if article.status == 'published' and not article.published_at:
            article.published_at = timezone.now()
        elif article.status == 'scheduled':
            article.published_at = request.POST.get('published_at')

        article.save()

        # Update tags
        article.tags.clear()
        tag_names = request.POST.get('tags', '').split(',')
        for name in tag_names:
            name = name.strip()
            if name:
                tag_obj, _ = Tag.objects.get_or_create(name=name, defaults={'slug': slugify(name)})
                article.tags.add(tag_obj)

        return redirect('admin_articles')

    categories = Category.objects.all()
    current_tags = ', '.join(article.tags.values_list('name', flat=True))
    is_editor = hasattr(request.user, 'role') and request.user.role and request.user.role.level >= 2
    return render(request, 'admin/article_form.html', {
        'active_nav': 'articles',
        'page_title': 'Edit Article',
        'article': article,
        'categories': categories,
        'current_tags': current_tags,
        'is_edit': True,
        'is_editor': is_editor,
        'revisions': article.revisions.order_by('-created_at')[:10],
    })


@login_required
@require_POST
def article_delete(request, pk):
    """Soft-delete article."""
    article = get_object_or_404(Article, pk=pk)
    article.soft_delete()
    return redirect('admin_articles')


@login_required
@require_POST
def article_restore(request, pk):
    """Restore soft-deleted article."""
    article = get_object_or_404(Article.objects.with_deleted(), pk=pk)
    article.restore()
    return redirect('admin_articles_archive')


@login_required
def archive(request):
    """Archived (soft-deleted) articles."""
    articles = Article.objects.with_deleted().filter(deleted_at__isnull=False).order_by('-deleted_at')
    paginator = Paginator(articles, 20)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/articles_archive.html', {
        'active_nav': 'archive',
        'page_title': 'Archive',
        'articles': page,
    })


@login_required
@require_POST
def bulk_archive(request):
    """Bulk archive selected articles."""
    ids = request.POST.getlist('article_ids')
    if ids:
        Article.objects.filter(id__in=ids).update(deleted_at=timezone.now())
    return redirect('admin_articles')


# --- Category admin ---

@login_required
def category_list(request):
    categories = Category.objects.annotate(article_count=Count('articles')).order_by('sort_order')
    return render(request, 'admin/categories/index.html', {
        'active_nav': 'categories',
        'page_title': 'Categories',
        'categories': categories,
    })


@login_required
def category_create(request):
    if request.method == 'POST':
        Category.objects.create(
            name=request.POST.get('name', ''),
            slug=slugify(request.POST.get('name', '')),
            color=request.POST.get('color', ''),
            icon=request.POST.get('icon', ''),
            description=request.POST.get('description', ''),
            sort_order=int(request.POST.get('sort_order', 0)),
            show_in_nav=bool(request.POST.get('show_in_nav')),
            show_in_sidebar=bool(request.POST.get('show_in_sidebar')),
        )
        return redirect('admin_categories')
    return render(request, 'admin/categories/form.html', {
        'active_nav': 'categories',
        'page_title': 'New Category',
    })


@login_required
def category_edit(request, pk):
    cat = get_object_or_404(Category, pk=pk)
    if request.method == 'POST':
        cat.name = request.POST.get('name', cat.name)
        cat.color = request.POST.get('color', cat.color)
        cat.icon = request.POST.get('icon', cat.icon)
        cat.description = request.POST.get('description', cat.description)
        cat.sort_order = int(request.POST.get('sort_order', cat.sort_order))
        cat.show_in_nav = bool(request.POST.get('show_in_nav'))
        cat.show_in_sidebar = bool(request.POST.get('show_in_sidebar'))
        cat.save()
        return redirect('admin_categories')
    return render(request, 'admin/categories/form.html', {
        'active_nav': 'categories',
        'page_title': 'Edit Category',
        'category': cat,
    })


@login_required
@require_POST
def category_delete(request, pk):
    """Delete a category."""
    cat = get_object_or_404(Category, pk=pk)
    name = cat.name
    cat.delete()
    messages.success(request, f'Category "{name}" deleted.')
    return redirect('admin_categories')


# --- Comment moderation ---

@login_required
def comment_list(request):
    status = request.GET.get('status', 'pending')
    filter_param = request.GET.get('filter', '')
    search = request.GET.get('search', '').strip()
    comments = Comment.objects.filter(status=status).select_related('article').order_by('-created_at')
    if filter_param:
        comments = comments.filter(status=filter_param)
    if search:
        comments = comments.filter(
            Q(author_name__icontains=search) | Q(content__icontains=search)
        )
    paginator = Paginator(comments, 20)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/comments/index.html', {
        'active_nav': 'comments',
        'page_title': 'Comments',
        'comments': page,
        'current_status': status,
        'filter': filter_param,
        'search': search,
    })


@login_required
@require_POST
def comment_moderate(request, pk):
    comment = get_object_or_404(Comment, pk=pk)
    action = request.POST.get('action')
    if action == 'approve':
        comment.status = 'approved'
    elif action == 'reject':
        comment.status = 'rejected'
    elif action == 'delete':
        comment.delete()
        return redirect('admin_comments')
    comment.save()
    return redirect('admin_comments')


@login_required
@require_POST
def comment_hide(request, pk):
    """Hide a comment by setting status to rejected."""
    comment = get_object_or_404(Comment, pk=pk)
    comment.status = 'rejected'
    comment.save()
    messages.success(request, 'Comment hidden.')
    return redirect('admin_comments')


@login_required
@require_POST
def comment_show(request, pk):
    """Show a comment by setting status to approved."""
    comment = get_object_or_404(Comment, pk=pk)
    comment.status = 'approved'
    comment.save()
    messages.success(request, 'Comment approved.')
    return redirect('admin_comments')


@login_required
@require_POST
def comment_delete(request, pk):
    """Soft delete a comment by setting status to pending."""
    comment = get_object_or_404(Comment, pk=pk)
    comment.status = 'pending'
    comment.save()
    messages.success(request, 'Comment soft deleted.')
    return redirect('admin_comments')


@login_required
@require_POST
def comment_destroy(request, pk):
    """Permanently delete a comment."""
    comment = get_object_or_404(Comment, pk=pk)
    comment.delete()
    messages.success(request, 'Comment permanently deleted.')
    return redirect('admin_comments')


@login_required
@require_POST
def comments_bulk(request):
    """Bulk approve/reject/delete comments by ids."""
    action = request.POST.get('action')
    ids = request.POST.getlist('ids')
    if not ids:
        messages.warning(request, 'No comments selected.')
        return redirect('admin_comments')

    comments = Comment.objects.filter(id__in=ids)
    if action == 'approve':
        comments.update(status='approved')
        messages.success(request, f'{comments.count()} comments approved.')
    elif action == 'reject':
        comments.update(status='rejected')
        messages.success(request, f'{comments.count()} comments rejected.')
    elif action == 'delete':
        count = comments.count()
        comments.delete()
        messages.success(request, f'{count} comments deleted.')
    return redirect('admin_comments')


@login_required
@role_required(2)
@require_POST
def review_approve(request, pk):
    """Approve an article: set status to published."""
    article = get_object_or_404(Article, pk=pk)
    article.status = 'published'
    article.published_at = timezone.now()
    article.save()
    messages.success(request, f'Article "{article.title}" published.')
    return redirect('admin_review')


@login_required
@role_required(2)
@require_POST
def review_reject(request, pk):
    """Reject an article: set status to archived."""
    article = get_object_or_404(Article, pk=pk)
    article.status = 'archived'
    article.save()
    messages.success(request, f'Article "{article.title}" archived.')
    return redirect('admin_review')


# --- Review (editorial review for crawled articles) ---

@login_required
@role_required(2)
def review_list(request):
    articles = Article.objects.filter(status='pending_review').select_related('category').order_by('-created_at')
    paginator = Paginator(articles, 20)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/review/index.html', {
        'active_nav': 'review',
        'page_title': 'Review Queue',
        'articles': page,
    })


@login_required
@role_required(2)
def review_show(request, pk):
    article = get_object_or_404(Article, pk=pk, status='pending_review')
    if request.method == 'POST':
        action = request.POST.get('action')
        if action == 'approve':
            article.status = 'published'
            article.published_at = timezone.now()
        elif action == 'reject':
            article.status = 'archived'
        article.save()
        return redirect('admin_review')
    return render(request, 'admin/review/show.html', {
        'active_nav': 'review',
        'page_title': 'Review Article',
        'article': article,
    })


# --- Dashboard API endpoints ---

@login_required
def dashboard_pulse(request):
    """Dashboard live stats."""
    now = timezone.now()
    today = now.date()
    return JsonResponse({
        'views_today': ArticleView.objects.filter(viewed_at__date=today).count(),
        'visitors_today': SiteVisitor.objects.filter(visit_date=today).count(),
        'pending_comments': Comment.objects.filter(status='pending').count(),
        'pending_review': Article.objects.filter(status='pending_review').count(),
    })


@login_required
def reader_map_api(request):
    """Reader geographic data for map."""
    visitors = SiteVisitor.objects.filter(
        latitude__isnull=False,
        longitude__isnull=False,
        visit_date__gte=timezone.now().date() - timedelta(days=7),
    ).values('latitude', 'longitude', 'city', 'country')[:500]
    return JsonResponse({'visitors': list(visitors)})


# --- Extra article actions ---


@login_required
@require_POST
def article_permanent_delete(request, pk):
    """Permanently delete an article (no recovery)."""
    article = get_object_or_404(Article.objects.with_deleted(), pk=pk)
    title = article.title
    article.delete()
    messages.success(request, f'Article "{title}" permanently deleted.')
    return redirect('admin_articles_archive')


@login_required
@require_POST
def article_toggle_breaking(request, pk):
    """Toggle breaking news status."""
    article = get_object_or_404(Article, pk=pk)
    article.is_breaking_manual = not article.is_breaking_manual
    article.save(update_fields=['is_breaking_manual'])
    status = 'marked as breaking' if article.is_breaking_manual else 'unmarked as breaking'
    messages.success(request, f'Article "{article.title}" {status}.')
    return redirect('admin_articles')


@login_required
@require_POST
def article_revision_restore(request, article_pk, revision_pk):
    """Restore article content from a previous revision."""
    article = get_object_or_404(Article.objects.with_deleted(), pk=article_pk)
    revision = get_object_or_404(ArticleRevision, pk=revision_pk, article=article)
    # Save current state as new revision before restoring
    ArticleRevision.objects.create(
        article=article,
        title=article.title,
        content=article.content,
        created_by=request.user,
    )
    article.title = revision.title
    article.content = revision.content
    article.save(update_fields=['title', 'content', 'updated_at'])
    messages.success(request, f'Article restored to revision from {revision.created_at}.')
    return redirect('admin_article_edit', pk=article.pk)
