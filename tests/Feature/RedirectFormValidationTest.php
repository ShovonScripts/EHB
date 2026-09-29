<?php

namespace Tests\Feature;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The redirect form's path validation.
 *
 * `ApplyRedirects` builds its target as `'/' . $to_path`, so a stored value
 * that is not a plain path produces a silently broken redirect rather than an
 * error the admin can see: typing `https://example.com/about` yields
 * `Location: https://this-site/https://example.com/about`, and the only
 * symptom is that an old link quietly stopped working.
 *
 * The fix is to reject it at the form, which is the only point at which the
 * mistake is cheap to correct. This test exists because a Filament field's
 * `rules()` is easy to attach in a way that silently never runs.
 */
class RedirectFormValidationTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner);

        return $owner;
    }

    public function test_a_plain_path_saves(): void
    {
        $this->signIn();

        Livewire::test(CreateRedirect::class)
            ->fillForm([
                'from_path' => 'old-slug',
                'to_path' => '/articles/new-slug',
                'status_code' => 301,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', [
            'from_path' => 'old-slug',
            'to_path' => '/articles/new-slug',
        ]);
    }

    /** A path with no leading slash is equally valid; both are normalized on use. */
    public function test_a_path_without_a_leading_slash_saves(): void
    {
        $this->signIn();

        Livewire::test(CreateRedirect::class)
            ->fillForm([
                'from_path' => '/another-old-slug/',
                'to_path' => 'about',
                'status_code' => 302,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', ['to_path' => 'about']);
    }

    public static function unusableTargets(): array
    {
        return [
            'absolute url' => ['https://example.com/about'],
            'protocol relative' => ['//evil.com/phish'],
            'backslash trick' => ['/\\evil.com'],
            'javascript scheme' => ['javascript:alert(1)'],
            'space' => ['/some path'],
        ];
    }

    /**
     * @dataProvider unusableTargets
     */
    #[DataProvider('unusableTargets')]
    public function test_an_unusable_target_is_rejected(string $target): void
    {
        $this->signIn();

        Livewire::test(CreateRedirect::class)
            ->fillForm([
                'from_path' => 'old-slug',
                'to_path' => $target,
                'status_code' => 301,
            ])
            ->call('create')
            ->assertHasFormErrors(['to_path']);

        $this->assertDatabaseMissing('redirects', ['to_path' => $target]);
    }

    /**
     * @dataProvider unusableTargets
     */
    #[DataProvider('unusableTargets')]
    public function test_an_unusable_source_path_is_rejected(string $target): void
    {
        $this->signIn();

        Livewire::test(CreateRedirect::class)
            ->fillForm([
                'from_path' => $target,
                'to_path' => 'about',
                'status_code' => 301,
            ])
            ->call('create')
            ->assertHasFormErrors(['from_path']);
    }
}
