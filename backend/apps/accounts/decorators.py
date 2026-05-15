from functools import wraps

from django.http import HttpResponseForbidden


def role_required(min_level):
    """Decorator that checks the user's role level.

    Returns 403 if the user's role level is below min_level.
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return HttpResponseForbidden('Authentication required.')
            if request.user.is_superuser:
                return view_func(request, *args, **kwargs)
            if request.user.get_role_level() < min_level:
                return HttpResponseForbidden('Insufficient role level.')
            return view_func(request, *args, **kwargs)
        return _wrapped
    return decorator


def permission_required(perm_key):
    """Decorator that checks a specific permission via Role.has_permission().

    Returns 403 if the user's role does not grant the permission.
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return HttpResponseForbidden('Authentication required.')
            if not request.user.is_superuser and not request.user.has_role_permission(perm_key):
                return HttpResponseForbidden('Permission denied.')
            return view_func(request, *args, **kwargs)
        return _wrapped
    return decorator
