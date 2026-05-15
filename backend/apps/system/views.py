import os
import platform
import shutil
import subprocess
from datetime import timedelta

from django.conf import settings
from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.cache import cache
from django.core.paginator import Paginator
from django.db import connection
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone
from django.views.decorators.http import require_POST

import django
from django.http import JsonResponse

from apps.accounts.decorators import role_required
from .models import CronRun, DbBackup, LoginQuote, Notification, PolicyPage, SystemLog
from apps.accounts.models import ActiveSession, User
from apps.ads.models import AdSlot, Popup
from apps.analytics.models import ArticleView, DailyStats, SiteVisitor
from apps.articles.models import Article, Comment
from apps.crawler.models import CrawlLog, CrawlSource
from apps.media.models import MediaItem
from apps.newsletter.models import EmailQueue, NewsletterIssue, Subscriber
from apps.seo.models import SeoAudit
from apps.social.models import SocialMention


# ── System admin views ───────────────────────────────────────


@login_required
@role_required(3)
def system_index(request):
    """System administration dashboard with system info."""
    # Database info
    db_info = {}
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT version()")
            db_info['version'] = cursor.fetchone()[0]
            cursor.execute("SELECT pg_database_size(current_database())")
            size_bytes = cursor.fetchone()[0]
            db_info['size'] = f'{size_bytes / (1024 ** 2):.1f} MB'
    except Exception:
        db_info['version'] = 'Unknown'
        db_info['size'] = 'Unknown'

    # Disk usage
    disk = {}
    try:
        usage = shutil.disk_usage('/')
        disk['total'] = f'{usage.total / (1024 ** 3):.1f} GB'
        disk['used'] = f'{usage.used / (1024 ** 3):.1f} GB'
        disk['free'] = f'{usage.free / (1024 ** 3):.1f} GB'
        disk['free_pct'] = f'{usage.free / usage.total * 100:.1f}%'
    except Exception:
        pass

    # Redis info
    redis_status = 'unknown'
    try:
        cache.set('_sys_check', '1', 5)
        redis_status = 'up' if cache.get('_sys_check') == '1' else 'down'
    except Exception:
        redis_status = 'down'

    # Last crawl time
    last_crawl = CrawlLog.objects.order_by('-created_at').values_list('created_at', flat=True).first()

    # Database table stats
    db_stats = [
        {'label': 'Articles', 'count': Article.objects.count()},
        {'label': 'Comments', 'count': Comment.objects.count()},
        {'label': 'Categories', 'count': Article.objects.values('category').distinct().count()},
        {'label': 'Subscribers', 'count': Subscriber.objects.count()},
        {'label': 'Crawl Sources', 'count': CrawlSource.objects.count()},
        {'label': 'Media Items', 'count': MediaItem.objects.count()},
        {'label': 'Users', 'count': User.objects.count()},
        {'label': 'Visitors (30d)', 'count': SiteVisitor.objects.filter(
            visit_date__gte=timezone.now().date() - timedelta(days=30),
        ).count()},
    ]

    return render(request, 'admin/system/index.html', {
        'python_version': platform.python_version(),
        'os_info': f'{platform.system()} {platform.release()}',
        'db_info': db_info,
        'disk': disk,
        'redis_status': redis_status,
        'health': {
            'db': db_info.get('version') != 'Unknown',
            'db_status': db_info.get('size', 'Unknown'),
            'redis': redis_status == 'up',
            'redis_status': 'UP' if redis_status == 'up' else 'DOWN',
            'disk_usage': disk.get('free_pct', '--'),
            'last_crawl': last_crawl.strftime('%b %d, %I:%M %p') if last_crawl else '--',
        },
        'db_stats': db_stats,
        'backups': DbBackup.objects.order_by('-created_at')[:10],
        'logs': SystemLog.objects.all()[:20],
        'recent_logs': SystemLog.objects.all()[:10],
        'active_nav': 'system',
        'page_title': 'System',
    })


