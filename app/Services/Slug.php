<?php
declare(strict_types=1);

namespace App\Services;

final class Slug
{
  public static function make(string $title): string
  {
    $slug = mb_strtolower(trim($title));
    $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $slug) ?? $slug; // keep letters/numbers
    $slug = preg_replace('/[\s-]+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'article';
  }

  /**
   * Generate a unique slug for a given table.
   *
   * @param string      $baseSlug   Base slug to start from
   * @param string|null $excludeId  Exclude this ID from collision check (for updates)
   * @param string      $table      Table to check against (default: articles)
   */
  public static function unique(string $baseSlug, ?string $excludeId = null, string $table = 'articles'): string
  {
    $pdo = DB::pdo();
    $slug = $baseSlug;
    $i = 2;

    // Whitelist allowed tables to prevent SQL injection
    $allowed = ['articles', 'popups', 'categories', 'tags'];
    if (!in_array($table, $allowed, true)) {
        $table = 'articles';
    }

    while (true) {
      if ($excludeId) {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = :s AND id <> :id LIMIT 1");
        $stmt->execute([':s' => $slug, ':id' => $excludeId]);
      } else {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = :s LIMIT 1");
        $stmt->execute([':s' => $slug]);
      }

      $exists = (bool)$stmt->fetchColumn();
      if (!$exists) return $slug;

      $slug = $baseSlug . '-' . $i;
      $i++;
    }
  }
}