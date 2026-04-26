<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Lightweight Sentry error reporter — zero dependencies.
 *
 * Sends uncaught exceptions to Sentry via their HTTP envelope API.
 * No SDK required. Set SENTRY_DSN in .env to enable.
 *
 * Usage:
 *   ErrorTracker::register();          // installs global handler
 *   ErrorTracker::capture($exception); // manual report
 */
final class ErrorTracker
{
    private static ?string $dsn = null;
    private static ?string $key = null;
    private static ?string $projectId = null;
    private static ?string $host = null;
    private static bool $registered = false;

    /**
     * Register as global exception + shutdown handler.
     */
    public static function register(): void
    {
        if (self::$registered) return;
        self::$registered = true;

        if (!self::parseDsn()) return;

        set_exception_handler(function (\Throwable $e): void {
            self::capture($e);
            // Re-throw so normal error handling (error page) still works
            // We can't re-throw from exception handler, so log it
            error_log('[ErrorTracker] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::capture(new \ErrorException(
                    $error['message'], 0, $error['type'], $error['file'], $error['line']
                ));
            }
        });
    }

    /**
     * Send an exception to Sentry.
     */
    public static function capture(\Throwable $e): void
    {
        if (!self::$host || !self::$key || !self::$projectId) {
            if (!self::parseDsn()) return;
        }

        $eventId = bin2hex(random_bytes(16));
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        $frames = [];
        foreach ($e->getTrace() as $frame) {
            $frames[] = [
                'filename' => $frame['file'] ?? '<unknown>',
                'lineno'   => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '<unknown>',
            ];
        }
        // Sentry expects frames in caller-first order (reversed)
        $frames = array_reverse($frames);
        // Add the exception location as the last frame
        $frames[] = [
            'filename' => $e->getFile(),
            'lineno'   => $e->getLine(),
            'function' => '<throw>',
        ];

        $payload = [
            'event_id'  => $eventId,
            'timestamp' => $timestamp,
            'platform'  => 'php',
            'level'     => 'error',
            'server_name' => gethostname() ?: 'northern-times',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'release'     => $_ENV['APP_VERSION'] ?? '1.0.0',
            'exception'   => [
                'values' => [[
                    'type'  => get_class($e),
                    'value' => substr($e->getMessage(), 0, 1000),
                    'stacktrace' => ['frames' => $frames],
                ]],
            ],
            'tags' => [
                'php_version' => PHP_VERSION,
            ],
            'request' => self::captureRequest(),
        ];

        self::send($eventId, $payload);
    }

    private static function parseDsn(): bool
    {
        $dsn = $_ENV['SENTRY_DSN'] ?? '';
        if ($dsn === '') return false;

        // DSN format: https://<key>@<host>/<project_id>
        $parts = parse_url($dsn);
        if (!$parts || empty($parts['user']) || empty($parts['host']) || empty($parts['path'])) {
            return false;
        }

        self::$key = $parts['user'];
        self::$host = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        self::$projectId = ltrim($parts['path'], '/');
        self::$dsn = $dsn;

        return true;
    }

    private static function captureRequest(): array
    {
        if (PHP_SAPI === 'cli') {
            return ['url' => 'cli://' . implode(' ', $_SERVER['argv'] ?? ['unknown'])];
        }

        return [
            'url'     => ($_ENV['APP_URL'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'),
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'headers' => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ],
        ];
    }

    private static function send(string $eventId, array $payload): void
    {
        $url = self::$host . '/api/' . self::$projectId . '/envelope/';

        $envelope  = json_encode([
            'event_id' => $eventId,
            'sent_at'  => gmdate('Y-m-d\TH:i:s\Z'),
            'dsn'      => self::$dsn,
        ]) . "\n";
        $envelope .= json_encode(['type' => 'event']) . "\n";
        $envelope .= json_encode($payload);

        // Fire-and-forget — don't block the request
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-sentry-envelope\r\n"
                           . "X-Sentry-Auth: Sentry sentry_version=7, sentry_key=" . self::$key . "\r\n",
                'content' => $envelope,
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $ctx);
    }
}
