from celery import shared_task


@shared_task
def run_scheduled_audit():
    """Run weekly SEO audit."""
    try:
        from apps.seo.services.audit_engine import SeoAuditEngine
        engine = SeoAuditEngine()
        audit = engine.run_audit()
        return f'SEO audit completed. Score: {audit.score}/100'
    except Exception as e:
        return f'SEO audit failed: {e}'
