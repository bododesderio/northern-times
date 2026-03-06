<?php
declare(strict_types=1);

namespace App\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeadersMiddleware
 *
 * Applies a production-grade set of HTTP security headers to every response.
 * Addresses the audit finding S-03: no X-Frame-Options, CSP, or nosniff headers
 * were present anywhere in the stack.
 *
 * Headers applied:
 *   • X-Frame-Options          — Prevents clickjacking (iFrame embedding by third parties)
 *   • X-Content-Type-Options   — Prevents MIME-type sniffing attacks
 *   • Referrer-Policy          — Controls how much referrer data is sent to external sites
 *   • Permissions-Policy       — Restricts access to browser features (camera, geolocation, etc.)
 *   • Content-Security-Policy  — Restricts what resources the browser may load
 *   • X-XSS-Protection         — Legacy IE/Edge XSS filter (belt-and-suspenders)
 *   • Cache-Control (admin)    — Prevents admin pages from being cached by browsers/proxies
 *
 * CSP Design Philosophy:
 *   The CSP is intentionally permissive on script-src for now ('self' + CDN + inline)
 *   because CKEditor, D3.js, and Chart.js rely on inline scripts and CDN delivery.
 *   A strict nonce-based CSP is the ideal end-state but requires view-layer changes.
 *   This middleware establishes the header foundation for progressive hardening.
 *
 * White-Label Note:
 *   APP_URL env var is used for CSP connect-src so the policy works regardless
 *   of what domain the platform is deployed on.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Trusted CDN origins used by the platform's JavaScript libraries.
     * CKEditor 5, D3.js, Chart.js all load from cdnjs.cloudflare.com.
     * Chart.js v4 and world-atlas GeoJSON load from cdn.jsdelivr.net.
     */
    private const TRUSTED_SCRIPT_CDNS = [
        'https://cdn.ckeditor.com',
        'https://cdnjs.cloudflare.com',
        'https://cdn.jsdelivr.net',      // Chart.js v4, world-atlas topojson
    ];

    /**
     * Trusted image origins: allow same-origin uploads and common external
     * image providers used by the crawler (hotlinked images from source sites).
     */
    private const TRUSTED_IMG_SRCS = [
        "'self'",
        'data:',
        'https:',          // crawler pulls images from arbitrary https sources
        'blob:',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        $path    = $request->getPathInfo();
        $isAdmin = str_starts_with($path, '/admin');
        $isApi   = str_starts_with($path, '/api');

        // ── X-Frame-Options ────────────────────────────────────────────
        // SAMEORIGIN allows embedding within the same domain (useful for
        // popup preview iframes in admin) but blocks external framing.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // ── X-Content-Type-Options ─────────────────────────────────────
        // Prevents browsers from MIME-sniffing a response away from the
        // declared Content-Type (e.g. treating a .jpg as executable script).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── Referrer-Policy ────────────────────────────────────────────
        // Send full URL within same origin (for analytics), send only
        // origin when crossing to HTTPS external sites, send nothing
        // when downgrading to HTTP (protects user privacy).
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        // ── Permissions-Policy ─────────────────────────────────────────
        // Restricts access to sensitive browser APIs the platform does not need.
        // push-notifications and geolocation are used, so those are 'self'.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), payment=(), usb=(), '
            . 'geolocation=(self), '
            . 'notifications=(self)'
        );

        // ── X-XSS-Protection (legacy) ──────────────────────────────────
        // Belt-and-suspenders for older browsers. Modern ones use CSP instead.
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // ── Content-Security-Policy ────────────────────────────────────
        if (!$isApi) {
            $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
            $scriptCdns = implode(' ', self::TRUSTED_SCRIPT_CDNS);
            $imgSrcs    = implode(' ', self::TRUSTED_IMG_SRCS);

            // font-src: Google Fonts + self for locally-served fonts
            // frame-src: self for popup preview iframes, youtube for embeds
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' {$scriptCdns}",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com {$scriptCdns}",
                "font-src 'self' https://fonts.gstatic.com data:",
                "img-src {$imgSrcs}",
                "connect-src 'self' {$appUrl} https://nominatim.openstreetmap.org https://ipapi.co https://cdn.jsdelivr.net",
                "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://www.instagram.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "upgrade-insecure-requests",
            ]);

            $response->headers->set('Content-Security-Policy', $csp);
        }

        // ── Admin-specific: prevent caching of sensitive pages ─────────
        // Browsers (and proxies) must not cache admin responses. This prevents
        // Back-button disclosure of sensitive admin data after logout.
        if ($isAdmin) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}