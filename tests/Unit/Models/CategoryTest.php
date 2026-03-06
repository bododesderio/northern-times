<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use Tests\DatabaseTestCase;

class CategoryTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $slug = 'politics-' . bin2hex(random_bytes(4));
        $cat = $this->createCategory('Politics', $slug);
        $this->assertNotEmpty($cat['id']);
        $this->assertSame('Politics', $cat['name']);

        $found = Category::find($cat['id']);
        $this->assertNotNull($found);
        $this->assertSame('Politics', $found['name']);
    }

    public function test_all_returns_categories(): void
    {
        $this->createCategory('Cat A', 'cat-a');
        $this->createCategory('Cat B', 'cat-b');

        $all = Category::all();
        $this->assertGreaterThanOrEqual(2, count($all));
    }

    public function test_dropdown(): void
    {
        $cat = $this->createCategory('News', 'news-test');
        $dd = Category::dropdown();
        $this->assertIsArray($dd);
        // Should contain at least our test category
        $found = false;
        foreach ($dd as $row) {
            if ($row['id'] === $cat['id']) { $found = true; break; }
        }
        $this->assertTrue($found, 'Dropdown should contain the created category');
    }

    public function test_find_nonexistent(): void
    {
        $this->assertNull(Category::find('00000000-0000-0000-0000-000000000000'));
    }
}