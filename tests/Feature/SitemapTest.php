<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Media;
use App\Models\Publication;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(DemoContentSeeder::class);
    }

    /** SEO.md §8 — sitemap renders valid XML with the expected URL types. */
    public function test_sitemap_includes_published_content_and_static_pages(): void
    {
        $this->seedContent();

        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap must be valid XML');

        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
        }

        // Static pages
        $this->assertContains(url('/'), $locations);
        $this->assertContains(url('/about'), $locations);
        $this->assertContains(url('/articles'), $locations);
        $this->assertContains(url('/archive'), $locations);

        // Published internal + external summary pages via publicPath()
        $this->assertContains(url('/articles/demo-internal-news-one'), $locations);
        $this->assertContains(url('/work/demo-external-news-one'), $locations);

        // Topic + publication pages
        $this->assertContains(url('/topics/crime-justice'), $locations);
        $this->assertContains(url('/publications/the-daily-star'), $locations);

        // No admin, no search
        $this->assertNotContains(url('/admin'), $locations);
        $this->assertNotContains(url('/search'), $locations);
    }

    /** SEO.md §8 — draft and future-scheduled items must never appear. */
    public function test_sitemap_excludes_draft_and_future_scheduled(): void
    {
        $this->seedContent();

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('demo-draft-hidden', $content);
        $this->assertStringNotContainsString('demo-scheduled-future-hidden', $content);
    }

    /** SEO.md §8/§11 — lastmod emitted; image extension for featured images. */
    public function test_sitemap_emits_lastmod_and_image_extension(): void
    {
        $this->seedContent();

        $user = User::where('role', 'owner')->first();
        Storage::fake('public');

        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagejpeg($img);
        $bytes = ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put('media/sitemap-test.jpg', $bytes);

        $media = Media::create([
            'type' => 'image',
            'file_path' => 'media/sitemap-test.jpg',
            'disk' => 'public',
            'original_filename' => 'sitemap-test.jpg',
            'alt_text' => 'Test image',
            'uploaded_by' => $user->id,
        ]);

        ContentItem::where('slug', 'demo-internal-news-one')
            ->update(['featured_image_media_id' => $media->id]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml);
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $this->assertNotEmpty($xml->url->lastmod, 'lastmod must be present');

        $imageLocs = $xml->xpath('//image:loc');
        $this->assertNotEmpty($imageLocs, 'image sitemap extension must be emitted');
        $this->assertStringContainsString(
            'sitemap-test.jpg',
            (string) $imageLocs[0]
        );
    }

    /** Static pages get a lastmod derived from latest content update. */
    public function test_sitemap_lastmod_is_iso8601(): void
    {
        $this->seedContent();

        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        foreach ($xml->url->lastmod as $lastmod) {
            $this->assertNotFalse(
                date(DATE_ATOM, strtotime((string) $lastmod)),
                'lastmod must be ISO-8601: '.(string) $lastmod
            );
        }
    }
}
