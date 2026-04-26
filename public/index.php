<?php
declare(strict_types=1);

/**
 * Application Entry Point
 *
 * This is the single authoritative bootstrap for the entire application.
 * All HTTP requests are funnelled here via Nginx try_files.
 *
 * Pipeline (in order):
 *   1. Autoload + .env
 *   2. Error reporting configuration
 *   3. Session hardening + start (Redis with file fallback)
 *   4. Route matching
 *   5. Middleware pipeline (SecurityHeaders → Auth → CSRF → Controller)
 *   6. SecurityHeadersMiddleware applied to ALL responses globally
 *   7. Response send
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use App\Middleware\Pipeline;
use App\Middleware\SecurityHeadersMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Load helpers after env is available
require __DIR__ . '/../app/Support/helpers.php';

// ── Sentry error tracking (if SENTRY_DSN is set) ─────────────────
\App\Services\ErrorTracker::register();

// ── Error handling ────────────────────────────────────────────────
$isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../storage/logs/php-errors.log');
}

// ── Session hardening ─────────────────────────────────────────────
// UPGRADE: Set security-focused cookie flags before session_start().
//   - cookie_httponly: JS cannot read the session cookie (XSS mitigation)
//   - cookie_samesite: Lax prevents CSRF from cross-site form submissions
//   - use_strict_mode: rejects unrecognised session IDs (session fixation guard)
//   - cookie_secure: HTTPS-only in production (SESSION_SECURE env flag)
$_sessionName = $_ENV['SESSION_NAME'] ?? '';
if ($_sessionName === '') {
    $appName      = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $_ENV['APP_NAME'] ?? 'app'));
    $_sessionName = $appName . '_session';
}

session_name($_sessionName);
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

$isSecure = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($isSecure) {
    ini_set('session.cookie_secure', '1');
}

$sessionOk = @session_start();
if (!$sessionOk) {
    error_log('[bootstrap] session_start() failed with redis handler — falling back to files');
    ini_set('session.save_handler', 'files');
    $sessDir = __DIR__ . '/../storage/cache/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0775, true);
    }
    ini_set('session.save_path', $sessDir);
    @session_start();
}

// ── Helper: render a styled error page ───────────────────────────
function renderErrorPage(int $statusCode, string $debugDetail = ''): Response
{
    $errorFile = __DIR__ . "/../app/Views/errors/{$statusCode}.php";
    if (!file_exists($errorFile)) {
        $errorFile = __DIR__ . '/../app/Views/errors/500.php';
    }

    $detail = $debugDetail; // available inside the error template via extract
    ob_start();
    require $errorFile;
    $body = ob_get_clean();

    return new Response(
        (string)$body,
        $statusCode,
        ['Content-Type' => 'text/html; charset=UTF-8']
    );
}

// ── Routing ───────────────────────────────────────────────────────
$request = Request::createFromGlobals();
$routes  = new RouteCollection();

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/admin.php';

$context = new RequestContext();
$context->fromRequest($request);
$matcher = new UrlMatcher($routes, $context);

try {
    $parameters = $matcher->match($request->getPathInfo());

    $controller = $parameters['_controller'];
    $middleware  = $parameters['_middleware'] ?? [];
    unset($parameters['_controller'], $parameters['_route'], $parameters['_middleware']);

    $controllerFn = function (Request $req) use ($controller, $parameters): Response {
        $response = call_user_func_array($controller, array_values($parameters));
        if (!$response instanceof Response) {
            return new Response(
                'Internal Server Error: controller did not return a Response.',
                500,
                ['Content-Type' => 'text/plain']
            );
        }
        return $response;
    };

    $response = !empty($middleware)
        ? Pipeline::run($request, $middleware, $controllerFn)
        : $controllerFn($request);

} catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
    $response = renderErrorPage(404);

} catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException $e) {
    $allowed  = implode(', ', $e->getAllowedMethods());
    $response = renderErrorPage(
        404,
        $isDebug ? "Method not allowed. Allowed: {$allowed}" : ''
    );

} catch (Throwable $e) {
    \App\Services\ErrorTracker::capture($e);
    $safeMsg  = preg_replace('/[\r\n\x00]/', ' ', $e->getMessage());
    $safeFile = preg_replace('/[\r\n\x00]/', '', $e->getFile());
    $debugInfo = $safeMsg . "\n"
               . $safeFile . ':' . $e->getLine() . "\n"
               . $e->getTraceAsString();
    error_log('[bootstrap] ' . $safeMsg . ' in ' . $safeFile . ':' . $e->getLine());
    $response = renderErrorPage(500, $isDebug ? $debugInfo : '');
}

// ── GLOBAL: Apply security headers to every response ─────────────
// SecurityHeadersMiddleware is applied here (outside the route middleware
// pipeline) so it covers 404s, 405s, and 500s too — not just matched routes.
// This closes audit finding S-03 completely.
$response = (new SecurityHeadersMiddleware())->handle($request, fn(Request $r) => $response);

// ── Cache-control: default no-cache for all dynamic responses ─────
// SecurityHeadersMiddleware sets Cache-Control: no-store for admin routes.
// For public routes, allow downstream (Nginx) to override if needed, but
// default to no-store for HTML to prevent stale auth-state disclosure.
$contentType = $response->headers->get('Content-Type', '');
$isHtmlResponse = str_contains($contentType, 'text/html') || $contentType === '';
if ($isHtmlResponse && !$response->headers->has('Cache-Control')) {
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->headers->set('Pragma', 'no-cache');
}

$response->send();