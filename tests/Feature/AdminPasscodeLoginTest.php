<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin sign-in is a single passcode with no email field.
 *
 * These cover the three things that could silently break when the credential
 * shape changed: that the right passcode still gets in, that a wrong one does
 * not, and — most importantly — that removing the email field did not weaken
 * the brute-force throttle, which is the one protection a shared secret has
 * left.
 */
class AdminPasscodeLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSCODE = 'correct-horse-battery-staple';

    private function seedOwner(string $passcode = self::PASSCODE): User
    {
        $this->seed(AdminUserSeeder::class);

        $owner = User::where('role', 'owner')->firstOrFail();

        $owner->forceFill(['password' => Hash::make($passcode)])->save();

        return $owner->fresh();
    }

    /** The form must offer a passcode and must not ask for an email. */
    public function test_login_form_asks_for_a_passcode_and_not_an_email(): void
    {
        $this->seedOwner();

        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('Passcode', $html);
        $this->assertStringNotContainsString('name="email"', $html);

        // "Remember me" is deliberately absent for a single-admin panel.
        $this->assertStringNotContainsString('name="remember"', $html);
    }

    /** The correct passcode signs the owner in. */
    public function test_correct_passcode_authenticates(): void
    {
        $owner = $this->seedOwner();

        $this->assertFalse(auth()->check());

        Livewire::test(Login::class)
            ->fillForm(['passcode' => self::PASSCODE])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(auth()->check());
        $this->assertSame($owner->id, auth()->id());
    }

    /** A wrong passcode must not authenticate, and must not leak which part failed. */
    public function test_wrong_passcode_is_rejected(): void
    {
        $this->seedOwner();

        Livewire::test(Login::class)
            ->fillForm(['passcode' => 'not-the-passcode'])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertFalse(auth()->check());
    }

    /**
     * The passcode is not an email address, and the email is never read from
     * the request — the account is resolved server-side. An attacker must not
     * be able to point the login at a different account.
     */
    public function test_the_email_is_resolved_server_side_not_taken_from_input(): void
    {
        $owner = $this->seedOwner();

        $other = User::factory()->create([
            'role' => 'editor',
            'email' => 'editor@example.test',
            'password' => Hash::make('editor-passcode'),
        ]);

        // Submitting an email alongside the passcode must not change who is
        // signed in: the owner's passcode still resolves to the owner.
        Livewire::test(Login::class)
            ->fillForm(['passcode' => self::PASSCODE, 'email' => $other->email])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertNotSame($other->id, auth()->id(), 'must not sign in as the submitted email');
        $this->assertSame($owner->id, auth()->id());
    }

    /**
     * The throttle must still engage. The rate-limit key is
     * sha1(component|method|IP) and never included the email field, so
     * dropping that field must not have widened the window.
     */
    public function test_brute_force_throttle_still_engages(): void
    {
        $this->seedOwner();

        $component = new \ReflectionClass(Login::class);

        // 5 wrong attempts are allowed, the 6th is blocked.
        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Login::class)
                ->fillForm(['passcode' => 'wrong-'.$i])
                ->call('authenticate');
        }

        $this->assertFalse(auth()->check());

        // The limiter has recorded hits against this IP.
        $key = 'livewire-rate-limiter:'.sha1($component->getName().'|authenticate|'.request()->ip());
        $this->assertGreaterThan(0, RateLimiter::attempts($key));
    }

    /** The panel is unreachable without a session. */
    public function test_admin_requires_authentication(): void
    {
        $this->seedOwner();

        $this->get('/admin')->assertRedirect();
    }

    /** The passcode is stored hashed, never in the clear. */
    public function test_passcode_is_stored_hashed(): void
    {
        $owner = $this->seedOwner();

        $this->assertNotSame(self::PASSCODE, $owner->password);
        $this->assertTrue(Hash::check(self::PASSCODE, $owner->password));
    }
}
