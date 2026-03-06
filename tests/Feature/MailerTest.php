<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mailer;
use Tests\DatabaseTestCase;

final class MailerTest extends DatabaseTestCase
{
    public function testQueueInsertsEmail(): void
    {
        $email = "queue-test-" . bin2hex(random_bytes(4)) . "@example.com";
        Mailer::queue($email, "Test Subject", "<p>Body</p>", "Body", "Tester");

        $row = static::$pdo->prepare("SELECT * FROM email_queue WHERE to_email = :e");
        $row->execute([":e" => $email]);
        $result = $row->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($result);
        $this->assertSame("Test Subject", $result["subject"]);
        $this->assertSame("pending", $result["status"]);
    }

    public function testQueueStats(): void
    {
        $stats = Mailer::queueStats();
        $this->assertArrayHasKey("pending", $stats);
        $this->assertArrayHasKey("sent", $stats);
        $this->assertArrayHasKey("failed", $stats);
    }

    public function testWelcomeEmailTemplate(): void
    {
        $html = Mailer::welcomeEmail("Alice", "abc123token");
        $this->assertStringContainsString("Alice", $html);
        $this->assertStringContainsString("abc123token", $html);
        $this->assertStringContainsString("Unsubscribe", $html);
    }

    public function testDigestEmailTemplate(): void
    {
        $articles = [
            ["title" => "Test Article", "slug" => "test", "excerpt" => "Excerpt", "featured_image" => "", "author" => "Bob"],
        ];
        $html = Mailer::digestEmail("Weekly Digest", $articles, "unsub123", "Hello readers!");
        $this->assertStringContainsString("Test Article", $html);
        $this->assertStringContainsString("Hello readers!", $html);
    }

    public function testPasswordResetTemplate(): void
    {
        $html = Mailer::passwordResetEmail("Bob", "https://example.com/reset?t=xyz");
        $this->assertStringContainsString("Bob", $html);
        $this->assertStringContainsString("Reset My Password", $html);
    }
}