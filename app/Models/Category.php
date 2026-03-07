<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Category model — categories table.
 */
final class Category extends BaseModel
{
    protected static string $table   = 'categories';
    protected static string $orderBy = 'sort_order ASC, name ASC';

    /**
     * All categories for dropdowns (id + name only).
     */
    public static function dropdown(): array
    {
        return self::query(
            "SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Categories visible in nav.
     */
    public static function navCategories(): array
    {
        return self::query(
            "SELECT id, name, slug
             FROM categories
             WHERE show_in_nav = TRUE
             ORDER BY sort_order ASC, name ASC
             LIMIT 7"
        );
    }

    /**
     * Sidebar categories — all enabled categories with live article counts.
     */
    public static function sidebarCategories(): array
    {
        return self::query("
            SELECT c.id, c.name, c.slug, c.sort_order,
                   COUNT(a.id) FILTER (WHERE a.status = 'published') AS article_count,
                   COUNT(a.id) FILTER (WHERE a.status = 'published' AND a.published_at >= NOW() - INTERVAL '24 hours') AS new_count
            FROM categories c
            LEFT JOIN articles a ON a.category_id = c.id
            WHERE c.show_in_sidebar = TRUE
            GROUP BY c.id, c.name, c.slug, c.sort_order
            ORDER BY c.sort_order ASC, c.name ASC
        ");
    }

    /**
     * ALL categories (for homepage sections — includes categories not in nav).
     */
    public static function allActive(): array
    {
        return self::query(
            "SELECT id, name, slug
             FROM categories
             ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Find by slug.
     */
    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /**
     * Slugs for name-slug dropdown labels.
     */
    public static function nameSlugList(): array
    {
        return self::query(
            "SELECT id, name, slug FROM categories ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Find or create a category by name. Returns the category ID.
     */
    public static function findOrCreate(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $slug = trim($slug, '-');

        // Check existing by slug
        $existing = self::queryOne(
            "SELECT id FROM categories WHERE slug = :slug",
            [':slug' => $slug]
        );
        if ($existing) return $existing['id'];

        // Check by name (case-insensitive)
        $existing = self::queryOne(
            "SELECT id FROM categories WHERE LOWER(name) = LOWER(:name)",
            [':name' => $name]
        );
        if ($existing) return $existing['id'];

        // Create new
        $row = self::queryOne(
            "INSERT INTO categories (name, slug, show_in_nav, show_in_sidebar, sort_order)
             VALUES (:name, :slug, false, true, 99)
             RETURNING id",
            [':name' => ucwords(strtolower($name)), ':slug' => $slug]
        );
        return $row['id'] ?? '';
    }

    /**
     * Dashboard: article count per category.
     */
    public static function articleCounts(): array
    {
        return self::query("
            SELECT c.name, c.slug,
                   COUNT(a.id) AS article_count
            FROM categories c
            LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
            GROUP BY c.id, c.name, c.slug
            ORDER BY c.sort_order ASC, c.name ASC
        ");
    }
}