<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The application timezone drives scheduled publishing.
 *
 * The admin's date picker interprets and displays dates in config('app.timezone').
 * This was hardcoded to 'UTC' in config/app.php, so for a Bangladeshi
 * publication a piece scheduled for 09:00 went live at 15:00 Asia/Dhaka — six
 * hours late — and there was no way to configure it, because the setting read
 * from neither APP_TIMEZONE nor .env.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_timezone_is_configurable_from_the_environment(): void
    {
        // Not hardcoded: it must read the env var so it can be corrected without
        // editing version-controlled config.
        $this->assertSame(
            env('APP_TIMEZONE', 'UTC'),
            config('app.timezone'),
            'config/app.php must derive the timezone from APP_TIMEZONE'
        );
    }

    public function test_the_app_timezone_is_not_utc(): void
    {
        // A Bangladesh publication scheduling by local wall-clock time.
        $this->assertSame('Asia/Dhaka', config('app.timezone'));
    }

    public function test_now_matches_local_bangladesh_time(): void
    {
        $app = now();
        $local = new \DateTime('now', new \DateTimeZone('Asia/Dhaka'));

        $this->assertSame(
            $local->format('Y-m-d H:i'),
            $app->format('Y-m-d H:i'),
            'now() must agree with Bangladesh local time, not UTC'
        );
    }

    /**
     * A date picked in the admin is stored and read back as the same wall-clock
     * time the journalist typed.
     */
    public function test_a_scheduled_datetime_is_not_shifted(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $item = ContentItem::create([
            'title' => 'Scheduled piece',
            'slug' => 'scheduled-piece',
            'status' => 'scheduled',
            'content_type' => 'news',
            'source_type' => 'internal',
            'author_id' => $owner->id,
            'summary' => 'Summary.',
            'body' => 'Body text for the piece.',
            'published_at' => '2027-03-14 09:00:00',
        ]);

        // What the journalist typed must be what comes back out.
        $this->assertSame('2027-03-14 09:00:00', $item->fresh()->published_at->format('Y-m-d H:i:s'));

        // And that instant must be 09:00 in Dhaka, not 03:00.
        $this->assertSame(
            '09:00',
            $item->fresh()->published_at->setTimezone('Asia/Dhaka')->format('H:i')
        );
    }
}
