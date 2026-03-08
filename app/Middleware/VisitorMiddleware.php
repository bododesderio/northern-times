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
            $userAgent = $request->headers->get('User-Agent') ?? '';
            $firstPage = $request->getPathInfo();

            // Resolve geo location for heatmap
            $geo = null;
            try {
                $geo = GeoIP::lookup($ip);
            } catch (\Throwable) {
                // GeoIP failure should never block visitor tracking
            }

            // Parse device info from user agent
            $device = self::parseUserAgent($userAgent);

            SiteVisitor::record($ip, $userAgent, $firstPage, $geo, $device);

            $_SESSION['_visitor_recorded'] = true;
        }

        return $next($request);
    }

    /**
     * Parse user agent string into device_type, browser, and OS.
     */
    private static function parseUserAgent(string $ua): array
    {
        $ua = strtolower($ua);

        // Device type
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/mobile|android.*mobile|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop|windows phone/i', $ua)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/bot|crawl|spider|slurp|wget|curl/i', $ua)) {
            $deviceType = 'bot';
        } else {
            $deviceType = 'desktop';
        }

        // Browser
        $browser = 'Other';
        if (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($ua, 'chrome') && !str_contains($ua, 'edg')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'safari') && !str_contains($ua, 'chrome')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'msie') || str_contains($ua, 'trident')) {
            $browser = 'IE';
        } elseif (str_contains($ua, 'samsung')) {
            $browser = 'Samsung Internet';
        } elseif (str_contains($ua, 'ucbrowser')) {
            $browser = 'UC Browser';
        } elseif (str_contains($ua, 'brave')) {
            $browser = 'Brave';
        }

        // OS
        $os = 'Other';
        if (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        } elseif (str_contains($ua, 'chromeos') || str_contains($ua, 'cros')) {
            $os = 'ChromeOS';
        }

        return [
            'device_type' => $deviceType,
            'browser'     => $browser,
            'os'          => $os,
        ];
    }
}
