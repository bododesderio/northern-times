<?php
declare(strict_types=1);

namespace App\Services;

final class Image
{
  /** Standard responsive breakpoints (widths in px) */
  public const SRCSET_WIDTHS = [320, 640, 960, 1280, 1920];

  /**
   * Resize/compress an uploaded image to WebP and return saved relative path.
   * - Keeps aspect ratio
   * - Converts jpeg/png/webp to webp
   *
   * @return array{path:string, width:int, height:int, bytes:int}
   */
  public static function saveResizedWebp(string $tmpPath, string $destDirAbs, string $baseNameNoExt, int $maxW = 1920, int $maxH = 1920, int $quality = 82): array
  {
    if (!is_dir($destDirAbs)) mkdir($destDirAbs, 0775, true);

    $info = @getimagesize($tmpPath);
    if (!$info || empty($info[0]) || empty($info[1])) {
      throw new \RuntimeException("Invalid image.");
    }

    [$w, $h] = [$info[0], $info[1]];
    $mime = $info['mime'] ?? '';

    // Load
    $src = match ($mime) {
      'image/jpeg' => @imagecreatefromjpeg($tmpPath),
      'image/png'  => @imagecreatefrompng($tmpPath),
      'image/webp' => @imagecreatefromwebp($tmpPath),
      default      => null,
    };

    if (!$src) throw new \RuntimeException("Unsupported image type: {$mime}");

    // Compute new dims
    $ratio = min($maxW / $w, $maxH / $h, 1);
    $nw = (int)max(1, floor($w * $ratio));
    $nh = (int)max(1, floor($h * $ratio));

    $dst = imagecreatetruecolor($nw, $nh);

    // Preserve transparency for PNG/WebP
    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $filename = $baseNameNoExt . '.webp';
    $abs = rtrim($destDirAbs, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!imagewebp($dst, $abs, $quality)) {
      imagedestroy($src);
      imagedestroy($dst);
      throw new \RuntimeException("Failed to save webp.");
    }

    imagedestroy($src);
    imagedestroy($dst);

    return [
      'path' => $filename,
      'width' => $nw,
      'height' => $nh,
      'bytes' => (int)filesize($abs),
    ];
  }

  /**
   * Generate a responsive variant of an image at a specific width.
   * Returns the public URL of the resized image, or null on failure.
   * Images are cached — subsequent calls return the cached path instantly.
   *
   * @param string $publicUrl  The original image's public URL (e.g. /uploads/Articles/2025/02/photo.webp)
   * @param int    $targetW    Desired width in pixels
   * @return string|null       Public URL of resized variant
   */
  public static function responsiveUrl(string $publicUrl, int $targetW): ?string
  {
    if (empty($publicUrl) || $targetW <= 0) return null;

    $storageRoot = dirname(__DIR__, 2) . '/storage/uploads';
    $mediaBase   = rtrim($_ENV['MEDIA_PUBLIC_BASE'] ?? '/uploads', '/');

    // Convert public URL to absolute filesystem path
    if (!str_starts_with($publicUrl, $mediaBase . '/')) return null;
    $relPath = ltrim(substr($publicUrl, strlen($mediaBase)), '/');
    if (str_contains($relPath, '..')) return null;
    $absPath = $storageRoot . '/' . $relPath;

    if (!is_file($absPath)) return null;

    // Build variant filename: photo-w640.webp
    $dir  = dirname($absPath);
    $ext  = pathinfo($absPath, PATHINFO_EXTENSION);
    $stem = pathinfo($absPath, PATHINFO_FILENAME);
    $variantName = $stem . '-w' . $targetW . '.webp';
    $variantAbs  = $dir . '/' . $variantName;

    // Return cached variant if it exists
    if (is_file($variantAbs)) {
      $variantRel = dirname($relPath) . '/' . $variantName;
      return $mediaBase . '/' . $variantRel;
    }

    // Check original dimensions — don't upscale
    $info = @getimagesize($absPath);
    if (!$info || $info[0] <= 0) return null;
    $origW = $info[0];
    $origH = $info[1];

    // If original is smaller than target, skip this size
    if ($origW <= $targetW) return null;

    try {
      $result = self::saveResizedWebp($absPath, $dir, $stem . '-w' . $targetW, $targetW, 9999, 80);
      $variantRel = dirname($relPath) . '/' . $result['path'];
      return $mediaBase . '/' . $variantRel;
    } catch (\Throwable) {
      return null;
    }
  }

