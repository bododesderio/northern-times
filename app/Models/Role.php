<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Role model — roles table.
 */
final class Role extends BaseModel
{
    protected static string $table   = 'roles';
    protected static string $orderBy = 'sort_order ASC, label ASC';

    /**
     * All roles with user counts.
     */
    public static function allWithUserCounts(): array
    {
        return self::query("
            SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role = r.slug) AS user_count
            FROM roles r
            ORDER BY r.sort_order ASC, r.label ASC
        ");
    }

    /**
     * Find role by slug.
     */
    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /**
     * Check if any users are assigned to a role.
     */
    public static function hasUsers(string $slug): bool
    {
        return (int)self::queryColumn(
            "SELECT COUNT(*) FROM users WHERE role = :slug",
            [':slug' => $slug]
        ) > 0;
    }

    /**
     * Dropdown list for user forms.
     */
    public static function dropdown(): array
    {
        return self::query(
            "SELECT slug, label FROM roles ORDER BY sort_order ASC, label ASC"
        );
    }
}