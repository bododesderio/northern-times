import csv
import json

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.db import models
from django.http import HttpResponse, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from .models import AdSlot, Popup


# ── Ad slots ─────────────────────────────────────────────────


@login_required
@role_required(2)
def ads_index(request):
    """Ad slots management dashboard."""
    slots = AdSlot.objects.order_by('name')
    return render(request, 'admin/ads/index.html', {
        'slots': slots,
        'active_nav': 'ads',
        'page_title': 'Ad Placements',
    })


@login_required
@role_required(2)
def ads_create(request):
    """Create a new ad slot."""
    if request.method == 'POST':
        AdSlot.objects.create(
            name=request.POST.get('name', ''),
            slot_name=request.POST.get('slot_name', ''),
            html_content=request.POST.get('html_content', ''),
            is_active=bool(request.POST.get('is_active')),
            device_targeting=request.POST.get('device_targeting', 'all'),
        )
        messages.success(request, 'Ad slot created.')
        return redirect('admin_ads')
    return render(request, 'admin/ads/form.html', {
        'device_choices': AdSlot.DEVICE_CHOICES,
        'active_nav': 'ads',
        'page_title': 'Create Ad',
    })


@login_required
@role_required(2)
def ads_edit(request, pk):
    """Edit an ad slot."""
    slot = get_object_or_404(AdSlot, pk=pk)
    if request.method == 'POST':
        slot.name = request.POST.get('name', slot.name)
        slot.slot_name = request.POST.get('slot_name', slot.slot_name)
        slot.html_content = request.POST.get('html_content', '')
        slot.is_active = bool(request.POST.get('is_active'))
        slot.device_targeting = request.POST.get('device_targeting', 'all')
        slot.save()
        messages.success(request, f'Ad slot "{slot.name}" updated.')
        return redirect('admin_ads')
    return render(request, 'admin/ads/form.html', {
        'slot': slot,
        'device_choices': AdSlot.DEVICE_CHOICES,
        'active_nav': 'ads',
        'page_title': 'Edit Ad',
    })


@login_required
@role_required(2)
@require_POST
def ads_delete(request, pk):
    """Delete an ad slot."""
    slot = get_object_or_404(AdSlot, pk=pk)
    slot.delete()
    messages.success(request, 'Ad slot deleted.')
    return redirect('admin_ads')


# ── Popups ───────────────────────────────────────────────────


@login_required
@role_required(2)
def popups_index(request):
    """Popup management dashboard."""
    popups = Popup.objects.order_by('name')
    totals = {
        'total': popups.count(),
        'active': popups.filter(is_active=True).count(),
        'impressions': sum(p.impressions for p in popups),
        'clicks': sum(p.clicks for p in popups),
    }
    return render(request, 'admin/popups/index.html', {
        'popups': popups,
        'totals': totals,
        'active_nav': 'popups',
        'page_title': 'Popups',
    })


@login_required
@role_required(2)
def popups_create(request):
    """Create a new popup."""
    if request.method == 'POST':
        Popup.objects.create(
            name=request.POST.get('name', ''),
            type=request.POST.get('type', 'modal'),
            content=request.POST.get('content', ''),
            is_active=bool(request.POST.get('is_active')),
            device_targeting=request.POST.get('device_targeting', 'all'),
            trigger_type=request.POST.get('trigger_type', ''),
            trigger_value=request.POST.get('trigger_value', ''),
            variant=request.POST.get('variant', '') or None,
        )
        messages.success(request, 'Popup created.')
        return redirect('admin_popups')
    return render(request, 'admin/ads/popup_form.html', {
        'type_choices': Popup.TYPE_CHOICES,
        'device_choices': Popup.DEVICE_CHOICES,
        'active_nav': 'popups',
        'page_title': 'Create Popup',
    })


@login_required
@role_required(2)
def popups_edit(request, pk):
    """Edit a popup."""
    popup = get_object_or_404(Popup, pk=pk)
    if request.method == 'POST':
        popup.name = request.POST.get('name', popup.name)
        popup.type = request.POST.get('type', popup.type)
        popup.content = request.POST.get('content', '')
        popup.is_active = bool(request.POST.get('is_active'))
        popup.device_targeting = request.POST.get('device_targeting', 'all')
        popup.trigger_type = request.POST.get('trigger_type', '')
        popup.trigger_value = request.POST.get('trigger_value', '')
        popup.variant = request.POST.get('variant', '') or None
        popup.save()
        messages.success(request, f'Popup "{popup.name}" updated.')
        return redirect('admin_popups')
    return render(request, 'admin/ads/popup_form.html', {
        'popup': popup,
        'type_choices': Popup.TYPE_CHOICES,
        'device_choices': Popup.DEVICE_CHOICES,
        'active_nav': 'popups',
        'page_title': 'Edit Popup',
    })


@login_required
@role_required(2)
@require_POST
def popups_delete(request, pk):
    """Delete a popup."""
    popup = get_object_or_404(Popup, pk=pk)
    popup.delete()
    messages.success(request, 'Popup deleted.')
    return redirect('admin_popups')


@login_required
@role_required(2)
def popups_analytics(request):
    """Popup analytics: impressions, clicks, CTR per popup."""
    popups = Popup.objects.order_by('name')
    analytics = []
    for popup in popups:
        ctr = (popup.clicks / popup.impressions * 100) if popup.impressions > 0 else 0
        analytics.append({
            'popup': popup,
            'ctr': round(ctr, 2),
        })
    return render(request, 'admin/ads/popups_analytics.html', {
        'analytics': analytics,
        'active_nav': 'popups',
        'page_title': 'Popup Analytics',
    })


# ── API views ────────────────────────────────────────────────


