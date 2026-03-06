<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Notification;
use Tests\DatabaseTestCase;

class NotificationTest extends DatabaseTestCase
{
    public function test_send_creates_notification(): void
    {
        $user = $this->createUser();
        Notification::send($user['id'], 'system', 'Test Title', 'Body text', '/admin');

        $notifs = Notification::forUser($user['id']);
        $this->assertCount(1, $notifs);
        $this->assertSame('Test Title', $notifs[0]['title']);
        $this->assertSame('system', $notifs[0]['type']);
        $this->assertFalse((bool)$notifs[0]['is_read']);
    }

    public function test_unread_count(): void
    {
        $user = $this->createUser();
        Notification::send($user['id'], 'system', 'N1');
        Notification::send($user['id'], 'system', 'N2');
        Notification::send($user['id'], 'system', 'N3');

        $this->assertSame(3, Notification::unreadCount($user['id']));
    }

    public function test_mark_read(): void
    {
        $user = $this->createUser();
        Notification::send($user['id'], 'system', 'Read Me');

        $notifs = Notification::forUser($user['id']);
        Notification::markRead($notifs[0]['id'], $user['id']);

        $this->assertSame(0, Notification::unreadCount($user['id']));
    }

    public function test_mark_all_read(): void
    {
        $user = $this->createUser();
        Notification::send($user['id'], 'system', 'A');
        Notification::send($user['id'], 'system', 'B');
        Notification::send($user['id'], 'system', 'C');

        Notification::markAllRead($user['id']);
        $this->assertSame(0, Notification::unreadCount($user['id']));
    }

    public function test_for_user_unread_only(): void
    {
        $user = $this->createUser();
        Notification::send($user['id'], 'system', 'Unread');
        Notification::send($user['id'], 'system', 'Read');

        $notifs = Notification::forUser($user['id']);
        Notification::markRead($notifs[1]['id'], $user['id']);

        $unread = Notification::forUser($user['id'], 20, true);
        $this->assertCount(1, $unread);
    }
}