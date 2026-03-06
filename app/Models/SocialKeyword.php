<?php
declare(strict_types=1);

namespace App\Models;

final class SocialKeyword extends BaseModel
{
    protected static string $table   = 'social_monitor_keywords';
    protected static string $orderBy = 'keyword ASC';

    /** All active keywords. */
    public static function active(): array
    {
        return self::query(
            "SELECT * FROM social_monitor_keywords WHERE is_active = TRUE ORDER BY keyword ASC"
        );
    }

    /** Add a keyword. */
    public static function store(string $keyword, bool $isCompetitor = false): ?array
    {
        return self::queryOne(
            "INSERT INTO social_monitor_keywords (keyword, is_competitor)
             VALUES (:keyword, :comp)
             ON CONFLICT (LOWER(keyword)) DO UPDATE SET is_active = TRUE
             RETURNING *",
            [':keyword' => $keyword, ':comp' => $isCompetitor ? 'true' : 'false']
        );
    }

    /** Toggle active/inactive. */
    public static function toggleActive(int $id): void
    {
        self::execute(
            "UPDATE social_monitor_keywords SET is_active = NOT is_active WHERE id = :id",
            [':id' => $id]
        );
    }

    /** Remove a keyword. */
    public static function remove(int $id): void
    {
        self::execute("DELETE FROM social_monitor_keywords WHERE id = :id", [':id' => $id]);
    }
}