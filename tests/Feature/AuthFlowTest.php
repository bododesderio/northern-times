<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Auth;
use App\Models\User;
use Tests\DatabaseTestCase;

class AuthFlowTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->logout();
    }

    public function test_login_with_valid_credentials(): void
    {
        $email = 'login-' . bin2hex(random_bytes(4)) . '@test.com';
        $this->createUser($email, 'editor');

        $result = Auth::attempt($email, 'TestPass123!');
        $this->assertTrue($result);
        $this->assertTrue(Auth::check());
        $this->assertSame($email, Auth::user()['email']);
    }

    public function test_login_with_wrong_password(): void
    {
        $email = 'badpw-' . bin2hex(random_bytes(4)) . '@test.com';
        $this->createUser($email);

        $result = Auth::attempt($email, 'WrongPassword');
        $this->assertFalse($result);
        $this->assertFalse(Auth::check());
    }

    public function test_login_nonexistent_user(): void
    {
        $result = Auth::attempt('ghost@nowhere.com', 'pass');
        $this->assertFalse($result);
    }

    public function test_login_inactive_user(): void
    {
        $email = 'inactive-' . bin2hex(random_bytes(4)) . '@test.com';
        $user = $this->createUser($email);
        User::toggleActive($user['id']);

        $result = Auth::attempt($email, 'TestPass123!');
        $this->assertFalse($result);
    }

    public function test_logout(): void
    {
        $email = 'out-' . bin2hex(random_bytes(4)) . '@test.com';
        $this->createUser($email);
        Auth::attempt($email, 'TestPass123!');
        $this->assertTrue(Auth::check());

        Auth::logout();
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
    }

    public function test_session_stores_role(): void
    {
        $email = 'role-' . bin2hex(random_bytes(4)) . '@test.com';
        $this->createUser($email, 'super_admin');
        Auth::attempt($email, 'TestPass123!');

        $this->assertSame('super_admin', Auth::user()['role']);
    }
}