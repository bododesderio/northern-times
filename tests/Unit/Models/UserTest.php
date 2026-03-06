<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\DatabaseTestCase;

class UserTest extends DatabaseTestCase
{
    public function test_create_and_find_by_email(): void
    {
        $email = 'find-' . bin2hex(random_bytes(4)) . '@test.com';
        $user  = $this->createUser($email);

        $found = User::findByEmail($email);
        $this->assertNotNull($found);
        $this->assertSame($user['id'], $found['id']);
    }

    public function test_find_by_email_nonexistent(): void
    {
        $this->assertNull(User::findByEmail('nobody@nowhere.com'));
    }

    public function test_toggle_active(): void
    {
        $user = $this->createUser();
        $this->assertTrue((bool)$user['is_active']);

        User::toggleActive($user['id']);
        $found = User::find($user['id']);
        $this->assertFalse((bool)$found['is_active']);

        User::toggleActive($user['id']);
        $found = User::find($user['id']);
        $this->assertTrue((bool)$found['is_active']);
    }

    public function test_update_password(): void
    {
        $user = $this->createUser();
        User::updatePassword($user['id'], 'NewPassword456!');

        $found = User::find($user['id']);
        $this->assertTrue(password_verify('NewPassword456!', $found['password_hash']));
        $this->assertFalse(password_verify('TestPass123!', $found['password_hash']));
    }

    public function test_active_count(): void
    {
        $this->createUser();
        $count = User::activeCount();
        $this->assertGreaterThanOrEqual(1, $count);
    }
}