<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentModelTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    /** Reading time auto-calculates for internal bodies (UTF-8 safe). */
    public function test_reading_time_is_calculated(): void
    {
        $words = implode(' ', array_fill(0, 600, 'word'));

        $item = ContentItem::create([
            'author_id' => $this->author()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Reading Time Test',
            'slug' => 'reading-time-test',
            'summary' => 'Summary.',
            'body' => '<p>'.$words.'</p>',
            'status' => 'published',
        ]);

        $this->assertSame(3, $item->reading_time_minutes);
        $this->assertNotNull($item->published_at);
    }

    /** Bangla word counting does not mangle or miscount (NFR-010). */
    public function test_reading_time_handles_bangla_text(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->author()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'বাংলা টেস্ট',
            'slug' => 'bangla-reading-time',
            'summary' => 'সারাংশ',
            'body' => '<p>'.str_repeat('বাংলা ভাষা টেস্ট ', 400).'</p>',
            'status' => 'published',
        ]);

        $this->assertGreaterThanOrEqual(1, $item->reading_time_minutes);
        $this->assertStringContainsString('বাংলা', $item->body);
    }

    /** Public path follows the canonical map from FRONTEND.md/SEO.md. */
    public function test_public_path_map(): void
    {
        $author = $this->author();

        $internal = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'investigation',
            'source_type' => 'internal',
            'title' => 'I',
            'slug' => 'internal-inv',
            'summary' => 's',
            'body' => '<p>b</p>',
            'status' => 'published',
        ]);

        $external = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'news',
            'source_type' => 'external',
            'title' => 'E',
            'slug' => 'external-news',
            'summary' => 's',
            'external_url' => 'https://example.com/original',
            'status' => 'published',
        ]);

        $this->assertSame('/investigations/internal-inv', $internal->publicPath());
        $this->assertSame('/work/external-news', $external->publicPath());
    }

    /** External items without an external_url are not visible (FR-115). */
    public function test_external_without_url_is_hidden(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->author()->id,
            'content_type' => 'news',
            'source_type' => 'external',
            'title' => 'Broken External',
            'slug' => 'broken-external',
            'summary' => 's',
            'external_url' => null,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->assertNull(
            ContentItem::published()->where('slug', 'broken-external')->first()
        );
    }

    /** Server-side sanitizer strips scripts/handlers, keeps markup (SECURITY.md §6). */
    public function test_html_sanitizer_strips_dangerous_markup(): void
    {
        $dirty = '<p onclick="alert(1)">Safe text</p>'
            .'<script>alert("xss")</script>'
            .'<a href="javascript:alert(1)">bad link</a>'
            .'<a href="https://example.com">good link</a>'
            .'<iframe src="https://evil.example"></iframe>'
            .'<h2>Kept Heading</h2>';

        $clean = HtmlSanitizer::sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('Safe text', $clean);
        $this->assertStringContainsString('Kept Heading', $clean);
        $this->assertStringContainsString('https://example.com', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    /** Bangla survives sanitization byte-for-byte (NFR-010). */
    public function test_sanitizer_preserves_utf8(): void
    {
        $html = '<p>ঢাকার বায়ু দূষণ নিয়ে প্রতিবেদন</p>';

        $this->assertSame($html, HtmlSanitizer::sanitize($html));
    }

    /** Only http(s) video URLs are exposed for iframe rendering (SECURITY.md §6). */
    public function test_unsafe_video_url_is_not_exposed(): void
    {
        $author = $this->author();

        $safe = ContentItem::create([
            'author_id' => $author->id,
            'content_type' => 'video',
            'source_type' => 'internal',
            'title' => 'Safe Video',
            'slug' => 'safe-video',
            'summary' => 's',
            'body' => '<p>b</p>',
            'video_url' => 'https://www.youtube.com/embed/abc123',
            'status' => 'published',
        ]);

        $javascript = $safe->replicate();
        $javascript->slug = 'javascript-video';
        $javascript->video_url = 'javascript:alert(document.domain)';
        $javascript->save();

        $data = $javascript->fresh();

        $this->assertSame('https://www.youtube.com/embed/abc123', $safe->safe_video_url);
        $this->assertNull($data->safe_video_url);
    }

    /** A canonical_url_override is surfaced when set (FR-404). */
    public function test_canonical_url_override_is_used_when_set(): void
    {
        $item = ContentItem::create([
            'author_id' => $this->author()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Canonical Test',
            'slug' => 'canonical-test',
            'summary' => 's',
            'body' => '<p>b</p>',
            'canonical_url_override' => 'https://elsewhere.example/canonical',
            'status' => 'published',
        ]);

        $this->assertSame('https://elsewhere.example/canonical', $item->canonical_url_override);
        $this->assertSame('/articles/canonical-test', $item->publicPath());
    }
}
