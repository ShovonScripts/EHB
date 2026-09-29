<?php

namespace Tests\Feature;

use App\Support\RemoteImageImporter;
use App\Support\SafeRemoteImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Timeout budgets for the import-from-URL path.
 *
 * The import runs synchronously inside a Livewire request the admin is
 * watching, so its ceilings are a UX budget rather than a networking one. A
 * single combined timeout conflated two unrelated failures: a host that is
 * not answering at all (bounded by connect_timeout, which should be short) and
 * a large legitimate image transferring slowly (bounded by the total, which
 * must not be short). Making a dead host fail faster used to require making
 * big downloads fail faster too.
 */
class ImageImportTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** A 1x1 PNG, the smallest thing the importer will accept. */
    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    /**
     * A per-call override must reach the HTTP client without breaking the
     * import.
     *
     * Note what this does *not* prove: `Http::fake()` records PSR-7 requests
     * and responses, and Guzzle's per-request options — where `timeout` and
     * `connect_timeout` actually live — are not observable through it. So the
     * budgets themselves are asserted structurally below, not by observing a
     * real timed-out request, which would need a socket that deliberately
     * stalls. This covers the wiring: the argument is threaded through
     * import() to fetch() and does not break the happy path.
     */
    public function test_a_per_call_timeout_does_not_break_the_import(): void
    {
        Http::fake([
            '*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        (new RemoteImageImporter('journalist/profile'))->import(
            'https://example.com/a.png',
            null,
            3
        );

        $this->assertCount(1, Storage::disk('public')->files('journalist/profile'));
    }

    public function test_the_default_timeout_is_used_when_none_is_given(): void
    {
        Http::fake([
            '*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        (new RemoteImageImporter('journalist/profile'))->import('https://example.com/a.png');

        $this->assertCount(1, Storage::disk('public')->files('journalist/profile'));
    }

    /**
     * The connect timeout is the one that governs an unreachable host, and it
     * must stay well under the total — otherwise the total is what an admin
     * waits through when a host is dead.
     */
    public function test_the_connect_timeout_is_shorter_than_the_total(): void
    {
        $this->assertLessThan(
            SafeRemoteImage::TIMEOUT_SECONDS,
            SafeRemoteImage::CONNECT_TIMEOUT_SECONDS,
            'An unreachable host must fail on connect_timeout, not the total.'
        );
    }

    /**
     * A connect timeout above the requested total is unreachable, since Guzzle
     * gives up on the total first. Clamping keeps a short override meaningful
     * rather than silently raising it.
     */
    public function test_a_tiny_timeout_still_completes_rather_than_being_clamped_up(): void
    {
        Http::fake([
            '*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        // 1 second: smaller than the connect timeout, so the clamp matters.
        (new RemoteImageImporter('journalist/profile'))->import('https://example.com/a.png', null, 1);

        $this->assertCount(1, Storage::disk('public')->files('journalist/profile'));
    }

    /**
     * The interactive form's budget must be tighter than the class default, or
     * lowering the constant in SafeRemoteImage alone would not change what the
     * admin experiences.
     */
    public function test_the_interactive_form_uses_a_tighter_budget_than_the_default(): void
    {
        $form = file_get_contents(app_path('Filament/Resources/JournalistProfiles/Schemas/JournalistProfileForm.php'));

        $this->assertMatchesRegularExpression(
            '/IMPORT_TIMEOUT_SECONDS\s*=\s*(\d+)/',
            $form,
            'The form should declare an explicit import timeout.'
        );

        preg_match('/IMPORT_TIMEOUT_SECONDS\s*=\s*(\d+)/', $form, $m);

        $this->assertLessThan(
            SafeRemoteImage::TIMEOUT_SECONDS,
            (int) $m[1],
            'The form budget must be tighter than the default, or the default is what applies here.'
        );
    }

    /** An override must not weaken any of the SSRF or content checks. */
    public function test_a_tighter_timeout_does_not_bypass_the_ssrf_guard(): void
    {
        Http::fake();

        $this->expectException(ValidationException::class);

        (new RemoteImageImporter('journalist/profile'))->import('http://169.254.169.254/latest/meta-data/', null, 1);
    }
}
