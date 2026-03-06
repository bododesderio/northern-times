<?php
declare(strict_types=1);

namespace Tests;

use App\Services\DB;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests needing a real database.
 * Wraps every test in a transaction that rolls back afterwards.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$pdo = DB::pdo();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        parent::tearDown();
    }

    protected function createCategory(string $name = 'Test Category', string $slug = ''): array
    {
        $slug = $slug ?: 'test-cat-' . bin2hex(random_bytes(4));
        $stmt = self::$pdo->prepare("INSERT INTO categories (name, slug) VALUES (:n, :s) RETURNING *");
        $stmt->execute([':n' => $name, ':s' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function createUser(string $email = '', string $role = 'author'): array
    {
        $email = $email ?: 'test-' . bin2hex(random_bytes(4)) . '@test.com';
        $stmt = self::$pdo->prepare(
            "INSERT INTO users (username, email, password_hash, role, is_active)
             VALUES (:u, :e, :p, :r, TRUE) RETURNING *"
        );
        $stmt->execute([
            ':u' => 'user_' . bin2hex(random_bytes(4)),
            ':e' => $email,
            ':p' => password_hash('TestPass123!', PASSWORD_BCRYPT),
            ':r' => $role,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function createArticle(string $categoryId, string $authorId, string $status = 'draft'): array
    {
        $slug = 'test-article-' . bin2hex(random_bytes(4));
        $stmt = self::$pdo->prepare(
            "INSERT INTO articles (title, slug, content, excerpt, category_id, author_id, status, display_author)
             VALUES (:t, :s, :c, :e, :cat, :auth, :st, 'Test Author') RETURNING *"
        );
        $stmt->execute([
            ':t' => 'Test Article ' . substr($slug, -8),
            ':s' => $slug, ':c' => '<p>Body.</p>', ':e' => 'Excerpt.',
            ':cat' => $categoryId, ':auth' => $authorId, ':st' => $status,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function loginAs(array $user): void
    {
        $_SESSION['user'] = [
            'id' => $user['id'], 'email' => $user['email'],
            'username' => $user['username'], 'role' => $user['role'],
        ];
    }

    protected function logout(): void
    {
        unset($_SESSION['user']);
    }
}