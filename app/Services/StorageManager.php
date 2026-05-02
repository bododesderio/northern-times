<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Unified storage abstraction — local filesystem or S3-compatible.
 *
 * Supports: AWS S3, Cloudflare R2 (recommended), DigitalOcean Spaces, MinIO.
 * Toggle via STORAGE_DRIVER env var (local|s3).
 *
 * Uses native PHP curl for S3 — no SDK dependency required.
 */
final class StorageManager
{
    private static ?string $driver = null;

    public static function driver(): string
    {
        if (self::$driver === null) {
            self::$driver = $_ENV['STORAGE_DRIVER'] ?? 'local';
        }
        return self::$driver;
    }

    public static function isS3(): bool
    {
        return self::driver() === 's3';
    }

    /**
     * Store a file. Returns the public URL.
     *
     * @param string $localPath Absolute path to the file on disk
     * @param string $remotePath Relative storage path (e.g. "Articles/2026/05/image.jpg")
     * @return string Public URL or local path
     */
    public static function put(string $localPath, string $remotePath): string
    {
        if (!self::isS3()) {
            return '/' . ltrim($remotePath, '/');
        }

        $prefix = $_ENV['S3_PATH_PREFIX'] ?? '';
        $key = $prefix ? trim($prefix, '/') . '/' . ltrim($remotePath, '/') : ltrim($remotePath, '/');

        $bucket = $_ENV['S3_BUCKET'] ?? '';
        $endpoint = rtrim($_ENV['S3_ENDPOINT'] ?? '', '/');
        $region = $_ENV['S3_REGION'] ?? 'auto';
        $accessKey = $_ENV['S3_KEY'] ?? '';
        $secretKey = $_ENV['S3_SECRET'] ?? '';

        $contentType = self::mimeType($localPath);
        $body = file_get_contents($localPath);
        if ($body === false) {
            throw new \RuntimeException("Cannot read file: {$localPath}");
        }

        $date = gmdate('Ymd\THis\Z');
        $dateShort = gmdate('Ymd');
        $host = parse_url($endpoint, PHP_URL_HOST);
        $url = "{$endpoint}/{$bucket}/{$key}";

        $payloadHash = hash('sha256', $body);

        // Canonical request
        $canonicalUri = '/' . $bucket . '/' . rawurlencode($key);
        $canonicalUri = str_replace('%2F', '/', $canonicalUri);
        $canonicalHeaders = "content-type:{$contentType}\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$date}\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "PUT\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        // String to sign
        $scope = "{$dateShort}/{$region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        // Signing key
        $kDate = hash_hmac('sha256', $dateShort, "AWS4{$secretKey}", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: {$contentType}",
                "Host: {$host}",
                "x-amz-content-sha256: {$payloadHash}",
                "x-amz-date: {$date}",
                "Authorization: {$auth}",
                "Cache-Control: public, max-age=31536000, immutable",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("StorageManager S3 PUT failed ({$httpCode}): " . substr((string)$response, 0, 500));
            throw new \RuntimeException("S3 upload failed: HTTP {$httpCode}");
        }

        // Return public URL
        $publicBase = rtrim($_ENV['S3_URL'] ?? $endpoint, '/');
        return "{$publicBase}/{$key}";
    }

    /**
     * Delete a file from storage.
     */
    public static function delete(string $remotePath): bool
    {
        if (!self::isS3()) {
            $storageRoot = dirname(__DIR__, 2) . '/storage/uploads/';
            $fullPath = $storageRoot . ltrim($remotePath, '/');
            return @unlink($fullPath);
        }

        $prefix = $_ENV['S3_PATH_PREFIX'] ?? '';
        $key = $prefix ? trim($prefix, '/') . '/' . ltrim($remotePath, '/') : ltrim($remotePath, '/');

        $bucket = $_ENV['S3_BUCKET'] ?? '';
        $endpoint = rtrim($_ENV['S3_ENDPOINT'] ?? '', '/');
        $region = $_ENV['S3_REGION'] ?? 'auto';
        $accessKey = $_ENV['S3_KEY'] ?? '';
        $secretKey = $_ENV['S3_SECRET'] ?? '';

        $date = gmdate('Ymd\THis\Z');
        $dateShort = gmdate('Ymd');
        $host = parse_url($endpoint, PHP_URL_HOST);
        $url = "{$endpoint}/{$bucket}/{$key}";

        $payloadHash = hash('sha256', '');
        $canonicalUri = '/' . $bucket . '/' . rawurlencode($key);
        $canonicalUri = str_replace('%2F', '/', $canonicalUri);
        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$date}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "DELETE\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $scope = "{$dateShort}/{$region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateShort, "AWS4{$secretKey}", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                "Host: {$host}",
                "x-amz-content-sha256: {$payloadHash}",
                "x-amz-date: {$date}",
                "Authorization: {$auth}",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Get the public URL for a stored file.
     */
    public static function url(string $path): string
    {
        if (!self::isS3() || str_starts_with($path, 'http')) {
            return $path;
        }
        $publicBase = rtrim($_ENV['S3_URL'] ?? $_ENV['S3_ENDPOINT'] ?? '', '/');
        $prefix = $_ENV['S3_PATH_PREFIX'] ?? '';
        $key = $prefix ? trim($prefix, '/') . '/' . ltrim($path, '/') : ltrim($path, '/');
        return "{$publicBase}/{$key}";
    }

    /**
     * Test connectivity to the configured S3 bucket.
     * Returns ['ok' => bool, 'message' => string]
     */
    public static function testConnection(): array
    {
        if (!self::isS3()) {
            $dir = dirname(__DIR__, 2) . '/storage/uploads/';
            return ['ok' => is_writable($dir), 'message' => is_writable($dir) ? 'Local storage writable' : 'storage/uploads not writable'];
        }

        try {
            // Try to upload a tiny test file
            $tmpFile = tempnam(sys_get_temp_dir(), 'nt_s3_');
            file_put_contents($tmpFile, 'test');
            $testKey = '.nt-connection-test-' . bin2hex(random_bytes(4));
            self::put($tmpFile, $testKey);
            self::delete($testKey);
            @unlink($tmpFile);
            return ['ok' => true, 'message' => 'S3 connection successful'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'S3 error: ' . $e->getMessage()];
        }
    }

    private static function mimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'avif'        => 'image/avif',
            'svg'         => 'image/svg+xml',
            'ico'         => 'image/x-icon',
            'pdf'         => 'application/pdf',
            'csv'         => 'text/csv',
            'json'        => 'application/json',
            default       => mime_content_type($path) ?: 'application/octet-stream',
        };
    }
}
