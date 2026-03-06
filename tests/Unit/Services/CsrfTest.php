<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['_csrf']);
    }

    public function test_token_generates_hex_string(): void
    {
        $token = Csrf::token();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_token_is_stable_within_session(): void
    {
        $t1 = Csrf::token();
        $t2 = Csrf::token();
        $this->assertSame($t1, $t2);
    }

    public function test_validate_correct_token(): void
    {
        $token = Csrf::token();
        $this->assertTrue(Csrf::validate($token));
    }

    public function test_validate_wrong_token(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::validate('bad-token'));
    }

    public function test_validate_null(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::validate(null));
    }

    public function test_validate_empty_session(): void
    {
        $this->assertFalse(Csrf::validate('anything'));
    }
}