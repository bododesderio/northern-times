<?php
declare(strict_types=1);

namespace App\Services;

final class Media
{
  public static function publicBase(): string
  {
    return rtrim($_ENV['MEDIA_PUBLIC_BASE'] ?? '/uploads', '/');
  }

  public static function uploadMaxBytes(): int
  {
    $mb = (int)($_ENV['MEDIA_UPLOAD_MAX_MB'] ?? 10);
    return max(1, $mb) * 1024 * 1024;
  }

  public static function storageRoot(): string
  {
    // /var/www/html/storage/uploads inside container
    return dirname(__DIR__, 2) . '/storage/uploads';
  }

  public static function classify(string $mime): string
  {
    if (str_starts_with($mime, 'image/')) return 'image';
    if (str_starts_with($mime, 'video/')) return 'video';
    if (str_starts_with($mime, 'audio/')) return 'audio';
    if ($mime === 'application/pdf') return 'document';
    return 'other';
  }

  public static function sha256(string $tmpPath): string
  {
    return hash_file('sha256', $tmpPath) ?: bin2hex(random_bytes(32));
  }

  public static function safeFileName(string $original): string
  {
    $original = trim($original);
    $original = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original) ?? 'file';
    $original = preg_replace('/-+/', '-', $original) ?? 'file';
    return trim($original, '-');
  }

  public static function ensureDir(string $path): void
  {
    if (!is_dir($path)) {
      mkdir($path, 0775, true);
    }
  }

  /**
   * Stores upload; returns array for DB insert.
   */
  public static function storeUpload(array $file, int $uploadedBy, string $folder): array
  {
    if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error'])) {
      throw new \RuntimeException('Invalid upload payload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
      throw new \RuntimeException('Upload failed (code ' . (int)$file['error'] . ').');
    }
    if ((int)$file['size'] > self::uploadMaxBytes()) {
      throw new \RuntimeException('File too large. Max ' . (int)(self::uploadMaxBytes()/1024/1024) . 'MB.');
    }

    $tmp = (string)$file['tmp_name'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);

    // basic allowlist
    $allowed = [
      'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
      'application/pdf',
      'video/mp4','video/webm','video/quicktime',
      'audio/mpeg','audio/wav','audio/ogg',
    ];
    if (!in_array($mime, $allowed, true)) {
      throw new \RuntimeException("File type not allowed: {$mime}");
    }

    $sha = self::sha256($tmp);

    // dedupe check
    $pdo = DB::pdo();
    $check = $pdo->prepare("SELECT id, public_url FROM media_library WHERE sha256=:h LIMIT 1");
    $check->execute([':h' => $sha]);
    $existing = $check->fetch();
    if ($existing) {
      throw new \RuntimeException('Duplicate detected. Already exists: ' . ($existing['public_url'] ?? ''));
    }

    $type = self::classify($mime);

    $folder = trim($folder) !== '' ? trim($folder) : ($_ENV['MEDIA_DEFAULT_FOLDER'] ?? 'Articles');
    $folder = preg_replace('/[^A-Za-z0-9 _-]+/', '', $folder) ?? 'Articles';

    $datePath = date('Y/m');
    $baseDir = self::storageRoot() . '/' . $folder . '/' . $datePath;
    self::ensureDir($baseDir);

    $safeName = self::safeFileName((string)$file['name']);
    $ext = pathinfo($safeName, PATHINFO_EXTENSION);
    $stem = pathinfo($safeName, PATHINFO_FILENAME);
    if ($stem === '') $stem = 'file';

    $unique = $stem . '-' . substr($sha, 0, 10);
    $finalName = $ext ? ($unique . '.' . strtolower($ext)) : $unique;

    $destAbs = $baseDir . '/' . $finalName;
    if (!move_uploaded_file($tmp, $destAbs)) {
      throw new \RuntimeException('Failed to move uploaded file.');
    }

    $relPath = $folder . '/' . $datePath . '/' . $finalName;
    $public = self::publicBase() . '/' . str_replace('\\', '/', $relPath);

    $thumbUrl = null;
    $webpUrl  = null;
    $w = null; $h = null;

    if ($type === 'image') {
      // SVG: skip raster processing
      if ($mime !== 'image/svg+xml') {
        [$w, $h] = self::imageDimensions($destAbs);

        // thumbnail (600px wide)
        $thumbAbs = $baseDir . '/' . $unique . '-thumb.jpg';
        self::makeJpegThumb($destAbs, $thumbAbs, 600);
        $thumbRel = $folder . '/' . $datePath . '/' . basename($thumbAbs);
        $thumbUrl = self::publicBase() . '/' . $thumbRel;

        // webp (if possible)
        $webpAbs = $baseDir . '/' . $unique . '.webp';
        if (self::makeWebp($destAbs, $webpAbs)) {
          $webpRel = $folder . '/' . $datePath . '/' . basename($webpAbs);
          $webpUrl = self::publicBase() . '/' . $webpRel;
        }
      }
    }

    return [
      'media_type' => $type,
      'source_type' => 'upload',
      'title' => $stem,
      'description' => null,
      'folder' => $folder,
      'tags' => null,
      'file_path' => $relPath,
      'public_url' => $public,
      'webp_url' => $webpUrl,
      'thumbnail_url' => $thumbUrl,
      'original_name' => (string)$file['name'],
      'mime_type' => $mime,
      'file_size' => (int)$file['size'],
      'sha256' => $sha,
      'width' => $w,
      'height' => $h,
      'uploaded_by' => $uploadedBy,
    ];
  }

  private static function imageDimensions(string $path): array
  {
    $info = @getimagesize($path);
    if (!$info) return [null, null];
    return [(int)$info[0], (int)$info[1]];
  }

  private static function loadImage(string $path)
  {
    $info = @getimagesize($path);
    if (!$info) return null;
    $mime = $info['mime'] ?? '';

    return match ($mime) {
      'image/jpeg' => @imagecreatefromjpeg($path),
      'image/png'  => @imagecreatefrompng($path),
      'image/gif'  => @imagecreatefromgif($path),
      'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
      default => null,
    };
  }

  private static function makeJpegThumb(string $src, string $dest, int $targetW): void
  {
    $img = self::loadImage($src);
    if (!$img) return;

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) return;

    $newW = min($targetW, $w);
    $newH = (int)round($h * ($newW / $w));

    $out = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($out, $img, 0,0,0,0, $newW,$newH, $w,$h);

    imagejpeg($out, $dest, 82);

    imagedestroy($out);
    imagedestroy($img);
  }

  private static function makeWebp(string $src, string $dest): bool
  {
    if (!function_exists('imagewebp')) return false;
    $img = self::loadImage($src);
    if (!$img) return false;

    $ok = imagewebp($img, $dest, 82);
    imagedestroy($img);
    return (bool)$ok;
  }

  public static function deleteMediaById(int $id): void
  {
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("SELECT * FROM media_library WHERE id=:id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $m = $stmt->fetch();
    if (!$m) return;

    // delete files (storage)
    self::deletePublicUrlFile($m['public_url'] ?? null);
    self::deletePublicUrlFile($m['thumbnail_url'] ?? null);
    self::deletePublicUrlFile($m['webp_url'] ?? null);

    $del = $pdo->prepare("DELETE FROM media_library WHERE id=:id");
    $del->execute([':id' => $id]);
  }

  private static function deletePublicUrlFile(?string $url): void
  {
    if (!$url) return;
    $base = self::publicBase(); // /uploads
    if (!str_starts_with($url, $base . '/')) return;

    $rel = ltrim(substr($url, strlen($base)), '/');
    $abs = self::storageRoot() . '/' . $rel;

    if (is_file($abs)) @unlink($abs);
  }
}