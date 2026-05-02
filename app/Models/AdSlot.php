<?php
declare(strict_types=1);

namespace App\Models;

/**
 * AdSlot model — ad_slots table.
 */
final class AdSlot extends BaseModel
{
    protected static string $table   = 'ad_slots';
    protected static string $orderBy = "CASE slot_name
        WHEN 'top-banner' THEN 1
        WHEN 'masthead-left' THEN 2
        WHEN 'masthead-right' THEN 3
        WHEN 'sidebar-left' THEN 4
        WHEN 'sidebar' THEN 5
        WHEN 'in-feed' THEN 6
        WHEN 'in-article' THEN 7
        WHEN 'below-article' THEN 8
        WHEN 'article-sidebar' THEN 9
        WHEN 'footer' THEN 10
        WHEN 'homepage-spotlight' THEN 11
        ELSE 12 END";

    /**
     * All slots for admin page (custom order).
     */
    public static function allOrdered(): array
    {
        return self::all();
    }

    /**
     * Active ads keyed by slot_name (for frontend rendering).
     * Includes device_target, dimensions, and custom CSS for responsive rendering.
     */
    public static function activeSlots(): array
    {
        $rows = self::query("
            SELECT slot_name, ad_type, content, content_tablet, content_mobile,
                   link_url, link_url_tablet, link_url_mobile,
                   device_target, max_width, max_height, custom_css, nofollow, alt_text
            FROM ad_slots
            WHERE is_active = TRUE
              AND (start_date IS NULL OR start_date <= NOW())
              AND (end_date IS NULL OR end_date >= NOW())
        ");

        $ads = [];
        foreach ($rows as $r) {
            $ads[$r['slot_name']] = $r;
        }
        return $ads;
    }

    /**
     * Toggle active state.
     */
    public static function toggleActive(string $id): void
    {
        self::execute(
            "UPDATE ad_slots SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Update an ad slot's content and settings.
     */
    public static function updateSlot(string $id, array $data): void
    {
        self::execute("
            UPDATE ad_slots SET
              ad_type         = :type,
              content         = :content,
              content_tablet  = :content_tablet,
              content_mobile  = :content_mobile,
              link_url        = :link,
              link_url_tablet = :link_tablet,
              link_url_mobile = :link_mobile,
              is_active       = :active,
              start_date      = :start,
              end_date        = :end,
              device_target   = :device,
              max_width       = :maxw,
              max_height      = :maxh,
              custom_css      = :css,
              nofollow        = :nofollow,
              alt_text        = :alt,
              updated_at      = NOW()
            WHERE id = :id
        ", [
            ':type'           => $data['ad_type'] ?? 'image',
            ':content'        => $data['content'] ?? '',
            ':content_tablet' => $data['content_tablet'] ?? '',
            ':content_mobile' => $data['content_mobile'] ?? '',
            ':link'           => $data['link_url'] ?? null,
            ':link_tablet'    => $data['link_url_tablet'] ?? '',
            ':link_mobile'    => $data['link_url_mobile'] ?? '',
            ':active'         => ($data['is_active'] ?? false) ? 'true' : 'false',
            ':start'          => $data['start_date'] ?? null,
            ':end'            => $data['end_date'] ?? null,
            ':device'         => $data['device_target'] ?? 'all',
            ':maxw'           => $data['max_width'] ?? null,
            ':maxh'           => $data['max_height'] ?? null,
            ':css'            => $data['custom_css'] ?? null,
            ':nofollow'       => ($data['nofollow'] ?? true) ? 'true' : 'false',
            ':alt'            => $data['alt_text'] ?? null,
            ':id'             => $id,
        ]);
    }

    /**
     * Increment click counter and return the link_url for redirect.
     */
    public static function recordClick(string $id): ?string
    {
        $row = self::queryOne(
            "UPDATE ad_slots SET clicks = clicks + 1, updated_at = NOW()
             WHERE id = :id AND is_active = TRUE
             RETURNING link_url",
            [':id' => $id]
        );
        return $row['link_url'] ?? null;
    }

    /**
     * Find an active ad slot by slot_name.
     */
    public static function findBySlotName(string $slotName): ?array
    {
        return self::queryOne(
            "SELECT * FROM ad_slots WHERE slot_name = :name LIMIT 1",
            [':name' => $slotName]
        );
    }
}