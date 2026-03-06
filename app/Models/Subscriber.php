<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Subscriber model — newsletter_subscribers table.
 */
final class Subscriber extends BaseModel
{
    protected static string $table   = 'newsletter_subscribers';
    protected static string $orderBy = 'created_at DESC';

    /**
     * Subscribe an email (upsert — update name on conflict).
     * Generates unsub_token on new subscription.
     */
    public static function subscribe(string $email, ?string $name = null): void
    {
        self::execute("
            INSERT INTO newsletter_subscribers (email, name, unsub_token, created_at)
            VALUES (:email, :name, :token, NOW())
            ON CONFLICT (email)
            DO UPDATE SET name = COALESCE(EXCLUDED.name, newsletter_subscribers.name)
        ", [
            ':email' => mb_strtolower($email),
            ':name'  => $name,
            ':token' => bin2hex(random_bytes(32)),
        ]);
    }

    /**
     * Toggle status between active/inactive.
     */
    public static function toggleStatus(string $id): void
    {
        $row = self::find($id);
        if ($row) {
            $newStatus = ($row['status'] === 'active') ? 'inactive' : 'active';
            self::update($id, ['status' => $newStatus]);
        }
    }

    /**
     * Admin listing with search + pagination.
     */
    public static function adminList(int $page = 1, int $perPage = 50, string $q = ''): array
    {
        $where  = '1=1';
        $params = [];

        if ($q !== '') {
            $where        = "(email ILIKE :q OR name ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: $where,
            params: $params,
            orderBy: 'created_at DESC',
            selectSql: 'id, email, name, status, source, created_at'
        );
    }

    /**
     * Export all subscribers as array (for CSV).
     */
    public static function export(): array
    {
        return self::query(
            "SELECT email, name, status, source, created_at
             FROM newsletter_subscribers
             ORDER BY created_at DESC"
        );
    }

    /**
     * Dashboard counts.
     */
    public static function dashboardCounts(): array
    {
        $pdo = self::pdo();
        return [
            'total'  => (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn(),
            'active' => (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn(),
        ];
    }

    /**
     * Get all active subscribers (for newsletter send).
     */
    public static function activeSubscribers(): array
    {
        return self::query(
            "SELECT id, email, name, unsub_token
             FROM newsletter_subscribers
             WHERE status = 'active'
             ORDER BY created_at ASC"
        );
    }

    /**
     * Find subscriber by unsubscribe token.
     */
    public static function findByToken(string $token): ?array
    {
        return self::queryOne(
            "SELECT * FROM newsletter_subscribers WHERE unsub_token = :t",
            [':t' => $token]
        );
    }

    /**
     * Unsubscribe by token (sets status to inactive).
     */
    public static function unsubscribeByToken(string $token): bool
    {
        $rows = self::execute(
            "UPDATE newsletter_subscribers SET status = 'inactive', updated_at = NOW() WHERE unsub_token = :t AND status = 'active'",
            [':t' => $token]
        );
        return $rows > 0;
    }

    /**
     * Count of active subscribers.
     */
    public static function activeCount(): int
    {
        return (int)self::queryOne("SELECT COUNT(*) AS cnt FROM newsletter_subscribers WHERE status = 'active'")['cnt'];
    }
}