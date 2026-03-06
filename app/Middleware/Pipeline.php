<?php
declare(strict_types=1);

namespace App\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Pipeline — builds a nested call chain.
 *
 * Usage (in index.php):
 *   $response = Pipeline::run($request, $middlewareStack, $controllerClosure);
 *
 * The pipeline is built from the OUTSIDE in:
 *   middleware[0] wraps middleware[1] wraps ... wraps controller
 */
final class Pipeline
{
    /**
     * Registered middleware aliases → class names.
     *
     * Routes reference these aliases in their _middleware array.
     * Aliases can include parameters after a colon: 'role:super_admin'
     */
    private static array $aliases = [
        'auth'       => AuthMiddleware::class,
        'role'       => RoleMiddleware::class,
        'csrf'       => CsrfMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'visitor'    => VisitorMiddleware::class,
    ];

    /**
     * Register a custom alias (for plugins/extensions).
     */
    public static function alias(string $name, string $class): void
    {
        self::$aliases[$name] = $class;
    }

    /**
     * Run a stack of middleware, then the controller.
     *
     * @param Request  $request     The HTTP request
     * @param array    $stack       Middleware aliases: ['auth', 'role:super_admin', 'csrf']
     * @param \Closure $controller  The final handler: fn(Request) => Response
     */
    public static function run(Request $request, array $stack, \Closure $controller): Response
    {
        // Build the chain from inside-out
        $next = $controller;

        foreach (array_reverse($stack) as $alias) {
            $next = self::wrap(self::resolve($alias), $next);
        }

        return $next($request);
    }

    /**
     * Resolve an alias (possibly with params) to a middleware instance.
     *
     * 'auth'             → new AuthMiddleware()
     * 'role:super_admin' → new RoleMiddleware('super_admin')
     */
    private static function resolve(string $alias): MiddlewareInterface
    {
        $parts     = explode(':', $alias, 2);
        $name      = $parts[0];
        $parameter = $parts[1] ?? null;

        $class = self::$aliases[$name] ?? null;

        if ($class === null) {
            throw new \RuntimeException("Unknown middleware alias: {$name}");
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware class not found: {$class}");
        }

        if ($parameter !== null) {
            return new $class($parameter);
        }

        return new $class();
    }

    /**
     * Wrap a middleware around the next handler.
     */
    private static function wrap(MiddlewareInterface $middleware, \Closure $next): \Closure
    {
        return function (Request $request) use ($middleware, $next): Response {
            return $middleware->handle($request, $next);
        };
    }
}