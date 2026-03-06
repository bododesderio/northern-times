<?php
declare(strict_types=1);
namespace App\Models;
final class PolicyPage extends BaseModel {
    protected static string $table = "policy_pages";
    protected static string $orderBy = "sort_order ASC, title ASC";
    public static function findBySlug(string $slug): ?array { return self::findBy("slug", $slug); }
    public static function findPublished(string $slug): ?array { return self::queryOne("SELECT * FROM policy_pages WHERE slug = :slug AND is_published = TRUE LIMIT 1", [":slug" => $slug]); }
    public static function activeList(): array { return self::where(["is_published" => true], "sort_order ASC, title ASC"); }
    public static function toggleActive(string $id): void { self::execute("UPDATE policy_pages SET is_published = NOT is_published, updated_at = NOW() WHERE id = :id", [":id" => $id]); }
}