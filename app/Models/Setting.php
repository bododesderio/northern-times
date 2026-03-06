<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Setting model — site_settings table (key/value store).
 */
final class Setting extends BaseModel
{
    protected static string $table   = 'site_settings';
    protected static string $primaryKey = 'id';
    protected static string $orderBy = 'setting_group ASC, setting_key ASC';

    /**
     * Get a single setting value.
     */
    public static function get(string $key, string $default = ''): string
    {
        $row = self::findBy('setting_key', $key);
        return $row ? (string)$row['setting_value'] : $default;
    }

    /**
     * Set a single setting value (upsert).
     */
    public static function set(string $key, string $value, string $group = 'general'): void
    {
        self::execute("
            INSERT INTO site_settings (setting_key, setting_value, setting_group)
            VALUES (:key, :val, :grp)
            ON CONFLICT (setting_key)
            DO UPDATE SET setting_value = :val, setting_group = :grp
        ", [':key' => $key, ':val' => $value, ':grp' => $group]);
    }

    /**
     * All settings grouped for admin page.
     */
    public static function allGrouped(): array
    {
        $rows = self::all();
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['setting_group'] ?? 'general'][] = $r;
        }
        return $groups;
    }

    /**
     * All settings as flat key => value map.
     */
    public static function allFlat(): array
    {
        $rows = self::query("SELECT setting_key, setting_value FROM site_settings");
        $map = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = $r['setting_value'];
        }
        return $map;
    }

    /**
     * Bulk save from admin settings form.
     */
    public static function bulkSave(array $settings, string $group = 'general'): void
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value, setting_group)
                VALUES (:key, :val, :grp)
                ON CONFLICT (setting_key)
                DO UPDATE SET setting_value = EXCLUDED.setting_value, setting_group = EXCLUDED.setting_group
            ");
            foreach ($settings as $key => $value) {
                $stmt->execute([':key' => $key, ':val' => $value, ':grp' => $group]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}