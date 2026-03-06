<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\SiteVisitor;
use App\Services\GeoIP;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records unique daily visitors in the site_visitors table.
 *
 * Usage in routes: '_middleware' => ['visitor']
 * Or apply globally to all frontend routes in index.php.
 *
 * Uses session to avoid DB call on every page load.
 * One INSERT per IP per day (UPSERT via ON CONFLICT DO NOTHING).
 * Stores GeoIP coordinates for the reader heatmap.
 */
final class VisitorMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        // Only track if not already recorded this session
        if (empty($_SESSION['_visitor_recorded'])) {
            $ip        = $request->getClientIp() ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $userAgent = $request->headers->get('User-Agent');
            $firstPage = $request->getPathInfo();

            // Resolve geo location for heatmap
            $geo = null;
            try {
                $geo = GeoIP::lookup($ip);
            } catch (\Throwable) {
                // GeoIP failure should never block visitor tracking
            }

            SiteVisitor::record($ip, $userAgent, $firstPage, $geo);

            $_SESSION['_visitor_recorded'] = true;
        }

        return $next($request);
    }
}