  /**
   * Regenerate the thumbnail for a single image file.
   *
   * Called by AdminSystemController::regenerateThumbnails() to rebuild
   * all thumbnails in bulk. Produces a -thumb.jpg at 600px wide.
   *
   * @param string $absPath  Absolute filesystem path to the original image
   * @throws \RuntimeException if the image cannot be read or written
   */
  public static function regenerateThumbnail(string $absPath): void
  {
      if (!is_file($absPath)) {
          throw new \RuntimeException("File not found: {$absPath}");
      }

      $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

      // SVG files don't need raster thumbnails
      if ($ext === 'svg') {
          return;
      }

      $info = @getimagesize($absPath);
      if (!$info || empty($info[0])) {
          throw new \RuntimeException("Cannot read image dimensions: {$absPath}");
      }

      $mime = $info['mime'] ?? '';
      $src  = match ($mime) {
          'image/jpeg' => @imagecreatefromjpeg($absPath),
          'image/png'  => @imagecreatefrompng($absPath),
          'image/webp' => @imagecreatefromwebp($absPath),
          'image/gif'  => @imagecreatefromgif($absPath),
          default      => null,
      };

      if (!$src) {
          throw new \RuntimeException("Unsupported image type ({$mime}): {$absPath}");
      }

      [$origW, $origH] = [$info[0], $info[1]];

      // Thumbnail dimensions: 600px wide, proportional height, no upscaling
      $thumbW = min(600, $origW);
      $thumbH = (int)max(1, round($origH * ($thumbW / $origW)));

      $dst = imagecreatetruecolor($thumbW, $thumbH);
      imagealphablending($dst, false);
      imagesavealpha($dst, true);

      // White background for JPEG output
      $white = imagecolorallocate($dst, 255, 255, 255);
      imagefill($dst, 0, 0, $white);

      imagecopyresampled($dst, $src, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);
      imagedestroy($src);

      // Build thumbnail path: /path/to/image.webp → /path/to/image-thumb.jpg
      $dir      = dirname($absPath);
      $stem     = pathinfo($absPath, PATHINFO_FILENAME);
      // Strip any existing -thumb suffix to avoid double-thumbing
      $stem     = preg_replace('/-thumb$/', '', $stem);
      $thumbAbs = $dir . DIRECTORY_SEPARATOR . $stem . '-thumb.jpg';

      $ok = imagejpeg($dst, $thumbAbs, 85);
      imagedestroy($dst);

      if (!$ok) {
          throw new \RuntimeException("Failed to write thumbnail: {$thumbAbs}");
      }
  }

  /**
   * Get the public URL of a thumbnail for a given public image URL.
   * Generates the thumbnail on-demand if it doesn't exist yet.
   * Returns the original URL if thumbnail generation is not possible.
   *
   * @param string $publicUrl  Public URL like /uploads/Articles/photo.webp
   * @return string            Thumbnail URL or original if unavailable
   */
  public static function thumbnail(string $publicUrl): string
  {
      if (empty($publicUrl)) return $publicUrl;

      $storageRoot = dirname(__DIR__, 2) . '/storage';
      $mediaBase   = rtrim($_ENV['MEDIA_PUBLIC_BASE'] ?? '/uploads', '/');

      if (!str_starts_with($publicUrl, $mediaBase . '/')) return $publicUrl;

      $relPath  = ltrim(substr($publicUrl, strlen($mediaBase)), '/');
      if (str_contains($relPath, '..')) return $publicUrl;
      $absPath  = $storageRoot . '/uploads/' . $relPath;

      if (!is_file($absPath)) return $publicUrl;

      $dir      = dirname($absPath);
      $stem     = preg_replace('/-thumb$/', '', pathinfo($absPath, PATHINFO_FILENAME));
      $thumbAbs = $dir . DIRECTORY_SEPARATOR . $stem . '-thumb.jpg';
      $thumbRel = dirname($relPath) . '/' . $stem . '-thumb.jpg';
      $thumbUrl = $mediaBase . '/' . ltrim($thumbRel, '/');

      if (is_file($thumbAbs)) return $thumbUrl;

      try {
          self::regenerateThumbnail($absPath);
          return is_file($thumbAbs) ? $thumbUrl : $publicUrl;
      } catch (\Throwable) {
          return $publicUrl;
      }
  }


  /**
   * Build a srcset string for responsive images.
   *
   * @param string $publicUrl  Original image URL
   * @param int[]  $widths     Desired widths (defaults to SRCSET_WIDTHS)
   * @return string            srcset attribute value, e.g. "/uploads/.../photo-w640.webp 640w, ..."
   */
  public static function srcset(string $publicUrl, array $widths = []): string
  {
    if (empty($publicUrl)) return '';

    $widths = $widths ?: self::SRCSET_WIDTHS;
    $parts  = [];

    foreach ($widths as $w) {
      $url = self::responsiveUrl($publicUrl, (int)$w);
      if ($url) {
        $parts[] = $url . ' ' . $w . 'w';
      }
    }

    // Always include the original as the largest option
    $parts[] = $publicUrl . ' 1920w';

    return implode(', ', $parts);
  }
}