def active_popups(request):
    """Return active popups for the current device."""
    popups = Popup.objects.filter(is_active=True).order_by('name')
    result = []
    for p in popups:
        result.append({
            'id': str(p.id),
            'name': p.name,
            'type': p.type,
            'content': p.content,
            'title': p.title,
            'body': p.body,
            'image_url': p.image_url,
            'banner_style': p.banner_style,
            'position': p.position,
            'bg_color': p.bg_color,
            'text_color': p.text_color,
            'overlay_opacity': p.overlay_opacity,
            'button_text': p.button_text,
            'button_url': p.button_url,
            'btn_bg_color': p.btn_bg_color,
            'btn_text_color': p.btn_text_color,
            'secondary_btn_text': p.secondary_btn_text,
            'has_email_field': p.has_email_field,
            'trigger_type': p.trigger_type,
            'trigger_value': p.trigger_value,
            'show_delay': p.show_delay,
            'close_delay': p.close_delay,
            'frequency': p.frequency,
            'frequency_days': p.frequency_days,
            'target_device': p.device_targeting,
            'target_audience': p.target_audience,
            'target_pages': p.target_pages,
            'version': p.version,
            'variant': p.variant or '',
        })
    return JsonResponse(result, safe=False)


# ── Popup extra actions ─────────────────────────────────────


@login_required
@role_required(2)
@require_POST
def popup_duplicate(request, pk):
    """Clone a popup with (Copy) suffix."""
    popup = get_object_or_404(Popup, pk=pk)
    popup.pk = None  # This creates a new instance on save
    popup.name = f'{popup.name} (Copy)'
    popup.impressions = 0
    popup.clicks = 0
    popup.is_active = False
    popup.save()
    messages.success(request, f'Popup duplicated as "{popup.name}".')
    return redirect('admin_popups')


@login_required
@role_required(2)
@require_POST
def popup_toggle(request, pk):
    """Toggle popup active/inactive."""
    popup = get_object_or_404(Popup, pk=pk)
    popup.is_active = not popup.is_active
    popup.save(update_fields=['is_active'])
    status = 'activated' if popup.is_active else 'deactivated'
    messages.success(request, f'Popup "{popup.name}" {status}.')
    return redirect('admin_popups')


@login_required
@role_required(2)
@require_POST
def ads_toggle(request, pk):
    """Toggle an ad slot's active state."""
    slot = get_object_or_404(AdSlot, pk=pk)
    slot.is_active = not slot.is_active
    slot.save(update_fields=['is_active'])
    status = 'activated' if slot.is_active else 'deactivated'
    messages.success(request, f'Ad slot "{slot.name}" {status}.')
    return redirect('admin_ads')


@require_POST
def popup_track(request):
    """Track popup impression or click (public API)."""
    try:
        data = json.loads(request.body)
        popup_id = data.get('popup_id')
        action = data.get('action', 'impression')
        popup = Popup.objects.get(pk=popup_id)
        if action == 'click':
            Popup.objects.filter(pk=popup_id).update(clicks=models.F('clicks') + 1)
        else:
            Popup.objects.filter(pk=popup_id).update(impressions=models.F('impressions') + 1)
        return JsonResponse({'ok': True})
    except Exception:
        return JsonResponse({'ok': False}, status=400)


@require_POST
def ad_track(request):
    """Track ad slot impression or click (public API)."""
    try:
        data = json.loads(request.body)
        slot_name = data.get('slot')
        action = data.get('action', 'impression')
        if action == 'click':
            AdSlot.objects.filter(slot_name=slot_name, is_active=True).update(
                clicks=models.F('clicks') + 1,
            )
        else:
            AdSlot.objects.filter(slot_name=slot_name, is_active=True).update(
                impressions=models.F('impressions') + 1,
            )
        return JsonResponse({'ok': True})
    except Exception:
        return JsonResponse({'ok': False}, status=400)


@login_required
@role_required(2)
def popups_analytics_export(request):
    """Export popup analytics as CSV."""
    response = HttpResponse(content_type='text/csv')
    response['Content-Disposition'] = 'attachment; filename="popup_analytics.csv"'
    writer = csv.writer(response)
    writer.writerow(['Name', 'Type', 'Active', 'Impressions', 'Clicks', 'CTR'])
    for p in Popup.objects.all():
        ctr = (p.clicks / p.impressions * 100) if p.impressions > 0 else 0
        writer.writerow([p.name, p.type, p.is_active, p.impressions, p.clicks, f'{ctr:.2f}%'])
    return response


@login_required
@role_required(2)
def ab_tests(request):
    """List A/B test pairs (popups grouped by variant)."""
    popups = Popup.objects.exclude(variant='').exclude(variant__isnull=True).order_by('name', 'variant')
    return render(request, 'admin/popups/ab_tests.html', {
        'popups': popups,
        'active_nav': 'ads',
        'page_title': 'A/B Tests',
    })


@login_required
@role_required(2)
def ab_test_create(request):
    """Create an A/B test -- create two popup variants."""
    if request.method == 'POST':
        base_name = request.POST.get('name', 'A/B Test')
        # Create variant A
        Popup.objects.create(
            name=base_name,
            type=request.POST.get('type', 'modal'),
            content=request.POST.get('content_a', ''),
            variant='A',
            is_active=True,
        )
        # Create variant B
        Popup.objects.create(
            name=base_name,
            type=request.POST.get('type', 'modal'),
            content=request.POST.get('content_b', ''),
            variant='B',
            is_active=True,
        )
        messages.success(request, f'A/B test "{base_name}" created with 2 variants.')
        return redirect('admin_ab_tests')
    return render(request, 'admin/popups/ab_form.html', {
        'active_nav': 'ads',
        'page_title': 'Create A/B Test',
        'type_choices': Popup.TYPE_CHOICES,
    })
