<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\MediaItem;

/**
 * Downloads remote images and stores them locally.
 * Uses ImageMagick CLI for thumbnail creation — supports ALL image formats
 * (AVIF, WebP, HEIC, TIFF, SVG, BMP, ICO, JP2, JXL, etc.)
 *
 * Fallback chain: ImageMagick CLI → PHP Imagick extension → PHP GD
 */
final class ImageDownloader
{
    public const UPLOAD_DIR = 'Crawled';
    private const TIMEOUT    = 25;
    private const MAX_RETRIES = 2;
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB max download
    private const MAX_WIDTH  = 1920;  // Resize images wider than this
    private const MAX_HEIGHT = 1920;  // Resize images taller than this
    private const JPEG_QUALITY = 82;

    /** All image MIME types we accept. If it's image/*, we download it. */
    private const MIME_TO_EXT = [
        'image/jpeg'                => 'jpg',
        'image/png'                 => 'png',
        'image/gif'                 => 'gif',
        'image/webp'                => 'webp',
        'image/avif'                => 'avif',
        'image/heic'                => 'heic',
        'image/heif'                => 'heif',
        'image/tiff'                => 'tiff',
        'image/bmp'                 => 'bmp',
        'image/x-ms-bmp'            => 'bmp',
        'image/svg+xml'             => 'svg',
        'image/jp2'                 => 'jp2',
        'image/jxl'                 => 'jxl',
        'image/x-icon'              => 'ico',
        'image/vnd.microsoft.icon'  => 'ico',
        'image/apng'                => 'apng',
    ];

    private static ?bool $hasConvert = null;
    private static ?bool $hasImagick = null;

