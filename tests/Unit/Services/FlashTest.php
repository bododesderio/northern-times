<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Flash;
use PHPUnit\Framework\TestCase;

final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['_flash']);
    }

    public function testSetAndGet(): void
    {
        Flash::set('success', 'Item saved');
        $this->assertSame('Item saved', Flash::get('success'));
    }

    public function testGetClearsValue(): void
    {
        Flash::set('error', 'Something broke');
        Flash::get('error');
        $this->assertNull(Flash::get('error'));
    }

    public function testGetReturnsNullIfNotSet(): void
    {
        $this->assertNull(Flash::get('nonexistent'));
    }

    public function testMultipleKeys(): void
    {
        Flash::set('success', 'OK');
        Flash::set('error', 'FAIL');
        $this->assertSame('OK', Flash::get('success'));
        $this->assertSame('FAIL', Flash::get('error'));
    }
}