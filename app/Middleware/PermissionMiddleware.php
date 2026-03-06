<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\RBAC;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks JSON permissions from the roles table.
 *
 * Usage in routes:
 *   '_middleware' => ['auth', 'permission:articles.all']
 *   '_middleware' => ['auth', 'permission:settings.edit']
 *   '_middleware' => ['auth', 'permission:users.manage,roles.manage']  // ANY of these
 *
 * Supports comma-separated permissions (OR logic — user needs ANY one).
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var string[] Required permissions (user needs at least one) */
    private array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }

    public function handle(Request $request, \Closure $next): Response
    {
        // Parse comma-separated permissions from a single string
        $allPerms = [];
        foreach ($this->permissions as $p) {
            foreach (explode(',', $p) as $perm) {
                $perm = trim($perm);
                if ($perm !== '') $allPerms[] = $perm;
            }
        }

        if (empty($allPerms) || RBAC::canAny($allPerms)) {
            return $next($request);
        }

        return new Response(
            '<p style="font-family:sans-serif;padding:40px;font-size:18px">'
            . '403 — You do not have the required permission.</p>',
            403,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}