<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Slug;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    public function test_make_basic(): void
    {
        $this->assertSame('hello-world', Slug::make('Hello World'));
    }

    public function test_make_strips_special_chars(): void
    {
        $this->assertSame('test-article-2024', Slug::make('Test Article! @#$% 2024'));
    }

    public function test_make_trims_dashes(): void
    {
        $this->assertSame('hello', Slug::make('---hello---'));
    }

    public function test_make_collapses_whitespace(): void
    {
        $this->assertSame('a-b-c', Slug::make('  a   b   c  '));
    }

    public function test_make_lowercase(): void
    {
        $this->assertSame('all-caps-title', Slug::make('ALL CAPS TITLE'));
    }

    public function test_make_empty_returns_fallback(): void
    {
        $this->assertSame('article', Slug::make(''));
        $this->assertSame('article', Slug::make('!!!'));
    }

    public function test_make_unicode(): void
    {
        $this->assertSame('café-résumé', Slug::make('Café résumé'));
    }

    public function test_make_numbers(): void
    {
        $this->assertSame('12345', Slug::make('12345'));
    }
}