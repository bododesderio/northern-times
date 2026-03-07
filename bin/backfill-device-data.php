<?php
/**
 * Backfill device_type, browser, and OS from stored user_agent strings.
 * Run once: php bin/backfill-device-data.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

require_once __DIR__ . '/../app/Support/helpers.php';

use App\Services\DB;
use App\Services\UserAgentParser;

$pdo = DB::pdo();

// Find rows with user_agent but missing device info
$rows = $pdo->query("
    SELECT id, user_agent
    FROM site_visitors
    WHERE user_agent IS NOT NULL
      AND user_agent != ''
      AND (device_type IS NULL OR browser IS NULL OR os IS NULL)
")->fetchAll(PDO::FETCH_ASSOC);

$count = count($rows);
echo "Found {$count} rows to backfill...\n";

if ($count === 0) {
    echo "Nothing to do.\n";
    exit(0);
}

$stmt = $pdo->prepare("
    UPDATE site_visitors
    SET device_type = :device_type, browser = :browser, os = :os
    WHERE id = :id
");

$updated = 0;
foreach ($rows as $row) {
    $parsed = UserAgentParser::parse($row['user_agent']);
    $stmt->execute([
        ':device_type' => $parsed['device_type'],
        ':browser'     => $parsed['browser'],
        ':os'          => $parsed['os'],
        ':id'          => $row['id'],
    ]);
    $updated++;

    if ($updated % 100 === 0) {
        echo "  Updated {$updated}/{$count}...\n";
    }
}

echo "Done. Updated {$updated} rows.\n";
