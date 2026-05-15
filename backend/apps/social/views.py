from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.shortcuts import redirect, render
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from .models import SocialKeyword, SocialMention


@login_required
@role_required(2)
def keywords(request):
    """Manage social monitoring keywords: list and add."""
    if request.method == 'POST':
        keyword_text = request.POST.get('keyword', '').strip()
        if keyword_text:
            obj, created = SocialKeyword.objects.get_or_create(keyword=keyword_text)
            if created:
                messages.success(request, f'Keyword "{keyword_text}" added.')
            else:
                messages.info(request, f'Keyword "{keyword_text}" already exists.')
        return redirect('admin_social')

    kws = SocialKeyword.objects.order_by('keyword')
    recent_mentions = SocialMention.objects.select_related('keyword').order_by('-mentioned_at')[:10]
    return render(request, 'admin/social/index.html', {
        'keywords': kws,
        'posts': recent_mentions,
        'active_nav': 'social',
        'page_title': 'Social Monitoring',
    })


@login_required
@role_required(2)
@require_POST
def keyword_toggle(request, pk):
    """Toggle a keyword's active state."""
    from django.shortcuts import get_object_or_404
    kw = get_object_or_404(SocialKeyword, pk=pk)
    kw.is_active = not kw.is_active
    kw.save()
    state = 'activated' if kw.is_active else 'deactivated'
    messages.success(request, f'Keyword "{kw.keyword}" {state}.')
    return redirect('admin_social')


@login_required
@role_required(2)
def mentions(request):
    """View social mentions with filters."""
    qs = SocialMention.objects.select_related('keyword').order_by('-mentioned_at')

    keyword_id = request.GET.get('keyword')
    if keyword_id:
        qs = qs.filter(keyword_id=keyword_id)

    sentiment = request.GET.get('sentiment')
    if sentiment:
        qs = qs.filter(sentiment=sentiment)

    source = request.GET.get('source')
    if source:
        qs = qs.filter(source=source)

    paginator = Paginator(qs, 30)
    page = paginator.get_page(request.GET.get('page'))

    return render(request, 'admin/social/mentions.html', {
        'mentions': page,
        'keywords': SocialKeyword.objects.all(),
        'current_keyword': keyword_id,
        'current_sentiment': sentiment,
        'current_source': source,
    })


@login_required
@role_required(2)
def social_settings(request):
    """Social monitoring settings."""
    from apps.core.models import Setting
    if request.method == 'POST':
        Setting.set('social_monitoring_enabled', request.POST.get('enabled', '0'), type='bool')
        Setting.set('social_check_interval', request.POST.get('interval', '60'), type='int')
        messages.success(request, 'Social monitoring settings saved.')
        return redirect('admin_social_settings')

    return render(request, 'admin/social/settings.html', {
        'enabled': Setting.get('social_monitoring_enabled', False),
        'interval': Setting.get('social_check_interval', 60),
    })


@login_required
@role_required(2)
def post_log(request):
    """View social post log (auto-posted articles to social media)."""
    from apps.system.models import SystemLog
    qs = SystemLog.objects.filter(
        action__startswith='social_post'
    ).order_by('-created_at')

    paginator = Paginator(qs, 30)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/social/post_log.html', {'logs': page})
