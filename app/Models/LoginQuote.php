<?php
declare(strict_types=1);

namespace App\Models;

/**
 * LoginQuote — rotating quotes shown on the admin login page.
 */
final class LoginQuote extends BaseModel
{
    protected static string $table   = 'login_quotes';
    protected static string $orderBy = 'sort_order ASC, created_at ASC';

    /** All active quotes for the login page (lightweight). */
    public static function allActive(): array
    {
        return self::query(
            "SELECT id, quote, author FROM login_quotes WHERE is_active = TRUE ORDER BY sort_order ASC, created_at ASC"
        );
    }

    /** All quotes for the admin management page. */
    public static function allForAdmin(): array
    {
        return self::query(
            "SELECT * FROM login_quotes ORDER BY sort_order ASC, created_at ASC"
        );
    }

    /** Create a new quote. */
    public static function createQuote(string $quote, string $author, int $sortOrder = 0): ?array
    {
        $stmt = self::pdo()->prepare(
            "INSERT INTO login_quotes (quote, author, sort_order) VALUES (:q, :a, :s) RETURNING *"
        );
        $stmt->execute([':q' => $quote, ':a' => $author, ':s' => $sortOrder]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Update quote text, author, sort order. */
    public static function updateQuote(string $id, string $quote, string $author, int $sortOrder): void
    {
        self::execute(
            "UPDATE login_quotes SET quote = :q, author = :a, sort_order = :s WHERE id = :id",
            [':q' => $quote, ':a' => $author, ':s' => $sortOrder, ':id' => $id]
        );
    }

    /** Toggle active state. */
    public static function toggleActive(string $id): bool
    {
        self::execute(
            "UPDATE login_quotes SET is_active = NOT is_active WHERE id = :id",
            [':id' => $id]
        );
        $row = self::find($id);
        return (bool)($row['is_active'] ?? false);
    }
}