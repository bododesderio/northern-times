import secrets

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from .models import Webhook, WebhookLog


@login_required
@role_required(3)
def webhook_index(request):
    """List all webhooks."""
    hooks = Webhook.objects.order_by('-created_at')
    return render(request, 'admin/webhooks/index.html', {'webhooks': hooks})


@login_required
@role_required(3)
def webhook_create(request):
    """Create a new webhook."""
    if request.method == 'POST':
        url = request.POST.get('url', '').strip()
        events_raw = request.POST.get('events', '').strip()
        events = [e.strip() for e in events_raw.split(',') if e.strip()] if events_raw else []
        is_active = bool(request.POST.get('is_active'))

        Webhook.objects.create(
            url=url,
            events=events,
            secret=secrets.token_urlsafe(32),
            is_active=is_active,
        )
        messages.success(request, f'Webhook for {url} created.')
        return redirect('admin_webhooks')
    return render(request, 'admin/webhooks/form.html')


@login_required
@role_required(3)
def webhook_edit(request, pk):
    """Edit a webhook."""
    hook = get_object_or_404(Webhook, pk=pk)
    if request.method == 'POST':
        hook.url = request.POST.get('url', hook.url).strip()
        events_raw = request.POST.get('events', '').strip()
        hook.events = [e.strip() for e in events_raw.split(',') if e.strip()] if events_raw else []
        hook.is_active = bool(request.POST.get('is_active'))

        if request.POST.get('regenerate_secret'):
            hook.secret = secrets.token_urlsafe(32)

        hook.save()
        messages.success(request, f'Webhook for {hook.url} updated.')
        return redirect('admin_webhooks')
    return render(request, 'admin/webhooks/form.html', {'webhook': hook})


@login_required
@role_required(3)
@require_POST
def webhook_delete(request, pk):
    """Delete a webhook."""
    hook = get_object_or_404(Webhook, pk=pk)
    hook.delete()
    messages.success(request, 'Webhook deleted.')
    return redirect('admin_webhooks')


@login_required
@role_required(3)
@require_POST
def webhook_test(request, pk):
    """Send a test payload to a webhook endpoint."""
    import json
    import httpx

    hook = get_object_or_404(Webhook, pk=pk)
    test_payload = {
        'event': 'webhook.test',
        'message': 'This is a test payload from Northern Times.',
        'webhook_id': str(hook.pk),
    }
    try:
        resp = httpx.post(
            hook.url,
            json=test_payload,
            timeout=10,
            headers={'Content-Type': 'application/json'},
        )
        WebhookLog.objects.create(
            webhook=hook,
            event='webhook.test',
            payload=test_payload,
            status_code=resp.status_code,
            response_body=resp.text[:2000],
        )
        messages.success(request, f'Test sent to {hook.url} — Status: {resp.status_code}')
    except Exception as e:
        messages.error(request, f'Test failed: {e}')
    return redirect('admin_webhooks')


@login_required
@role_required(3)
def webhook_logs(request):
    """View webhook delivery logs with filters."""
    qs = WebhookLog.objects.select_related('webhook').order_by('-created_at')

    webhook_id = request.GET.get('webhook')
    if webhook_id:
        qs = qs.filter(webhook_id=webhook_id)

    event = request.GET.get('event')
    if event:
        qs = qs.filter(event=event)

    paginator = Paginator(qs, 30)
    page = paginator.get_page(request.GET.get('page'))

    return render(request, 'admin/webhooks/logs.html', {
        'logs': page,
        'webhooks': Webhook.objects.all(),
    })
