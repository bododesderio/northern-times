<?php
declare(strict_types=1);

namespace App\Models;

/**
 * StoryThread model — story_threads table.
 */
final class StoryThread extends BaseModel
{
    protected static string $table   = 'story_threads';
    protected static string $orderBy = 'title ASC';

    /**
     * Active threads for article form dropdown.
     */
    public static function activeDropdown(): array
    {
        return self::where(['is_active' => true], 'title ASC');
    }

    /**
     * Find thread by slug.
     */
    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /**
     * Find thread with its article count.
     */
    public static function findWithArticleCount(string $id): ?array
    {
        return self::queryOne("
            SELECT st.*,
                   (SELECT COUNT(*) FROM articles WHERE story_thread_id = st.id) AS article_count
            FROM story_threads st
            WHERE st.id = :id
        ", [':id' => $id]);
    }
}