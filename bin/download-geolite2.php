<?php
/**
 * Download MaxMind GeoLite2-City database.
 *
 * Requires a MaxMind license key. Get one free at:
 * https://www.maxmind.com/en/geolite2/signup
 *
 * Set MAXMIND_LICENSE_KEY in your .env file.
 *
 * Usage: php bin/download-geolite2.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$key = $_ENV['MAXMIND_LICENSE_KEY'] ?? '';
if ($key === '') {
    echo "ERROR: Set MAXMIND_LICENSE_KEY in your .env file.\n";
    echo "Get a free key at: https://www.maxmind.com/en/geolite2/signup\n";
    exit(1);
}

$edition = 'GeoLite2-City';
$url = "https://download.maxmind.com/app/geoip_download?edition_id={$edition}&license_key={$key}&suffix=tar.gz";
$dest = __DIR__ . '/../storage/geoip';
$tarFile = $dest . '/GeoLite2-City.tar.gz';
$mmdbFile = $dest . '/GeoLite2-City.mmdb';

echo "Downloading {$edition}...\n";

$ctx = stream_context_create(['http' => ['timeout' => 60]]);
$data = @file_get_contents($url, false, $ctx);

if ($data === false) {
    echo "ERROR: Download failed. Check your license key.\n";
    exit(1);
}

file_put_contents($tarFile, $data);
echo "Downloaded " . round(strlen($data) / 1024 / 1024, 1) . " MB\n";

// Extract .mmdb from tar.gz
echo "Extracting...\n";
$phar = new PharData($tarFile);
$phar->decompress(); // creates .tar

$tarOnly = str_replace('.tar.gz', '.tar', $tarFile);
$tar = new PharData($tarOnly);

foreach (new RecursiveIteratorIterator($tar) as $file) {
    if (str_ends_with($file->getPathname(), '.mmdb')) {
        file_put_contents($mmdbFile, file_get_contents($file->getPathname()));
        echo "Extracted to: {$mmdbFile}\n";
        break;
    }
}

// Cleanup
@unlink($tarFile);
@unlink($tarOnly);

if (file_exists($mmdbFile)) {
    echo "Done! GeoLite2-City.mmdb is ready (" . round(filesize($mmdbFile) / 1024 / 1024, 1) . " MB).\n";
} else {
    echo "ERROR: Could not extract .mmdb file.\n";
    exit(1);
}
