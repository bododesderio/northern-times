import csv
import secrets

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.http import HttpResponse, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from .models import EmailQueue, NewsletterIssue, Subscriber


# ── Admin views ──────────────────────────────────────────────


@login_required
def newsletter_index(request):
    """Newsletter admin dashboard: list all issues."""
    qs = NewsletterIssue.objects.order_by('-created_at')
    status_filter = request.GET.get('status')
    if status_filter:
        qs = qs.filter(status=status_filter)
    paginator = Paginator(qs, 20)
    page = paginator.get_page(request.GET.get('page'))
    active_count = Subscriber.objects.filter(status='active').count()
    return render(request, 'admin/newsletter/index.html', {
        'issues': page,
        'subscriber_count': active_count,
        'active_count': active_count,
        'stats': {
            'sent': NewsletterIssue.objects.filter(status='sent').count(),
        },
        'queue_stats': {
            'pending': EmailQueue.objects.filter(status='pending').count(),
            'failed': EmailQueue.objects.filter(status='failed').count(),
        },
        'active_nav': 'newsletter',
        'page_title': 'Newsletter',
    })


@login_required
@role_required(2)
def newsletter_compose(request, pk=None):
    """Create or edit a newsletter issue."""
    issue = get_object_or_404(NewsletterIssue, pk=pk) if pk else None
    if request.method == 'POST':
        subject = request.POST.get('subject', '').strip()
        content = request.POST.get('content', '')
        status = request.POST.get('status', 'draft')

        if issue:
            issue.subject = subject
            issue.content = content
            issue.status = status
            if status == 'scheduled':
                scheduled_at = request.POST.get('scheduled_at')
                issue.scheduled_at = scheduled_at or None
            issue.save()
            messages.success(request, f'Newsletter "{subject}" updated.')
        else:
            issue = NewsletterIssue.objects.create(
                subject=subject,
                content=content,
                status=status,
                scheduled_at=request.POST.get('scheduled_at') or None,
            )
            messages.success(request, f'Newsletter "{subject}" created.')
        return redirect('admin_newsletter')

    from apps.articles.models import Article
    recent_articles = Article.objects.filter(
        status='published', deleted_at__isnull=True,
    ).select_related('category').order_by('-published_at')[:30]

    return render(request, 'admin/newsletter/compose.html', {
        'issue': issue,
        'recent_articles': recent_articles,
        'active_count': Subscriber.objects.filter(status='active').count(),
        'active_nav': 'newsletter',
        'page_title': 'Compose Newsletter',
    })


@login_required
def newsletter_preview(request, pk):
    """Preview a newsletter issue."""
    issue = get_object_or_404(NewsletterIssue, pk=pk)
    return render(request, 'admin/newsletter/preview.html', {
        'issue': issue,
        'active_nav': 'newsletter',
        'page_title': 'Preview',
    })


@login_required
def subscriber_index(request):
    """List all subscribers with search and pagination."""
    qs = Subscriber.objects.order_by('-created_at')
    search = request.GET.get('q', '').strip()
    if search:
        qs = qs.filter(email__icontains=search)
    status_filter = request.GET.get('status')
    if status_filter:
        qs = qs.filter(status=status_filter)

    paginator = Paginator(qs, 30)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/subscribers/index.html', {
        'subscribers': page,
        'search': search,
        'total_count': Subscriber.objects.count(),
        'total_active': Subscriber.objects.filter(status='active').count(),
        'total_unsubscribed': Subscriber.objects.filter(status='unsubscribed').count(),
        'active_nav': 'subscribers',
        'page_title': 'Subscribers',
    })


@login_required
def subscriber_export(request):
    """Export subscribers as CSV."""
    response = HttpResponse(content_type='text/csv')
    response['Content-Disposition'] = 'attachment; filename="subscribers.csv"'

    writer = csv.writer(response)
    writer.writerow(['email', 'status', 'source', 'subscribed_at'])

    for sub in Subscriber.objects.order_by('-created_at').iterator():
        writer.writerow([sub.email, sub.status, sub.source, sub.created_at.isoformat()])

    return response


@login_required
@require_POST
def subscriber_delete(request, pk):
    """Delete a subscriber."""
    subscriber = get_object_or_404(Subscriber, pk=pk)
    subscriber.delete()
    messages.success(request, f'Subscriber {subscriber.email} deleted.')
    return redirect('admin_subscribers')


