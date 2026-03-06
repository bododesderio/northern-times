<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Authentication middleware.
 *
 * Replaces manual `Auth::requireAuth()` calls in controllers.
 * If the user is not logged in, redirects to /admin/login.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            return new RedirectResponse('/admin/login', 302);
        }

        // Heartbeat: update session activity (throttled to once per minute)
        $lastBeat = $_SESSION['_session_beat'] ?? 0;
        if (time() - $lastBeat > 60) {
            try {
                $user = Auth::user();
                if ($user) {
                    \App\Models\ActiveSession::touch(session_id(), $user['id']);
                }
                $_SESSION['_session_beat'] = time();
            } catch (\Throwable $e) {
                // Never block requests for session tracking
            }
        }

        return $next($request);
    }
}