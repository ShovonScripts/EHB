<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\RichTextField;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The application's own rich text field.
 *
 * Two things are being protected here, and only one of them is about code:
 *
 * 1. **The editor is ours.** No third-party WYSIWYG is pulled into the admin
 *    panel, and the markup that makes it work — a contenteditable region, a
 *    toolbar, a hidden input carrying the Livewire state — is asserted here so
 *    a future refactor cannot quietly reintroduce a dependency.
 *
 * 2. **Nothing typed into it is trusted.** The field runs in a browser, so the
 *    only real guarantee is that the server sanitizes on save. Every dangerous
 *    construct below must be gone from the database, not merely hidden.
 */
class RichTextFieldTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $overrides = []): ContentItem
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $item = ContentItem::create(array_merge([
            'title' => 'A piece',
            'slug' => 'a-piece',
            'status' => 'draft',
            'content_type' => 'news',
            'source_type' => 'internal',
            'author_id' => $owner->id,
            'summary' => 'A summary.',
            // Internal content requires the main body, so every fixture needs
            // one — without it the save fails on *that* field and this suite
            // would be testing the wrong error.
            'body' => '<p>The main article body.</p>',
        ], $overrides));

        $this->actingAs($owner);

        return $item;
    }

    private function saveWith(string $html): ContentItem
    {
        $item = $this->item();

        Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['news_body' => $html])
            ->call('save')
            ->assertHasNoFormErrors();

        return $item->fresh();
    }

    // ── The JavaScript must actually parse ────────────────────────

    /**
     * The bug this exists for.
     *
     * The link guard's regex was written `/^https?:\\/\\//` on the reasoning
     * that Blade needed the backslashes escaped. It does not — Blade passes raw
     * HTML through untouched — so the browser received a regex literal that
     * closed early, which made the **entire** `x-data` object a syntax error.
     *
     * Every method on it went undefined at once: `mirror()` stopped syncing the
     * region into the hidden input, `exec()` stopped formatting, `onPaste()`
     * stopped working. The field still rendered perfectly, still looked right,
     * and silently saved an empty string forever. Nothing in a PHP test suite
     * noticed, because a string containing invalid JavaScript is still a valid
     * string.
     *
     * So the rendered `x-data` is extracted and handed to a real parser. It is
     * skipped when node is unavailable rather than failing, so the suite stays
     * runnable without it.
     */
    public function test_the_rendered_javascript_parses(): void
    {
        if ($this->nodeBinary() === null) {
            $this->markTestSkipped('node is not available to parse the editor script.');
        }

        $xData = $this->extractXData($this->renderedEditorHtml());

        $this->assertNotNull($xData, 'Could not find the editor\'s x-data attribute.');

        // Alpine's x-data takes an object literal; wrap it so `node --check` is
        // parsing the same source the browser will.
        $script = "const __alpine = {$xData};";

        $file = tempnam(sys_get_temp_dir(), 'rte-js-').'.js';
        file_put_contents($file, $script);

        exec(escapeshellarg($this->nodeBinary()).' --check '.escapeshellarg($file).' 2>&1', $output, $exitCode);

        $error = trim(implode("\n", $output));
        @unlink($file);

        $this->assertSame(
            0,
            $exitCode,
            "The editor's x-data is not valid JavaScript, so every method on it is undefined:\n{$error}"
        );
    }

    /**
     * Locate node, or null.
     *
     * Uses `where` rather than a bare `node` because a Windows PHP process does
     * not always inherit the shell's PATH, and a Unix-style `2>/dev/null` in
     * the probe is a syntax error under cmd.exe — which made the first version
     * of this test silently skip on the very machine that had the bug.
     */
    private function nodeBinary(): ?string
    {
        $found = @shell_exec('where node 2>&1');

        if (! is_string($found) || trim($found) === '') {
            return null;
        }

        foreach (preg_split('/\R/', trim($found)) as $line) {
            $line = trim($line);

            if ($line !== '' && is_file($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * The editor's `x-data` value, HTML-decoded.
     *
     * Scans every `x-data` on the page and returns the one that is ours,
     * identified by the methods it defines. Filament wraps the whole form in
     * its own `x-data`, so taking the first match on the page returns that one
     * instead and the syntax check passes on the wrong script.
     */
    private function extractXData(string $html): ?string
    {
        if (! preg_match_all('/x-data="(.*?)"(?=\s|>)/s', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $candidate) {
            $decoded = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5);

            if (str_contains($decoded, 'onPaste') && str_contains($decoded, 'mirror')) {
                return $decoded;
            }
        }

        return null;
    }

    private function renderedEditorHtml(): string
    {
        $item = $this->item();

        return Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->html();
    }

    /**
     * The specific guard, asserted directly as well as through the parser, so
     * the failure message names the cause rather than "syntax error".
     */
    public function test_the_link_regex_is_not_double_escaped(): void
    {
        $xData = $this->extractXData($this->renderedEditorHtml());

        $this->assertStringContainsString('/^https?:\\/\\//i', (string) $xData);
        $this->assertStringNotContainsString('\\\\/', (string) $xData);
    }

    /** The region must be tall enough to be a writing surface. */
    public function test_the_editing_region_is_tall(): void
    {
        $html = $this->renderedEditorHtml();

        $this->assertStringContainsString('min-h-[22rem]', $html);
        $this->assertStringNotContainsString('min-h-40', $html);
    }

    public function test_the_height_is_configurable(): void
    {
        $this->assertSame('min-h-[22rem]', RichTextField::make('x')->getEditorHeight());
        $this->assertSame(
            'min-h-[30rem]',
            RichTextField::make('x')->editorHeight('min-h-[30rem]')->getEditorHeight()
        );
    }

    // ── The editor is ours ────────────────────────────────────────

    public function test_the_field_renders_a_contenteditable_region_and_toolbar(): void
    {
        $item = $this->item();

        $html = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('contenteditable="true"', $html);
        $this->assertStringContainsString('role="toolbar"', $html);
    }

    /**
     * The mechanism that makes the field work at all: Livewire can only
     * serialise form controls, so the region's HTML is mirrored into a hidden
     * input carrying the state. Without this the field would save empty.
     */
    public function test_the_state_is_carried_by_a_bound_input(): void
    {
        $item = $this->item();

        $html = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('wire:model="data.news_body"', $html);
    }

    /**
     * The widget must survive a re-render triggered by another field. Without
     * `wire:ignore` the DOM diff replaces the region and a half-typed
     * sentence is lost.
     */
    public function test_the_widget_is_protected_from_livewire_dom_diffing(): void
    {
        $item = $this->item();

        $html = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('wire:ignore', $html);
    }

    public function test_the_toolbar_exposes_the_expected_commands(): void
    {
        $commands = array_column(RichTextField::make('x')->getToolbarButtons(), 'command');

        foreach (['bold', 'italic', 'formatBlock', 'insertUnorderedList', 'insertOrderedList'] as $command) {
            $this->assertContains($command, $commands);
        }
    }

    /** formatBlock is the one command that takes an argument. */
    public function test_only_format_block_carries_an_argument(): void
    {
        foreach (RichTextField::make('x')->getToolbarButtons() as $button) {
            $expected = $button['command'] === 'formatBlock' ? $button['value'] : null;

            $this->assertSame($expected, $button['argument'], "Wrong argument for {$button['command']}");
        }
    }

    // ── Nothing typed into it is trusted ──────────────────────────

    public static function dangerousMarkup(): array
    {
        return [
            'script tag' => ['<p>ok</p><script>alert(1)</script>', 'alert(1)'],
            'event handler' => ['<p onclick="alert(1)">x</p>', 'onclick'],
            'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'iframe' => ['<iframe src="https://evil.com"></iframe>', 'iframe'],
            'style block' => ['<style>body{display:none}</style><p>x</p>', 'display:none'],
            'svg payload' => ['<svg><script>alert(1)</script></svg>', 'alert(1)'],
            'form' => ['<form action="https://evil.com"><input name="a"></form>', 'evil.com'],
        ];
    }

    /**
     * @dataProvider dangerousMarkup
     */
    #[DataProvider('dangerousMarkup')]
    public function test_dangerous_markup_never_reaches_the_database(string $input, string $mustNotContain): void
    {
        $fresh = $this->saveWith($input);

        $this->assertStringNotContainsStringIgnoringCase(
            $mustNotContain,
            (string) $fresh->news_body,
            'The server-side sanitizer is the only real guarantee for a browser-driven field.'
        );
    }

    /** Formatting the editor is meant to produce must survive intact. */
    public function test_legitimate_formatting_survives(): void
    {
        $fresh = $this->saveWith(
            '<h2>A heading</h2><p>Some <strong>bold</strong> and <em>italic</em> text.</p>'
            .'<blockquote>A quote.</blockquote><ul><li>One</li></ul>'
            .'<p><a href="https://example.com">a link</a></p>'
        );

        $stored = (string) $fresh->news_body;

        foreach (['<h2>', '<strong>', '<em>', '<blockquote>', '<ul>', '<li>', 'href="https://example.com"'] as $expected) {
            $this->assertStringContainsString($expected, $stored, "Lost {$expected} on save.");
        }
    }

    /** An external link must be given rel=noopener, as the sanitizer does. */
    public function test_external_links_are_hardened_on_save(): void
    {
        $fresh = $this->saveWith('<p><a href="https://example.com">x</a></p>');

        $this->assertStringContainsString('noopener', (string) $fresh->news_body);
    }

    /** Bangla must not be mangled — the content is UTF-8 throughout. */
    public function test_utf8_content_survives(): void
    {
        $bangla = '<p>বিচারের রায়ে দুর্নীতির ফাঁস খুলে দিলেন আদালত।</p>';

        $this->assertStringContainsString('বিচারের রায়ে', (string) $this->saveWith($bangla)->news_body);
    }

    /** The field is optional; a piece may leave it empty. */
    public function test_the_field_is_optional(): void
    {
        $fresh = $this->saveWith('');

        $this->assertEmpty($fresh->news_body);
    }
}
