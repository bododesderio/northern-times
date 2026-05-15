from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.http import HttpResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.accounts.decorators import role_required
from .models import SeoAudit, SeoIssue


@login_required
@role_required(2)
def audit_list(request):
    """SEO dashboard with latest audit results."""
    last_audit = SeoAudit.objects.order_by('-created_at').first()
    last_run = last_audit.created_at if last_audit else None

    issues = []
    issues_count = 0
    if last_audit:
        issues = list(last_audit.issues.select_related().order_by('-severity')[:50])
        issues_count = last_audit.issues.count()

    return render(request, 'admin/seo/index.html', {
        'last_run': last_run,
        'issues_count': issues_count,
        'issues': issues,
        'active_nav': 'seo',
        'page_title': 'SEO Audit',
    })


@login_required
@role_required(2)
def audit_detail(request, pk):
    """View SEO audit details with filtered issues."""
    audit = get_object_or_404(SeoAudit, pk=pk)
    issues_qs = audit.issues.order_by('-severity', '-created_at')

    severity = request.GET.get('severity')
    if severity:
        issues_qs = issues_qs.filter(severity=severity)

    paginator = Paginator(issues_qs, 50)
    page = paginator.get_page(request.GET.get('page'))

    return render(request, 'admin/seo/audit_detail.html', {
        'audit': audit,
        'issues': page,
        'current_severity': severity,
        'active_nav': 'seo',
        'page_title': 'Audit Detail',
    })


@login_required
@role_required(2)
@require_POST
def run_audit(request):
    """Trigger a new SEO audit in the background."""
    from apps.seo.tasks import run_scheduled_audit
    run_scheduled_audit.delay()
    messages.info(request, 'SEO audit started in the background.')
    return redirect('admin_seo')


@login_required
@role_required(2)
def report_pdf(request, pk):
    """Download SEO audit report as PDF."""
    audit = get_object_or_404(SeoAudit, pk=pk)
    issues = audit.issues.order_by('severity', 'issue_type')

    # Build a simple text-based PDF-like report
    # For production, use reportlab or weasyprint
    lines = [
        f'SEO Audit Report',
        f'Date: {audit.created_at:%Y-%m-%d %H:%M}',
        f'Score: {audit.score}/100',
        f'Pages scanned: {audit.pages_scanned}',
        f'Critical: {audit.critical_count} | Warning: {audit.warning_count} | Info: {audit.info_count}',
        '',
        'Issues:',
    ]
    for issue in issues:
        lines.append(f'  [{issue.severity.upper()}] {issue.issue_type} - {issue.url}')
        lines.append(f'    {issue.message}')

    content = '\n'.join(lines)
    response = HttpResponse(content, content_type='text/plain')
    response['Content-Disposition'] = f'attachment; filename="seo_audit_{audit.pk}.txt"'
    return response
