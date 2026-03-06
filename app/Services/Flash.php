<?php
declare(strict_types=1);

namespace App\Services;

final class Flash
{
  public static function set(string $key, string $value): void
  {
    $_SESSION['_flash'][$key] = $value;
  }

  public static function get(string $key): ?string
  {
    if (!isset($_SESSION['_flash'][$key])) return null;
    $v = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $v;
  }
}