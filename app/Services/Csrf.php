<?php
declare(strict_types=1);

namespace App\Services;

final class Csrf
{
    /**
     * Get the current CSRF token, generating one if not yet set.
     * Token is stored in the session and lives for the session lifetime.
     */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    /**
     * Validate a submitted CSRF token against the session token.
     * Uses hash_equals() for timing-safe comparison (prevents timing attacks).
     */
    public static function validate(?string $token): bool
    {
        if (!$token || empty($_SESSION['_csrf'])) return false;
        return hash_equals((string)$_SESSION['_csrf'], (string)$token);
    }

    /**
     * Rotate (replace) the CSRF token.
     *
     * FIX (Audit S-05): Must be called after login (session_regenerate_id)
     * so any pre-login CSRF token embedded in pages is invalidated.
     * Auth::attempt() calls this automatically.
     *
     * Also useful after any sensitive state change (password reset, role change).
     *
     * @return string  The new token
     */
    public static function rotate(): string
    {
        unset($_SESSION['_csrf']);
        return self::token();
    }

    /**
     * Alias for rotate() — explicit name for post-login use.
     */
    public static function regenerate(): string
    {
        return self::rotate();
    }

    /**
     * Validate or abort with a 419 response.
     *
     * UPGRADE: Uses output buffering and a clean response rather than
     * raw echo + exit, so it can be caught in tests.
     */
    public static function requireValid(?string $token): void
    {
        if (!self::validate($token)) {
            // Clear any buffered output to avoid leaking partial page content
            if (ob_get_level() > 0) ob_end_clean();

            http_response_code(419);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '419 CSRF token mismatch. Please refresh and try again.';
            exit;
        }
    }
}