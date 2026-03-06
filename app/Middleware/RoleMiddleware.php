<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth;
use App\Services\RBAC;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access middleware.
 *
 * Usage in routes:
 *   'role:super_admin' — only super_admin can access
 *   'role:editor'      — editor + super_admin can access
 *
 * Now also respects JSON permissions:
 *   - A custom role with wildcard '*' passes any role check.
 *   - System roles use the level hierarchy as before.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    private string $requiredRole;

    public function __construct(string $requiredRole = 'editor')
    {
        $this->requiredRole = $requiredRole;
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->forbidden();
        }

        // Wildcard permission always passes
        if (RBAC::can('*')) {
            return $next($request);
        }

        // Level hierarchy check
        $userLevel     = RBAC::roleLevel($user['role']);
        $requiredLevel = RBAC::roleLevel($this->requiredRole);

        if ($userLevel >= $requiredLevel && $requiredLevel > 0) {
            return $next($request);
        }

        return $this->forbidden();
    }

    private function forbidden(): Response
    {
        $errorPage = __DIR__ . '/../Views/errors/403.php';
        if (file_exists($errorPage)) {
            ob_start();
            require $errorPage;
            $body = ob_get_clean();
            return new Response($body, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return new Response('403 Forbidden', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}