    /**
     * Download image, store locally, return local URL. Falls back to original URL on failure.
     */
    public static function download(string $url, ?string $authorId = null): string
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) return $url;
        if (str_starts_with($url, '/uploads/') || str_starts_with($url, '/storage/')) return $url;

        // Block SSRF: reject private/reserved IP ranges + pin DNS
        $host = parse_url($url, PHP_URL_HOST);
        $resolvedIp = null;
        if ($host) {
            $ip = @gethostbyname($host);
            // gethostbyname returns the original hostname on failure — detect that
            if (!$ip || $ip === $host || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return $url;
            }
            $resolvedIp = $ip;
        }

        try {
            // Dedup by URL hash
            $urlHash = hash('sha256', $url);
            $existing = MediaItem::queryOne(
                "SELECT public_url FROM media_library WHERE sha256 = :sha LIMIT 1",
                [':sha' => $urlHash]
            );
            if ($existing && !empty($existing['public_url'])) return $existing['public_url'];

            // Fetch with 2 retry attempts
            $imageData = self::fetchImage($url);
            if ($imageData === null) return $url;

            // Detect MIME — accept any image/*
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->buffer($imageData);
            if (!str_starts_with($mime, 'image/')) return $url;

            // Reject SVGs (potential XSS vector) and ICO (useless for articles)
            if (in_array($mime, ['image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'])) return $url;

            $ext = self::MIME_TO_EXT[$mime] ?? self::guessExtension($url, $mime);

            // Storage directory
            $monthDir    = date('Y-m');
            $storageBase = realpath(__DIR__ . '/../../storage/uploads') ?: (__DIR__ . '/../../storage/uploads');
            $fullDir     = $storageBase . '/' . self::UPLOAD_DIR . '/' . $monthDir;
            if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

            // Save file locally first (needed for thumbnails/resize)
            $fileHash  = substr(hash('sha256', $imageData), 0, 16);
            $filename  = $fileHash . '_' . time() . '.' . $ext;
            $filePath  = $fullDir . '/' . $filename;
            $relativePath = 'uploads/' . self::UPLOAD_DIR . '/' . $monthDir . '/' . $filename;
            $publicUrl = '/' . $relativePath;
            file_put_contents($filePath, $imageData);

            // Get dimensions — try PHP first, then ImageMagick
            $width = $height = null;
            $dimensions = @getimagesizefromstring($imageData);
            if ($dimensions) {
                $width  = $dimensions[0];
                $height = $dimensions[1];
            } elseif (self::hasConvert()) {
                [$width, $height] = self::identifyDimensions($filePath);
            }

            // Resize oversized images in-place (keeps same path)
            if ($width && $height && ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT)) {
                self::resizeInPlace($filePath, self::MAX_WIDTH, self::MAX_HEIGHT);
                // Re-read dimensions after resize
                $newDims = @getimagesize($filePath);
                if ($newDims) {
                    $width  = $newDims[0];
                    $height = $newDims[1];
                }
                // Update image data size
                $imageData = file_get_contents($filePath);
            }

            // Create thumbnail using best available backend
            $thumbnailUrl = null;
            if ($width && $height && $width > 640) {
                $thumbnailUrl = self::createThumbnail($filePath, $fullDir, $filename, 640);
            }

            // Upload to S3 if configured (after resize/thumbnail so we upload final version)
            if (StorageManager::isS3()) {
                try {
                    $publicUrl = StorageManager::put($filePath, $relativePath);
                    if ($thumbnailUrl) {
                        $thumbRelative = ltrim($thumbnailUrl, '/');
                        $thumbLocal = $storageBase . '/../' . $thumbRelative;
                        if (file_exists($thumbLocal)) {
                            $thumbnailUrl = StorageManager::put($thumbLocal, $thumbRelative);
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[ImageDownloader] S3 upload failed, using local: ' . $e->getMessage());
                }
            }

            // Register in media_library
            try {
                MediaItem::insertMedia([
                    'media_type'    => 'image',
                    'source_type'   => 'crawled',
                    'title'         => pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_FILENAME) ?: 'crawled-image',
                    'folder'        => 'Crawled',
                    'file_path'     => 'storage/uploads/' . self::UPLOAD_DIR . '/' . $monthDir . '/' . $filename,
                    'public_url'    => $publicUrl,
                    'webp_url'      => null,
                    'thumbnail_url' => $thumbnailUrl,
                    'original_name' => basename(parse_url($url, PHP_URL_PATH) ?: 'image.' . $ext),
                    'mime_type'     => $mime,
                    'file_size'     => strlen($imageData),
                    'sha256'        => $urlHash,
                    'width'         => $width,
                    'height'        => $height,
                    'uploaded_by'   => $authorId,
                ]);
            } catch (\Throwable $e) {
                error_log('[ImageDownloader] Media library insert failed: ' . $e->getMessage());
            }

            return $publicUrl;

        } catch (\Throwable $e) {
            error_log("ImageDownloader failed for {$url}: " . $e->getMessage());
            return $url;
        }
    }

    /**
     * Process all <img> tags in HTML, downloading each image.
     */
    public static function processContentImages(string $html, ?string $authorId = null): string
    {
        return preg_replace_callback('#<img([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)>#i', function ($m) use ($authorId) {
            $src = $m[2];
            if (str_starts_with($src, 'data:') || str_starts_with($src, '/uploads/')) return $m[0];
            $localUrl = self::download($src, $authorId);
            return '<img' . $m[1] . 'src="' . htmlspecialchars($localUrl) . '"' . $m[3] . '>';
        }, $html) ?? $html;
    }

    // ═══════════════════════════════════════════════════════════
    //  FETCHING — 2 attempts, 10MB size limit
    // ═══════════════════════════════════════════════════════════

    private static function fetchImage(string $url): ?string
    {
        // Resolve DNS for SSRF protection and pinning
        $host = parse_url($url, PHP_URL_HOST);
        $resolvedIp = null;
        if ($host) {
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                $resolvedIp = $ip;
            }
        }
        // Skip known ad/tracking URLs
        $adDomains = ['doubleclick.net', 'googlesyndication.com', 'googleads.g.doubleclick',
                      'facebook.com/tr', 'pixel.', 'beacon.', 'analytics.',
                      'scorecardresearch.com', 'quantserve.com', 'outbrain.com', 'taboola.com'];
        $urlLower = strtolower($url);
        foreach ($adDomains as $ad) {
            if (str_contains($urlLower, $ad)) return null;
        }

        // Skip tiny tracking pixel URLs (common patterns)
        if (preg_match('/[?&](?:w|width|sz|size)=(?:1|2|0)\b/i', $url)) return null;

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (compatible; ' . bot_name() . '/1.0)',
        ];

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT      => $userAgents[$attempt] ?? $userAgents[0],
                CURLOPT_SSL_VERIFYPEER => ($_ENV['VERIFY_SSL'] ?? 'true') !== 'false',
                CURLOPT_SSL_VERIFYHOST => ($_ENV['VERIFY_SSL'] ?? 'true') !== 'false' ? 2 : 0,
                CURLOPT_MAXFILESIZE    => self::MAX_FILE_SIZE,
                CURLOPT_HTTPHEADER     => [
                    'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer: ' . parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/',
                    'Accept-Language: en-US,en;q=0.9',
                    'X-Crawler-Identity: ' . \App\Services\RobotsChecker::userAgentFull(),
                ],
            ]);

            // Pin DNS resolution to prevent TOCTOU rebinding attacks
            if ($resolvedIp && $host) {
                curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:80:{$resolvedIp}", "{$host}:443:{$resolvedIp}"]);
            }

            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($data !== false && $code === 200 && strlen($data) >= 100 && strlen($data) <= self::MAX_FILE_SIZE) {
                return $data;
            }

            if ($attempt === 0) usleep(500000);
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════
    //  THUMBNAILS — ImageMagick CLI → Imagick ext → GD
    // ═══════════════════════════════════════════════════════════

    /**
     * Create thumbnail using best available backend.
     * Always outputs JPEG for universal browser compatibility.
     */
    private static function createThumbnail(
        string $sourcePath, string $dir, string $filename, int $maxWidth
    ): ?string {
        $thumbName = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $thumbPath = $dir . '/' . $thumbName;
        $monthDir  = basename($dir);
        $thumbUrl  = '/uploads/' . self::UPLOAD_DIR . '/' . $monthDir . '/' . $thumbName;

        // Backend 1: ImageMagick CLI (supports ALL formats)
        if (self::hasConvert()) {
            if (self::thumbConvert($sourcePath, $thumbPath, $maxWidth)) return $thumbUrl;
        }

        // Backend 2: PHP Imagick extension
        if (self::hasImagick()) {
            if (self::thumbImagick($sourcePath, $thumbPath, $maxWidth)) return $thumbUrl;
        }

        // Backend 3: PHP GD (JPEG/PNG/GIF/WebP only)
        if (self::thumbGD($sourcePath, $thumbPath, $maxWidth)) return $thumbUrl;

        return null;
    }

    /**
     * ImageMagick CLI: `convert input[0] -thumbnail WIDTHx -quality 82 -strip output.jpg`
     * Handles: AVIF, WebP, HEIC, HEIF, TIFF, SVG, BMP, JP2, JXL, ICO, PSD, EPS...
     */
    private static function thumbConvert(string $src, string $dst, int $maxWidth): bool
    {
        try {
            $maxWidth = max(1, min($maxWidth, 2000)); // Constrain to safe range
            $cmd = self::getConvertCmd();
            $srcE = escapeshellarg($src . '[0]'); // [0] = first frame/page only
            $dstE = escapeshellarg($dst);
            exec("{$cmd} {$srcE} -thumbnail {$maxWidth}x -quality 82 -strip -colorspace sRGB {$dstE} 2>&1", $out, $code);
            return $code === 0 && file_exists($dst) && filesize($dst) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * PHP Imagick extension
     */
    private static function thumbImagick(string $src, string $dst, int $maxWidth): bool
    {
        try {
            $im = new \Imagick();
            $im->readImage($src . '[0]');
            $im->setImageFormat('jpeg');
            $im->thumbnailImage($maxWidth, 0);
            $im->setImageCompressionQuality(82);
            $im->stripImage();
            $im->writeImage($dst);
            $im->destroy();
            return file_exists($dst) && filesize($dst) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * PHP GD — limited to formats GD supports (JPEG, PNG, GIF, WebP)
     */
    private static function thumbGD(string $src, string $dst, int $maxWidth): bool
    {
        try {
            $data = file_get_contents($src);
            if (!$data) return false;

            $source = @imagecreatefromstring($data);
            if (!$source) return false;

            $origW = imagesx($source);
            $origH = imagesy($source);
            if ($origW <= $maxWidth) { imagedestroy($source); return false; }

            $newW  = $maxWidth;
            $newH  = (int)round($origH * ($maxWidth / $origW));
            $thumb = imagecreatetruecolor($newW, $newH);

            // Transparency preservation
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($src);
            if ($mime === 'image/png' || $mime === 'image/gif') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $t = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
                imagefilledrectangle($thumb, 0, 0, $newW, $newH, $t);
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagejpeg($thumb, $dst, 82);
            imagedestroy($source);
            imagedestroy($thumb);

            return file_exists($dst) && filesize($dst) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  RESIZING — downscale oversized images in-place
    // ═══════════════════════════════════════════════════════════

    /**
     * Resize an image in-place if it exceeds max dimensions.
     * Converts to JPEG for consistency and smaller file size.
     */
    private static function resizeInPlace(string $filePath, int $maxW, int $maxH): bool
    {
        // Backend 1: ImageMagick CLI (best quality, handles all formats)
        if (self::hasConvert()) {
            $cmd = self::getConvertCmd();
            $src = escapeshellarg($filePath . '[0]');
            $dst = escapeshellarg($filePath);
            exec("{$cmd} {$src} -resize {$maxW}x{$maxH}\\> -quality " . self::JPEG_QUALITY . " -strip -colorspace sRGB {$dst} 2>&1", $out, $code);
            return $code === 0;
        }

        // Backend 2: PHP GD
        try {
            $data = file_get_contents($filePath);
            if (!$data) return false;
            $source = @imagecreatefromstring($data);
            if (!$source) return false;

            $origW = imagesx($source);
            $origH = imagesy($source);

            if ($origW <= $maxW && $origH <= $maxH) {
                imagedestroy($source);
                return true; // Already within limits
            }

            // Calculate new dimensions maintaining aspect ratio
            $ratio = min($maxW / $origW, $maxH / $origH);
            $newW  = (int)round($origW * $ratio);
            $newH  = (int)round($origH * $ratio);

            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagejpeg($resized, $filePath, self::JPEG_QUALITY);
            imagedestroy($source);
            imagedestroy($resized);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  UTILITY
    // ═══════════════════════════════════════════════════════════

    /** Get dimensions via `identify` for formats PHP can't read */
    private static function identifyDimensions(string $path): array
    {
        try {
            $cmd = self::getIdentifyCmd();
            $p = escapeshellarg($path . '[0]');
            exec("{$cmd} -format '%wx%h' {$p} 2>/dev/null", $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $parts = explode('x', trim($out[0], "'\" \n"));
                if (count($parts) === 2) return [(int)$parts[0], (int)$parts[1]];
            }
        } catch (\Throwable) {}
        return [null, null];
    }

    private static function hasConvert(): bool
    {
        if (self::$hasConvert !== null) return self::$hasConvert;
        exec('which magick 2>/dev/null', $o1, $c1);
        if ($c1 === 0) { self::$hasConvert = true; return true; }
        exec('which convert 2>/dev/null', $o2, $c2);
        self::$hasConvert = ($c2 === 0);
        return self::$hasConvert;
    }

    private static function hasImagick(): bool
    {
        if (self::$hasImagick !== null) return self::$hasImagick;
        self::$hasImagick = class_exists('\Imagick');
        return self::$hasImagick;
    }

    private static function getConvertCmd(): string
    {
        exec('which magick 2>/dev/null', $o, $c);
        return $c === 0 ? 'magick' : 'convert';
    }

    private static function getIdentifyCmd(): string
    {
        exec('which magick 2>/dev/null', $o, $c);
        return $c === 0 ? 'magick identify' : 'identify';
    }

    private static function guessExtension(string $url, string $mime): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $urlExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($urlExt && strlen($urlExt) <= 5 && preg_match('/^[a-z0-9]+$/', $urlExt)) return $urlExt;
        $parts = explode('/', $mime);
        return str_replace(['x-', 'vnd.', 'microsoft.'], '', $parts[1] ?? 'bin');
    }
}