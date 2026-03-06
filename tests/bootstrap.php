<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads autoloader and environment so models/services work in tests.
 * For DB-dependent tests, the real database is used with transactions
 * that roll back after each test (see DatabaseTestCase).
 */

require __DIR__ . '/../vendor/autoload.php';

// Load .env (test can override with .env.testing if present)
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Ensure sessions work in CLI
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_handler', 'files');
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

// Load application helpers
$helpersFile = dirname(__DIR__) . '/app/Support/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
}

// Helper stubs — only if not loaded from app
if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('get_site_setting')) {
    function get_site_setting(string $key, string $default = ''): string {
        return $default;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string {
        return 'http://localhost:8080' . $path;
    }
}

if (!function_exists('excerpt')) {
    function excerpt(string $text, int $length = 160): string {
        $text = strip_tags($text);
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
}