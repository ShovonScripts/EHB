<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_renders(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Get in Touch', false);
    }

    /** Valid submission is stored (FR-113 / FR-405). */
    public function test_valid_message_is_stored(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Reader One',
            'email' => 'reader@example.com',
            'subject' => 'Hello',
            'message' => 'This is a test message from the feature suite.',
        ]);

        $response->assertRedirect(route('contact.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', [
            'email' => 'reader@example.com',
            'subject' => 'Hello',
        ]);
    }

    /** Validation failures re-render with errors (NFR-009). */
    public function test_invalid_message_is_rejected(): void
    {
        $response = $this->from('/contact')->post('/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ]);

        $response->assertRedirect('/contact');
        $response->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, Contact::count());
    }

    /** Honeypot submissions silently succeed without storing (SECURITY.md §9). */
    public function test_honeypot_silently_drops_message(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'spamspamspam',
            'website' => 'http://spam.example',
        ]);

        $response->assertRedirect(route('contact.create'))
            ->assertSessionHas('status');

        $this->assertSame(0, Contact::count());
    }

    /** Contact endpoint is rate limited (SECURITY.md §9). */
    public function test_contact_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/contact', [
                'name' => 'R'.$i,
                'email' => 'r'.$i.'@example.com',
                'message' => 'rate limit test message '.$i,
            ]);
        }

        // 6th request within the minute exceeds the limit of 5.
        $this->post('/contact', [
            'name' => 'R6',
            'email' => 'r6@example.com',
            'message' => 'one too many',
        ])->assertStatus(429);
    }
}
