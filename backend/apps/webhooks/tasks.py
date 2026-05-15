from celery import shared_task


@shared_task
def dispatch_webhook(webhook_id, event, payload):
    """Async webhook delivery."""
    try:
        from apps.webhooks.services.dispatcher import WebhookDispatcher
        dispatcher = WebhookDispatcher()
        dispatcher.send(webhook_id, event, payload)
        return f'Webhook {webhook_id} dispatched for {event}'
    except Exception as e:
        return f'Webhook dispatch failed: {e}'
