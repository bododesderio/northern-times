<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Csrf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF token validation middleware.
 *
 * Automatically validates `_csrf` on POST/PUT/PATCH/DELETE requests.
 * GET/HEAD/OPTIONS are passed through (safe methods).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /** Methods that modify state and need CSRF protection */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, \Closure $next): Response
    {
        if (in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            $token = $request->request->get('_csrf')
                  ?? $request->headers->get('X-CSRF-Token');

            if (!Csrf::validate($token)) {
                // Render the styled 419 error page
                $errorPage = __DIR__ . '/../Views/errors/419.php';
                if (file_exists($errorPage)) {
                    ob_start();
                    require $errorPage;
                    $body = ob_get_clean();
                    return new Response($body, 419, ['Content-Type' => 'text/html; charset=UTF-8']);
                }

                return new Response(
                    '419 CSRF token mismatch.',
                    419,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }
        }

        return $next($request);
    }
}