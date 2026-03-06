<?php
declare(strict_types=1);

namespace App\Services;

/**
 * RBAC — Role-Based Access Control with JSON permissions.
 *
 * Each role row has a `permissions` JSON column containing an array of
 * permission keys, e.g. ["articles.own","media.upload","comments.moderate"].
 * The wildcard "*" grants unrestricted access.
 *
 * System roles fall back to a hardcoded hierarchy:
 *   super_admin → level 3 (all permissions, wildcard)
 *   editor      → level 2 (content + moderate + settings)
 *   author      → level 1 (own articles + media upload)
 *
 * Custom roles rely entirely on the JSON permissions array.
 */
final class RBAC
{
    /** @var array<string,bool>|null Cached permission map for current user */
    private static ?array $permCache = null;

    /** @var array|null Cached role row from DB */
    private static ?array $roleRow = null;

    // ── System role hierarchy ───────────────────────────────────────

    private const LEVEL = [
        'author'      => 1,
        'editor'      => 2,
        'super_admin' => 3,
    ];

    /** Default permissions granted to system roles (fallback) */
    private const SYSTEM_PERMS = [
        'author' => [
            'articles.own', 'media.upload', 'comments.view',
        ],
        'editor' => [
            'articles.own', 'articles.all', 'articles.publish', 'articles.delete',
            'categories.manage',
            'media.upload', 'media.delete', 'media.manage',
            'comments.view', 'comments.moderate',
            'subscribers.view', 'subscribers.manage',
            'ads.view', 'ads.manage',
            'settings.view', 'settings.edit',
        ],
        'super_admin' => ['*'],
    ];

    // ── Permission loading ──────────────────────────────────────────

    /**
     * Load the permission set for the current user.
     * Cached per-request (static).
     *
     * @return array<string,bool>  permission key → true
     */
    private static function permissions(): array
    {
        if (self::$permCache !== null) return self::$permCache;

        $user = Auth::user();
        if (!$user) {
            self::$permCache = [];
            return self::$permCache;
        }

        $roleSlug = $user['role'] ?? 'author';

        // Try loading from DB
        $perms = self::loadFromDb($roleSlug);

        // If DB returned nothing, use system defaults
        if ($perms === null) {
            $perms = self::SYSTEM_PERMS[$roleSlug] ?? self::SYSTEM_PERMS['author'];
        }

        // Build lookup map
        $map = [];
        foreach ($perms as $p) {
            $map[$p] = true;
        }

        self::$permCache = $map;
        return self::$permCache;
    }

    /**
     * Load permissions from the roles table.
     * Returns null if role not found or permissions column is empty.
     */
    private static function loadFromDb(string $slug): ?array
    {
        try {
            if (self::$roleRow === null) {
                $pdo  = DB::pdo();
                $stmt = $pdo->prepare("SELECT permissions, is_system FROM roles WHERE slug = :slug LIMIT 1");
                $stmt->execute([':slug' => $slug]);
                self::$roleRow = $stmt->fetch() ?: [];
            }

            $row = self::$roleRow;
            if (empty($row) || empty($row['permissions'])) return null;

            $decoded = json_decode($row['permissions'], true);
            if (!is_array($decoded) || empty($decoded)) return null;

            return $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reset the permission cache (useful after role change in same request).
     */
    public static function reset(): void
    {
        self::$permCache = null;
        self::$roleRow   = null;
    }

    // ── Permission checks ───────────────────────────────────────────

    /**
     * Check if the current user has a specific permission.
     *
     * Supports:
     *   Exact match:     RBAC::can('articles.all')
     *   Global wildcard: '*' grants everything
     *   Namespace wildcard: 'articles.*' grants articles.all, articles.own, etc.
     */
    public static function can(string $permission): bool
    {
        $perms = self::permissions();
        // Global wildcard
        if (isset($perms['*'])) return true;
        // Exact match
        if (isset($perms[$permission])) return true;
        // Namespace wildcard: check if user has 'namespace.*' for the permission's namespace
        $dot = strpos($permission, '.');
        if ($dot !== false) {
            $ns = substr($permission, 0, $dot) . '.*';
            if (isset($perms[$ns])) return true;
        }
        return false;
    }

    /**
     * Check if the current user has ANY of the listed permissions.
     *
     * Example: RBAC::canAny(['articles.all', 'articles.own'])
     */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) return true;
        }
        return false;
    }

    /**
     * Check if the current user has ALL of the listed permissions.
     */
    public static function canAll(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (!self::can($p)) return false;
        }
        return true;
    }

    /**
     * Get all permission keys the current user has.
     * @return string[]
     */
    public static function userPermissions(): array
    {
        return array_keys(self::permissions());
    }

    // ── Role hierarchy checks (backward compatible) ─────────────────

    public static function isSuperAdmin(): bool
    {
        $u = Auth::user();
        return $u !== null && ($u['role'] === 'super_admin' || self::can('*'));
    }

    public static function isEditor(): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        $level = self::LEVEL[$u['role']] ?? 0;
        return $level >= self::LEVEL['editor'] || self::can('*');
    }

    public static function roleLevel(?string $slug = null): int
    {
        if ($slug === null) {
            $u = Auth::user();
            $slug = $u['role'] ?? 'author';
        }
        return self::LEVEL[$slug] ?? 0;
    }

    // ── Semantic permission helpers ─────────────────────────────────

    /** Editor+ can manage all content, OR anyone with articles.all */
    public static function canManageAll(): bool
    {
        return self::isEditor() || self::can('articles.all');
    }

    public static function canManageUsers(): bool
    {
        return self::isSuperAdmin() || self::can('users.manage');
    }

    public static function canManageSettings(): bool
    {
        return self::isEditor() || self::can('settings.edit');
    }

    public static function canDeleteAnyArticle(): bool
    {
        return self::isEditor() || self::can('articles.delete');
    }

    public static function canModerateComments(): bool
    {
        return self::isEditor() || self::can('comments.moderate');
    }

    public static function canManageMedia(): bool
    {
        return self::can('media.manage') || self::isEditor();
    }

    public static function canManageAds(): bool
    {
        return self::can('ads.manage') || self::isEditor();
    }

    public static function canManageSubscribers(): bool
    {
        return self::can('subscribers.manage') || self::isEditor();
    }

    /** Any authenticated user can edit their own; editors/super_admin edit any */
    public static function canEditArticle(array $articleRow): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        if (self::canManageAll()) return true;
        return isset($articleRow['author_id']) && $articleRow['author_id'] === $u['id'];
    }

    // ── Hard-stop guards (legacy — prefer middleware) ────────────────

    public static function requireSuperAdmin(): void
    {
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            echo '<p style="font-family:sans-serif;padding:40px;font-size:18px">403 — Insufficient permissions.</p>';
            exit;
        }
    }

    public static function requireEditor(): void
    {
        if (!self::isEditor()) {
            http_response_code(403);
            echo '<p style="font-family:sans-serif;padding:40px;font-size:18px">403 — Insufficient permissions.</p>';
            exit;
        }
    }

    /**
     * Hard-stop guard for any permission string.
     * Usage: RBAC::requirePermission('settings.edit');
     */
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            echo '<p style="font-family:sans-serif;padding:40px;font-size:18px">403 — You do not have the "' . htmlspecialchars($permission) . '" permission.</p>';
            exit;
        }
    }
}