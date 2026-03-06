<?php
declare(strict_types=1);

namespace App\Models;

final class SeoAudit extends BaseModel
{
    protected static string $table   = 'seo_audits';
    protected static string $orderBy = 'started_at DESC';

    /** Start a new audit run. */
    public static function start(): int
    {
        $row = self::queryOne(
            "INSERT INTO seo_audits DEFAULT VALUES RETURNING id"
        );
        return (int)($row['id'] ?? 0);
    }

    /** Finish an audit run. */
    public static function finish(
        int $id, int $pages, int $issues, int $score,
        int $critical, int $warnings, int $passed,
        ?array $details = null, ?string $error = null
    ): void {
        self::execute(
            "UPDATE seo_audits SET
                finished_at   = NOW(),
                status        = :status,
                pages_scanned = :pages,
                issues_found  = :issues,
                health_score  = :score,
                critical_count = :critical,
                warning_count  = :warnings,
                passed_count   = :passed,
                details       = :details,
                error_message = :error
             WHERE id = :id",
            [
                ':id'       => $id,
                ':status'   => $error ? 'failed' : 'completed',
                ':pages'    => $pages,
                ':issues'   => $issues,
                ':score'    => $score,
                ':critical' => $critical,
                ':warnings' => $warnings,
                ':passed'   => $passed,
                ':details'  => $details ? json_encode($details) : null,
                ':error'    => $error,
            ]
        );
    }

    /** Get the latest completed audit. */
    public static function latest(): ?array
    {
        return self::queryOne(
            "SELECT * FROM seo_audits
             WHERE status = 'completed'
             ORDER BY started_at DESC LIMIT 1"
        );
    }

    /** Recent audits for listing. */
    public static function recent(int $limit = 20): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT * FROM seo_audits
             ORDER BY started_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Health score trend (last N audits). */
    public static function scoreTrend(int $limit = 10): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, health_score, started_at
             FROM seo_audits
             WHERE status = 'completed' AND health_score IS NOT NULL
             ORDER BY started_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}