@login_required
@role_required(2)
@require_POST
def newsletter_send_test(request):
    """Send a generic test email to verify mail delivery."""
    test_email = request.POST.get('test_email', '').strip()
    if test_email:
        from django.core.mail import send_mail
        from django.conf import settings
        try:
            send_mail(
                subject='Northern Times — Test Email',
                message='This is a test email from Northern Times. If you received this, email delivery is working correctly.',
                from_email=settings.DEFAULT_FROM_EMAIL,
                recipient_list=[test_email],
                fail_silently=False,
            )
            messages.success(request, f'Test email sent to {test_email}.')
        except Exception as e:
            messages.error(request, f'Email failed: {e}')
    else:
        messages.error(request, 'Please enter an email address.')
    return redirect('admin_newsletter')


@login_required
@role_required(2)
@require_POST
def newsletter_test(request, pk):
    """Send a test email for a newsletter issue."""
    issue = get_object_or_404(NewsletterIssue, pk=pk)
    messages.info(request, f'Test email for "{issue.subject}" sent (stub).')
    return redirect('admin_newsletter')


# ── API views ────────────────────────────────────────────────


@login_required
@role_required(2)
@require_POST
def newsletter_schedule(request, pk):
    """Schedule a newsletter for later sending."""
    issue = get_object_or_404(NewsletterIssue, pk=pk)
    scheduled_at = request.POST.get('scheduled_at')
    if scheduled_at:
        from django.utils.dateparse import parse_datetime
        dt = parse_datetime(scheduled_at)
        if dt:
            issue.scheduled_at = dt
            issue.status = 'scheduled'
            issue.save(update_fields=['scheduled_at', 'status'])
            messages.success(request, f'Newsletter scheduled for {dt}.')
        else:
            messages.error(request, 'Invalid date format.')
    return redirect('admin_newsletter')


@login_required
@role_required(2)
@require_POST
def newsletter_draft_delete(request, pk):
    """Delete a draft newsletter."""
    issue = get_object_or_404(NewsletterIssue, pk=pk)
    if issue.status == 'draft':
        issue.delete()
        messages.success(request, 'Draft deleted.')
    else:
        messages.error(request, 'Only drafts can be deleted this way.')
    return redirect('admin_newsletter')


@login_required
@role_required(2)
@require_POST
def newsletter_delete(request, pk):
    """Soft-delete a newsletter by changing status."""
    issue = get_object_or_404(NewsletterIssue, pk=pk)
    issue.status = 'deleted'
    issue.save(update_fields=['status'])
    messages.success(request, f'Newsletter "{issue.subject}" deleted.')
    return redirect('admin_newsletter')


@login_required
@role_required(2)
@require_POST
def newsletter_restore(request, pk):
    """Restore a deleted newsletter to draft."""
    issue = get_object_or_404(NewsletterIssue, pk=pk)
    if issue.status == 'deleted':
        issue.status = 'draft'
        issue.save(update_fields=['status'])
        messages.success(request, f'Newsletter "{issue.subject}" restored.')
    return redirect('admin_newsletter')


@login_required
@role_required(2)
def newsletter_queue(request):
    """View email queue with status."""
    qs = EmailQueue.objects.order_by('-created_at')
    status_filter = request.GET.get('status')
    if status_filter:
        qs = qs.filter(status=status_filter)
    paginator = Paginator(qs, 50)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/newsletter/queue.html', {
        'emails': page,
        'active_nav': 'newsletter',
        'page_title': 'Email Queue',
    })


@login_required
@role_required(2)
@require_POST
def newsletter_queue_delete(request, pk):
    """Remove an email from queue."""
    email = get_object_or_404(EmailQueue, pk=pk)
    email.delete()
    messages.success(request, 'Email removed from queue.')
    return redirect('admin_newsletter_queue')


@login_required
@role_required(2)
@require_POST
def newsletter_queue_retry(request, pk):
    """Retry a failed email."""
    email = get_object_or_404(EmailQueue, pk=pk)
    if email.status == 'failed':
        email.status = 'pending'
        email.attempts = 0
        email.error_message = ''
        email.save()
        messages.success(request, f'Email to {email.to_email} re-queued.')
    return redirect('admin_newsletter_queue')


# ── API views ────────────────────────────────────────────────


def subscribe(request):
    """Public API endpoint for newsletter subscription."""
    if request.method != 'POST':
        return JsonResponse({'error': 'Method not allowed'}, status=405)

    email = request.POST.get('email', '').strip()
    if not email:
        return JsonResponse({'error': 'Email is required'}, status=400)

    subscriber, created = Subscriber.objects.get_or_create(
        email=email,
        defaults={
            'unsub_token': secrets.token_urlsafe(32),
            'source': request.POST.get('source', 'website'),
            'status': 'active',
        },
    )

    if not created:
        if subscriber.status == 'unsubscribed':
            subscriber.status = 'active'
            subscriber.save()
            return JsonResponse({'status': 'resubscribed'})
        return JsonResponse({'status': 'already_subscribed'})

    return JsonResponse({'status': 'subscribed'})
