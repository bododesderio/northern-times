import logging
import uuid


class RequestIDMiddleware:
    """Attach a unique request ID to every request for log correlation."""

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        request_id = request.META.get('HTTP_X_REQUEST_ID', str(uuid.uuid4())[:8])
        request.request_id = request_id

        # Inject request_id into all log records for this request
        old_factory = logging.getLogRecordFactory()

        def record_factory(*args, **kwargs):
            record = old_factory(*args, **kwargs)
            record.request_id = request_id
            return record

        logging.setLogRecordFactory(record_factory)

        response = self.get_response(request)
        response['X-Request-ID'] = request_id

        # Restore original factory
        logging.setLogRecordFactory(old_factory)
        return response


class SecurityHeadersMiddleware:
    """Add security-related HTTP headers to every response."""

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        response = self.get_response(request)

        response['X-Frame-Options'] = 'SAMEORIGIN'
        response['X-Content-Type-Options'] = 'nosniff'
        response['Referrer-Policy'] = 'strict-origin-when-cross-origin'
        response['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()'
        response['Content-Security-Policy'] = "img-src 'self' data: https: blob:"

        # Prevent caching on admin pages
        if request.path.startswith('/admin'):
            response['Cache-Control'] = 'no-cache, no-store'

        return response
