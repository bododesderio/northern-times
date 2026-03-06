<?php
declare(strict_types=1);

namespace App\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware contract.
 *
 * Each middleware receives the request and a $next closure.
 * Call $next($request) to continue the pipeline, or return
 * a Response early to short-circuit (e.g. redirect to login).
 */
interface MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response;
}