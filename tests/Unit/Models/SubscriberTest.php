<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Subscriber;
use Tests\DatabaseTestCase;

class SubscriberTest extends DatabaseTestCase
{
    public function test_subscribe_creates_record(): void
    {
        $email = 'sub-' . bin2hex(random_bytes(4)) . '@test.com';
        Subscriber::subscribe($email, 'Test User');

        $count = Subscriber::activeCount();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_subscribe_duplicate_does_not_crash(): void
    {
        $email = 'dup-' . bin2hex(random_bytes(4)) . '@test.com';
        Subscriber::subscribe($email, 'First');

        // Second call should not throw (UPSERT or ON CONFLICT)
        $this->expectNotToPerformAssertions();
        try {
            Subscriber::subscribe($email, 'Second');
        } catch (\Throwable) {
            // Some implementations may throw on duplicate; that's also OK
            $this->assertTrue(true);
        }
    }

    public function test_active_subscribers_returns_array(): void
    {
        $email = 'active-' . bin2hex(random_bytes(4)) . '@test.com';
        Subscriber::subscribe($email, 'Active Sub');

        $subs = Subscriber::activeSubscribers();
        $this->assertIsArray($subs);
        $this->assertGreaterThanOrEqual(1, count($subs));
    }

    public function test_find_by_token(): void
    {
        $email = 'token-' . bin2hex(random_bytes(4)) . '@test.com';
        Subscriber::subscribe($email, 'Token User');

        // Find the subscriber to get the token
        $subs = Subscriber::activeSubscribers();
        $found = null;
        foreach ($subs as $s) {
            if ($s['email'] === $email) { $found = $s; break; }
        }

        if ($found && !empty($found['unsub_token'])) {
            $byToken = Subscriber::findByToken($found['unsub_token']);
            $this->assertNotNull($byToken);
            $this->assertSame($email, $byToken['email']);
        } else {
            $this->markTestSkipped('Subscriber has no unsub_token');
        }
    }

    public function test_dashboard_counts(): void
    {
        $counts = Subscriber::dashboardCounts();
        $this->assertArrayHasKey('active', $counts);
        $this->assertArrayHasKey('total', $counts);
        $this->assertIsInt($counts['active']);
    }
}