@login_required
@role_required(3)
def system_logs(request):
    """View system logs with pagination and filters."""
    qs = SystemLog.objects.select_related('user').order_by('-created_at')

    action_filter = request.GET.get('action', '').strip()
    if action_filter:
        qs = qs.filter(action__icontains=action_filter)

    paginator = Paginator(qs, 50)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/system/logs.html', {'logs': page})


@login_required
@role_required(3)
def backups(request):
    """View database backups."""
    backup_list = DbBackup.objects.order_by('-created_at')
    return render(request, 'admin/system/backups.html', {'backups': backup_list})


@login_required
@role_required(3)
@require_POST
def backup_create(request):
    """Create a new database backup via pg_dump."""
    try:
        backup_dir = os.path.join(settings.BASE_DIR, 'backups')
        os.makedirs(backup_dir, exist_ok=True)

        timestamp = timezone.now().strftime('%Y%m%d_%H%M%S')
        filename = f'backup_{timestamp}.sql.gz'
        filepath = os.path.join(backup_dir, filename)

        db = settings.DATABASES['default']
        env = os.environ.copy()
        env['PGPASSWORD'] = db.get('PASSWORD', '')

        cmd = [
            'pg_dump',
            '-h', db.get('HOST', 'localhost'),
            '-p', str(db.get('PORT', 5432)),
            '-U', db.get('USER', 'postgres'),
            '-d', db.get('NAME', ''),
            '-Fc',
        ]
        with open(filepath, 'wb') as f:
            subprocess.run(cmd, stdout=f, env=env, check=True, timeout=300)

        size = os.path.getsize(filepath)
        DbBackup.objects.create(filename=filename, size=size)
        messages.success(request, f'Backup "{filename}" created ({size / (1024 ** 2):.1f} MB).')
    except Exception as e:
        messages.error(request, f'Backup failed: {e}')

    return redirect('admin_system_backups')


@login_required
@role_required(3)
@require_POST
def backup_restore(request, pk):
    """Restore from a database backup."""
    backup = get_object_or_404(DbBackup, pk=pk)
    filepath = os.path.join(settings.BASE_DIR, 'backups', backup.filename)

    if not os.path.exists(filepath):
        messages.error(request, f'Backup file not found: {backup.filename}')
        return redirect('admin_system_backups')

    try:
        db = settings.DATABASES['default']
        env = os.environ.copy()
        env['PGPASSWORD'] = db.get('PASSWORD', '')

        cmd = [
            'pg_restore',
            '-h', db.get('HOST', 'localhost'),
            '-p', str(db.get('PORT', 5432)),
            '-U', db.get('USER', 'postgres'),
            '-d', db.get('NAME', ''),
            '--clean',
            '--if-exists',
            filepath,
        ]
        subprocess.run(cmd, env=env, check=True, timeout=600)
        messages.success(request, f'Database restored from "{backup.filename}".')
    except Exception as e:
        messages.error(request, f'Restore failed: {e}')

    return redirect('admin_system_backups')


@login_required
@role_required(3)
@require_POST
def flush_cache(request):
    """Flush the entire application cache (Redis)."""
    try:
        cache.clear()
        messages.success(request, 'Cache flushed successfully.')
    except Exception as e:
        messages.error(request, f'Cache flush failed: {e}')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_logs(request):
    """Purge old system logs (older than 30 days)."""
    cutoff = timezone.now() - timedelta(days=30)
    count, _ = SystemLog.objects.filter(created_at__lt=cutoff).delete()
    messages.success(request, f'{count} old log entries purged.')
    return redirect('admin_system_logs')


@login_required
@role_required(3)
def mail_queue(request):
    """View mail queue with status filter."""
    qs = EmailQueue.objects.order_by('-created_at')

    status_filter = request.GET.get('status')
    if status_filter:
        qs = qs.filter(status=status_filter)

    paginator = Paginator(qs, 30)
    page = paginator.get_page(request.GET.get('page'))

    counts = {
        'pending': EmailQueue.objects.filter(status='pending').count(),
        'sent': EmailQueue.objects.filter(status='sent').count(),
        'failed': EmailQueue.objects.filter(status='failed').count(),
    }
    return render(request, 'admin/system/mail_queue.html', {
        'emails': page,
        'counts': counts,
    })


