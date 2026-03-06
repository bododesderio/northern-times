<?php
declare(strict_types=1);

namespace App\Models;

final class ImageHealthLog extends BaseModel
{
    protected static string $table   = 'image_health_logs';
    protected static string $orderBy = 'checked_at DESC';

    /** Log a batch of image check results. */
    public static function logBatch(array $results): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO image_health_logs (article_id, image_url, http_status, is_broken, error_message)
             VALUES (:article_id, :image_url, :http_status, :is_broken, :error)"
        );

        foreach ($results as $r) {
            $stmt->execute([
                ':article_id'  => $r['article_id'],
                ':image_url'   => $r['image_url'],
                ':http_status' => $r['http_status'] ?? null,
                ':is_broken'   => ($r['is_broken'] ?? false) ? 'true' : 'false',
                ':error'       => $r['error_message'] ?? null,
            ]);
        }
    }

    /** Currently broken images (latest check per URL). */
    public static function brokenImages(int $limit = 100): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT DISTINCT ON (ihl.image_url)
                    ihl.*, a.title AS article_title, a.slug AS article_slug
             FROM image_health_logs ihl
             JOIN articles a ON a.id = ihl.article_id
             WHERE ihl.is_broken = TRUE
             ORDER BY ihl.image_url, ihl.checked_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Dashboard stats. */
    public static function stats(): array
    {
        return self::queryOne(
            "SELECT
                COUNT(DISTINCT image_url) AS total_checked,
                COUNT(DISTINCT image_url) FILTER (WHERE is_broken = TRUE) AS total_broken,
                COUNT(DISTINCT image_url) FILTER (WHERE replaced = TRUE) AS total_replaced,
                MAX(checked_at) AS last_check
             FROM image_health_logs
             WHERE checked_at > NOW() - INTERVAL '7 days'"
        ) ?: ['total_checked' => 0, 'total_broken' => 0, 'total_replaced' => 0, 'last_check' => null];
    }

    /** Mark an image as replaced with placeholder. */
    public static function markReplaced(string $imageUrl): void
    {
        self::execute(
            "UPDATE image_health_logs SET replaced = TRUE
             WHERE image_url = :url AND is_broken = TRUE AND replaced = FALSE",
            [':url' => $imageUrl]
        );
    }
}