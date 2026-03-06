<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Sanitizer;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    // ── Script removal ──────────────────────────────────────────

    public function test_strips_script_tags(): void
    {
        $dirty = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
        $clean = Sanitizer::clean($dirty);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringContainsString('<p>World</p>', $clean);
    }

    public function test_strips_event_handlers(): void
    {
        $dirty = '<img src="x.jpg" onerror="alert(1)" alt="pic">';
        $clean = Sanitizer::clean($dirty);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('alt="pic"', $clean);
    }

    public function test_strips_javascript_uri(): void
    {
        $dirty = '<a href="javascript:alert(1)">Click</a>';
        $clean = Sanitizer::clean($dirty);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    // ── Allowed tags ────────────────────────────────────────────

    public function test_allows_basic_formatting(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p>';
        $this->assertSame($html, Sanitizer::clean($html));
    }

    public function test_allows_links(): void
    {
        $clean = Sanitizer::clean('<a href="https://example.com" title="Go">Link</a>');
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('title="Go"', $clean);
    }

    public function test_allows_images(): void
    {
        $clean = Sanitizer::clean('<img src="/uploads/photo.jpg" alt="Photo" width="600">');
        $this->assertStringContainsString('src="/uploads/photo.jpg"', $clean);
        $this->assertStringContainsString('alt="Photo"', $clean);
    }

    public function test_allows_tables(): void
    {
        $html = '<table><tr><td>A</td><td>B</td></tr></table>';
        $clean = Sanitizer::clean($html);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('<td>A</td>', $clean);
    }

    // ── Disallowed tags ─────────────────────────────────────────

    public function test_strips_iframes(): void
    {
        $clean = Sanitizer::clean('<iframe src="https://evil.com"></iframe>');
        $this->assertStringNotContainsString('<iframe', $clean);
    }

    public function test_strips_forms(): void
    {
        $clean = Sanitizer::clean('<form action="/steal"><input name="cc"></form>');
        $this->assertStringNotContainsString('<form', $clean);
        $this->assertStringNotContainsString('<input', $clean);
    }

    public function test_strips_style_tags(): void
    {
        $clean = Sanitizer::clean('<style>body{display:none}</style><p>Hi</p>');
        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringContainsString('<p>Hi</p>', $clean);
    }

    // ── Profiles ────────────────────────────────────────────────

    public function test_basic_profile_strips_images(): void
    {
        $clean = Sanitizer::clean('<p>Text</p><img src="x.jpg">', 'basic');
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringContainsString('<p>Text</p>', $clean);
    }

    public function test_basic_profile_keeps_links(): void
    {
        $clean = Sanitizer::clean('<a href="https://example.com">Link</a>', 'basic');
        $this->assertStringContainsString('href="https://example.com"', $clean);
    }

    // ── Edge cases ──────────────────────────────────────────────

    public function test_empty_string(): void
    {
        $this->assertSame('', Sanitizer::clean(''));
    }

    public function test_plain_text(): void
    {
        $this->assertSame('Just plain text', Sanitizer::clean('Just plain text'));
    }

    public function test_external_links_get_noopener(): void
    {
        $clean = Sanitizer::clean('<a href="https://external.com">Ext</a>');
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }
}