@login_required
@role_required(3)
@require_POST
def mail_retry(request, pk):
    """Retry a failed email by resetting its status."""
    email = get_object_or_404(EmailQueue, pk=pk)
    if email.status == 'failed':
        email.status = 'pending'
        email.attempts = 0
        email.error_message = ''
        email.save()
        messages.success(request, f'Email to {email.to_email} re-queued.')
    else:
        messages.warning(request, 'Only failed emails can be retried.')
    return redirect('admin_system_mail_queue')


# ── Policy pages ─────────────────────────────────────────────


@login_required
@role_required(3)
def policy_index(request):
    """List policy pages."""
    pages = PolicyPage.objects.order_by('sort_order', 'title')
    return render(request, 'admin/policies/index.html', {
        'pages': pages,
        'active_nav': 'policies',
        'page_title': 'Policy Pages',
    })


@login_required
@role_required(3)
def policy_create(request):
    """Create a new policy page."""
    if request.method == 'POST':
        PolicyPage.objects.create(
            title=request.POST.get('title', ''),
            slug=request.POST.get('slug', ''),
            content=request.POST.get('content', ''),
            is_published=bool(request.POST.get('is_published')),
            show_in_footer=bool(request.POST.get('show_in_footer')),
            sort_order=int(request.POST.get('sort_order', 0)),
        )
        messages.success(request, 'Policy page created.')
        return redirect('admin_policies')
    return render(request, 'admin/policies/form.html', {
        'active_nav': 'policies',
        'page_title': 'Create Policy Page',
    })


@login_required
@role_required(3)
def policy_edit(request, pk):
    """Edit a policy page."""
    page = get_object_or_404(PolicyPage, pk=pk)
    if request.method == 'POST':
        page.title = request.POST.get('title', page.title)
        page.slug = request.POST.get('slug', page.slug)
        page.content = request.POST.get('content', '')
        page.is_published = bool(request.POST.get('is_published'))
        page.show_in_footer = bool(request.POST.get('show_in_footer'))
        page.sort_order = int(request.POST.get('sort_order', page.sort_order))
        page.save()
        messages.success(request, f'Policy page "{page.title}" updated.')
        return redirect('admin_policies')
    return render(request, 'admin/policies/form.html', {
        'page': page,
        'active_nav': 'policies',
        'page_title': 'Edit Policy Page',
    })


@login_required
@role_required(3)
@require_POST
def policy_delete(request, pk):
    """Delete a policy page."""
    page = get_object_or_404(PolicyPage, pk=pk)
    title = page.title
    page.delete()
    messages.success(request, f'Policy page "{title}" deleted.')
    return redirect('admin_policies')


# ── AI Tools ────────────────────────────────────────────────


