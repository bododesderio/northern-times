<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * DB — singleton PDO connection manager.
 */
final class DB
{
    private static ?PDO $pdo = null;

    /**
     * Get the shared PDO instance (lazy-initialized).
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }
        return self::$pdo;
    }

    /**
     * Force a fresh connection (useful after caught PDO errors that leave
     * the connection in a broken state, e.g. failed transactions).
     */
    public static function reconnect(): PDO
    {
        self::$pdo = null;
        return self::pdo();
    }

    /**
     * Create a new PDO connection from config.
     */
    private static function connect(): PDO
    {
        $cfg = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s',
            $cfg['driver'] ?? 'pgsql',
            $cfg['host'] ?? 'db',
            $cfg['port'] ?? 5432,
            $cfg['database'] ?? 'northern_times'
        );

        $pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }

    /** Prevent instantiation */
    private function __construct() {}
}
