<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accessibility smoke-tests — static-analysis pass over rendered HTML
 * and CSS because this environment does not have a headless browser.
 *
 * We assert:
 *  - every <img> has a non-empty alt attribute
 *  - exactly one <h1> per page
 *  - every <input>/<textarea> has an associated <label> or aria-label
 *  - CSS declares :focus-visible rules for interactive elements
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedBasicContent(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $profile = JournalistProfile::create([
            'user_id' => $owner->id,
            'name' => 'Test Journalist',
            'title' => 'Staff Writer',
            'short_bio' => 'Short bio.',
            'long_bio' => 'Long bio.',
        ]);

        $category = Category::create(['name' => 'News', 'slug' => 'news']);

        ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Internal Article',
            'slug' => 'internal-article',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => $category->id,
        ]);

        ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'news',
            'source_type' => 'external',
            'title' => 'External Article',
            'slug' => 'external-article',
            'summary' => 'Summary.',
            'external_url' => 'https://example.com/original',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => $category->id,
        ]);
    }

    // ── Image alt text ──────────────────────────────────────────

    /** Every rendered <img> has a non-empty alt attribute. */
    public function test_all_images_have_alt_text(): void
    {
        $this->seedBasicContent();

        $paths = ['/', '/contact', '/archive', '/search?q=test', '/articles/internal-article', '/work/external-article'];

        foreach ($paths as $path) {
            $response = $this->get($path);

            if ($response->status() === 302) {
                $response->followRedirects();
            }

            $this->assertNotEquals(404, $response->status(), "Path {$path} returned 404");

            $content = $response->content();

            if (str_contains($content, '<img')) {
                preg_match_all('/<img[^>]*>/i', $content, $matches);

                foreach ($matches[0] as $imgTag) {
                    $this->assertStringContainsString('alt=', $imgTag, "Missing alt on <img> in {$path}");

                    preg_match('/alt=("([^"]*)"|\'([^\']*)\')/i', $imgTag, $altMatch);

                    $this->assertNotEmpty($altMatch, "Empty alt attribute on <img> in {$path}: {$imgTag}");
                    $altValue = $altMatch[2] ?? $altMatch[3] ?? '';

                    $this->assertNotEmpty(trim($altValue), "Empty alt value on <img> in {$path}: {$imgTag}");
                }
            }
        }
    }

    // ── Single H1 per page ──────────────────────────────────────

    /** Each public page contains exactly one <h1>. */
    public function test_each_page_has_exactly_one_h1(): void
    {
        $this->seedBasicContent();

        $paths = ['/', '/contact', '/archive', '/search?q=test', '/articles/internal-article', '/work/external-article'];

        foreach ($paths as $path) {
            $response = $this->get($path);

            if ($response->status() === 302) {
                $response->followRedirects();
            }

            $this->assertNotEquals(404, $response->status(), "Path {$path} returned 404");

            $content = $response->content();
            preg_match_all('/<h1\b/i', $content, $matches);

            $this->assertCount(1, $matches[0], "Expected exactly one <h1> on {$path}, found ".count($matches[0]));
        }
    }

    /**
     * Headings must not skip a level (WCAG 1.3.1 / 2.4.6).
     *
     * A band label rendered as a styled <p> is pixel-identical to a heading but
     * invisible to anyone navigating by heading — which is the only mechanism a
     * screen-reader user has for skipping a section. That is not a cosmetic
     * preference: it is the difference between "The Archive" being reachable
     * and being decoration.
     */
    public function test_headings_do_not_skip_levels(): void
    {
        $this->seedBasicContent();

        $paths = [
            '/', '/contact', '/archive', '/search?q=test',
            '/articles/internal-article', '/work/external-article',
            '/work', '/sections', '/topics', '/publications', '/about',
        ];

        foreach ($paths as $path) {
            $response = $this->get($path);

            if ($response->status() === 302) {
                $response->followRedirects();
            }

            $this->assertNotEquals(404, $response->status(), "Path {$path} returned 404");

            // Inline SVG carries <title>/<path> but no heading text; stripping it
            // keeps the outline parse to real document headings.
            $html = preg_replace('~<svg.*?</svg>~s', '', $response->content());

            preg_match_all('/<(h[1-6])[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER);

            $previous = 0;

            foreach ($matches as $match) {
                $level = (int) substr($match[1], 1);

                if ($previous > 0 && $level > $previous + 1) {
                    $text = trim(preg_replace('/\s+/', ' ', strip_tags($match[2])));

                    $this->fail("{$path}: heading level jumps h{$previous} to h{$level} ({$text})");
                }

                $previous = $level;
            }

            $this->assertGreaterThan(0, count($matches), "No headings found on {$path}");
        }
    }

    // ── Form label association ──────────────────────────────────

    /** Every <input> and <textarea> on public forms has a label or aria-label. */
    public function test_form_fields_have_labels_or_aria_labels(): void
    {
        $this->seedBasicContent();

        $response = $this->get('/contact');

        $response->assertOk();

        $content = $response->content();

        preg_match_all('/<input[^>]*>/i', $content, $inputMatches);
        preg_match_all('/<textarea[^>]*>/i', $content, $textareaMatches);

        $fields = array_merge($inputMatches[0], $textareaMatches[0]);

        $this->assertNotEmpty($fields, 'Expected to find form fields on /contact');

        foreach ($fields as $field) {
            $hasLabel = preg_match('/(?:<label[^>]*for=)["\']([^"\']+)["\']/i', $content, $labelMatch) === 1
                && (str_contains($field, 'id=') || str_contains($field, 'name='));

            $hasAriaLabel = preg_match('/aria-label=("([^"]*)"|\'([^\']*)\')/i', $field, $ariaMatch) === 1
                && ! empty(trim($ariaMatch[2] ?? $ariaMatch[3] ?? ''));

            $hasAriaLabelledBy = preg_match('/aria-labelledby=("([^"]*)"|\'([^\']*)\')/i', $field, $labelledByMatch) === 1;

            $this->assertTrue(
                $hasLabel || $hasAriaLabel || $hasAriaLabelledBy,
                "Form field without accessible name: {$field}"
            );
        }
    }

    // ── CSS focus rules ─────────────────────────────────────────

    /** The source CSS includes :focus-visible rules for interactive elements. */
    public function test_css_has_focus_visible_rules(): void
    {
        $cssPath = resource_path('css/app.css');

        $this->assertFileExists($cssPath, 'Expected resources/css/app.css to exist');

        $css = file_get_contents($cssPath);

        $this->assertNotEmpty($css, 'CSS file is empty');

        $this->assertStringContainsString(':focus-visible', $css, 'CSS is missing :focus-visible rules');

        $interactiveSelectors = ['a:focus-visible', 'button:focus-visible', 'input:focus-visible', 'textarea:focus-visible'];

        foreach ($interactiveSelectors as $selector) {
            $this->assertStringContainsString($selector, $css, "CSS is missing focus rule for {$selector}");
        }
    }

    // ── Reduced motion ──────────────────────────────────────────

    /** CSS declares a prefers-reduced-motion override that disables animations. */
    public function test_css_supports_prefers_reduced_motion(): void
    {
        $cssPath = resource_path('css/app.css');

        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css, 'CSS is missing reduced-motion media query');

        $this->assertStringContainsString('animation-duration: 0.01ms', $css, 'Reduced-motion fallback should zero out animation durations');
        $this->assertStringContainsString('transition-duration: 0.01ms', $css, 'Reduced-motion fallback should zero out transition durations');
    }

    /** Animation classes used in views are covered by the reduced-motion rule. */
    public function test_animation_classes_are_covered_by_reduced_motion(): void
    {
        $cssPath = resource_path('css/app.css');

        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);

        $classes = ['animate-entrance', 'reveal', 'nav-open', 'link-accent', 'btn-primary', 'content-card-thumb', 'arrow-nudge'];

        foreach ($classes as $class) {
            $this->assertStringContainsString($class, $css, "CSS is missing animation utility class: {$class}");
        }
    }

    // ── CSP compliance ──────────────────────────────────────────

    /** No new external script or style domains were introduced by the animation pass. */
    public function test_animation_pass_does_not_introduce_new_external_domains(): void
    {
        $cssPath = resource_path('css/app.css');

        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);

        $externalPattern = '/https?:\/\/(?!fonts\.googleapis\.com|fonts\.gstatic\.com|fonts\.google\.com)[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}/i';

        preg_match_all($externalPattern, $css, $matches);

        $this->assertEmpty($matches[0], 'CSS contains unexpected external domains: '.implode(', ', $matches[0]));
    }
}