@login_required
@role_required(3)
@require_POST
def ai_reclassify(request):
    """Trigger AI reclassification of recent articles."""
    from apps.enrichment.tasks import ai_reclassify_articles
    ai_reclassify_articles.delay()
    messages.success(request, 'AI reclassification task queued. Articles from the last 24 hours will be reclassified.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def cleanup_duplicates(request):
    """Trigger duplicate article cleanup."""
    from apps.enrichment.tasks import cleanup_duplicates as cleanup_task
    cleanup_task.delay()
    messages.success(request, 'Duplicate cleanup task queued. Near-duplicate articles from the last 48 hours will be archived.')
    return redirect('admin_system')


# ── Data Resets ──────────────────────────────────────────────


@login_required
@role_required(3)
@require_POST
def reset_article_views(request):
    count, _ = ArticleView.objects.all().delete()
    messages.success(request, f'{count} article view records deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_site_visitors(request):
    v_count, _ = SiteVisitor.objects.all().delete()
    d_count, _ = DailyStats.objects.all().delete()
    messages.success(request, f'{v_count} visitor records and {d_count} daily stats deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_ad_stats(request):
    AdSlot.objects.all().update(impressions=0, clicks=0)
    Popup.objects.all().update(impressions=0, clicks=0)
    messages.success(request, 'Ad statistics reset to zero.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_newsletter_stats(request):
    NewsletterIssue.objects.all().update(recipient_count=0)
    messages.success(request, 'Newsletter statistics reset.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_crawler_stats(request):
    CrawlSource.objects.all().update(consecutive_failures=0, last_crawled_at=None)
    messages.success(request, 'Crawler statistics reset.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_all_analytics(request):
    counts = {}
    counts['views'], _ = ArticleView.objects.all().delete()
    counts['visitors'], _ = SiteVisitor.objects.all().delete()
    counts['daily_stats'], _ = DailyStats.objects.all().delete()
    AdSlot.objects.all().update(impressions=0, clicks=0)
    Popup.objects.all().update(impressions=0, clicks=0)
    NewsletterIssue.objects.all().update(recipient_count=0)
    CrawlSource.objects.all().update(consecutive_failures=0, last_crawled_at=None)
    messages.success(request, f'All analytics reset. Deleted {counts["views"]} views, {counts["visitors"]} visitors, {counts["daily_stats"]} daily stats.')
    return redirect('admin_system')


# ── Content Purges ───────────────────────────────────────────


@login_required
@role_required(3)
@require_POST
def purge_comments(request):
    count, _ = Comment.objects.all().delete()
    messages.success(request, f'{count} comments deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_subscribers(request):
    count, _ = Subscriber.objects.all().delete()
    messages.success(request, f'{count} subscribers deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_notifications(request):
    count, _ = Notification.objects.all().delete()
    messages.success(request, f'{count} notifications deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_email_queue(request):
    count, _ = EmailQueue.objects.all().delete()
    messages.success(request, f'{count} email queue entries deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_crawl_history(request):
    count, _ = CrawlLog.objects.all().delete()
    messages.success(request, f'{count} crawl log entries deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_seo_history(request):
    count, _ = SeoAudit.objects.all().delete()
    messages.success(request, f'{count} SEO audit records deleted (issues cascade-deleted).')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def purge_social_mentions(request):
    count, _ = SocialMention.objects.all().delete()
    messages.success(request, f'{count} social mentions deleted.')
    return redirect('admin_system')


# ── Danger Zone ──────────────────────────────────────────────


@login_required
@role_required(3)
@require_POST
def delete_all_articles(request):
    count, _ = Article.objects.all().delete()
    messages.success(request, f'{count} articles deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def delete_crawled_articles(request):
    count, _ = Article.objects.filter(is_crawled=True).delete()
    messages.success(request, f'{count} crawled articles deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_all_popups(request):
    count, _ = Popup.objects.all().delete()
    messages.success(request, f'{count} popups deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def reset_crawler_system(request):
    s_count, _ = CrawlSource.objects.all().delete()
    l_count, _ = CrawlLog.objects.all().delete()
    messages.success(request, f'{s_count} crawl sources and {l_count} crawl logs deleted.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def factory_reset(request):
    """Delete everything except the requesting user and Role records."""
    from apps.accounts.models import Role

    # Delete all content and data
    Article.objects.all().delete()
    Comment.objects.all().delete()
    ArticleView.objects.all().delete()
    SiteVisitor.objects.all().delete()
    DailyStats.objects.all().delete()
    AdSlot.objects.all().delete()
    Popup.objects.all().delete()
    CrawlSource.objects.all().delete()
    CrawlLog.objects.all().delete()
    SeoAudit.objects.all().delete()
    SocialMention.objects.all().delete()
    MediaItem.objects.all().delete()
    Subscriber.objects.all().delete()
    NewsletterIssue.objects.all().delete()
    EmailQueue.objects.all().delete()
    Notification.objects.all().delete()
    ActiveSession.objects.all().delete()
    CronRun.objects.all().delete()
    SystemLog.objects.all().delete()
    DbBackup.objects.all().delete()
    PolicyPage.objects.all().delete()
    LoginQuote.objects.all().delete()

    # Delete all users except the requesting user
    User.objects.exclude(pk=request.user.pk).delete()

    messages.success(request, 'Factory reset complete. All data deleted except your account and roles.')
    return redirect('admin_system')


# ── Database & Infrastructure ────────────────────────────────


@login_required
@role_required(3)
def table_sizes(request):
    """Return JSON with table sizes."""
    with connection.cursor() as cursor:
        cursor.execute("""
            SELECT relname, pg_total_relation_size(C.oid) AS size
            FROM pg_class C
            JOIN pg_namespace N ON N.oid = C.relnamespace
            WHERE nspname = 'public'
            ORDER BY pg_total_relation_size(C.oid) DESC
        """)
        rows = cursor.fetchall()
    data = [
        {'table': row[0], 'size_bytes': row[1], 'size_mb': round(row[1] / (1024 * 1024), 2)}
        for row in rows
    ]
    return JsonResponse({'tables': data})


@login_required
@role_required(3)
@require_POST
def vacuum_db(request):
    """Run VACUUM ANALYZE on the database."""
    try:
        with connection.cursor() as cursor:
            # VACUUM cannot run inside a transaction block
            old_autocommit = connection.connection.autocommit
            connection.connection.autocommit = True
            try:
                cursor.execute('VACUUM ANALYZE')
            finally:
                connection.connection.autocommit = old_autocommit
        messages.success(request, 'VACUUM ANALYZE completed successfully.')
    except Exception as e:
        messages.error(request, f'VACUUM failed: {e}')
    return redirect('admin_system')


@login_required
@role_required(3)
def environment_api(request):
    """Return JSON with environment information."""
    db_version = 'unknown'
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT version()")
            db_version = cursor.fetchone()[0]
    except Exception:
        pass

    redis_ok = False
    try:
        cache.set('_env_check', '1', 5)
        redis_ok = cache.get('_env_check') == '1'
    except Exception:
        pass

    return JsonResponse({
        'python_version': platform.python_version(),
        'django_version': django.get_version(),
        'db_version': db_version,
        'redis_status': 'up' if redis_ok else 'down',
    })


@login_required
@role_required(3)
def cron_history(request):
    """Paginated list of CronRun records."""
    qs = CronRun.objects.order_by('-started_at')
    paginator = Paginator(qs, 50)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/system/cron_history.html', {'runs': page})


@login_required
@role_required(3)
@require_POST
def force_logout_user(request):
    """Force logout a user by deleting their active sessions."""
    user_id = request.POST.get('user_id')
    if not user_id:
        messages.error(request, 'No user ID provided.')
        return redirect('admin_system')
    count, _ = ActiveSession.objects.filter(user_id=user_id).delete()
    messages.success(request, f'{count} active sessions deleted for user {user_id}.')
    return redirect('admin_system')


@login_required
@role_required(3)
@require_POST
def clean_sessions(request):
    """Delete active sessions older than 24 hours."""
    cutoff = timezone.now() - timedelta(hours=24)
    count, _ = ActiveSession.objects.filter(last_activity_at__lt=cutoff).delete()
    messages.success(request, f'{count} stale sessions cleaned up.')
    return redirect('admin_system')


# ── Login quotes ─────────────────────────────────────────────


@login_required
@role_required(3)
def login_quotes_index(request):
    """Manage login quotes: list, add, toggle."""
    if request.method == 'POST':
        action = request.POST.get('action')
        if action == 'add':
            LoginQuote.objects.create(
                quote=request.POST.get('quote', ''),
                author=request.POST.get('author', ''),
            )
            messages.success(request, 'Quote added.')
        elif action == 'toggle':
            quote_id = request.POST.get('quote_id')
            if quote_id:
                q = get_object_or_404(LoginQuote, pk=quote_id)
                q.is_active = not q.is_active
                q.save()
        elif action == 'delete':
            quote_id = request.POST.get('quote_id')
            if quote_id:
                LoginQuote.objects.filter(pk=quote_id).delete()
                messages.success(request, 'Quote deleted.')
        return redirect('admin_login_quotes')

    quotes = LoginQuote.objects.order_by('author')
    return render(request, 'admin/system/login_quotes.html', {'quotes': quotes})
