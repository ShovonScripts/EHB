<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SECURITY.md §15 — the activity_logs table records create / update /
 * publish / schedule / delete on content and profile models.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function makeItem(array $attributes = []): ContentItem
    {
        return ContentItem::create(array_merge([
            'author_id' => $this->author()->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Audited Item',
            'slug' => 'audited-item',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'draft',
        ], $attributes));
    }

    /** Creating content writes a `created` entry against the acting user. */
    public function test_create_is_logged_with_user_and_subject(): void
    {
        $user = $this->author();

        $this->actingAs($user);

        $item = $this->makeItem();

        $log = ActivityLog::where('action', ActivityLogger::CREATED)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($item->getMorphClass(), $log->subject_type);
        $this->assertSame($item->id, $log->subject_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Audited Item', $log->changes['after']['title']);
    }

    /** A draft -> published transition is logged as `published`. */
    public function test_publishing_is_logged_as_publish_action(): void
    {
        $this->actingAs($this->author());

        $item = $this->makeItem();

        $item->update(['status' => 'published']);

        $log = ActivityLog::where('action', ActivityLogger::PUBLISHED)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->subject_id);
        $this->assertSame('draft', $log->changes['before']['status']);
        $this->assertSame('published', $log->changes['after']['status']);
    }

    /** Scheduling for the future is distinguishable from publishing now. */
    public function test_scheduling_is_logged_as_schedule_action(): void
    {
        $this->actingAs($this->author());

        $item = $this->makeItem();

        $item->update([
            'status' => 'scheduled',
            'published_at' => now()->addWeek(),
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLogger::SCHEDULED,
            'subject_id' => $item->id,
        ]);
    }

    /** Ordinary edits log `updated` with a before/after diff. */
    public function test_update_records_changed_attributes_only(): void
    {
        $this->actingAs($this->author());

        $item = $this->makeItem();

        $item->update(['title' => 'Renamed']);

        $log = ActivityLog::where('action', ActivityLogger::UPDATED)->where('subject_id', $item->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame(['title' => 'Audited Item'], $log->changes['before']);
        $this->assertSame(['title' => 'Renamed'], $log->changes['after']);
        $this->assertArrayNotHasKey('updated_at', $log->changes['after']);
    }

    /** A no-op save (same values) does not create noise in the trail. */
    public function test_saving_without_changes_writes_no_entry(): void
    {
        $this->actingAs($this->author());

        $item = $this->makeItem();

        $before = ActivityLog::count();

        $item->save();

        $this->assertSame($before, ActivityLog::count());
    }

    /** Deletes capture the last known state for recovery/audit. */
    public function test_delete_is_logged_with_snapshot(): void
    {
        $this->actingAs($this->author());

        $item = $this->makeItem();
        $id = $item->id;

        $item->delete();

        $log = ActivityLog::where('action', ActivityLogger::DELETED)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($id, $log->subject_id);
        $this->assertSame('Audited Item', $log->changes['before']['title']);
    }

    /** Profile-related models are audited too (SECURITY.md §15). */
    public function test_profile_updates_are_logged(): void
    {
        $user = $this->author();

        $this->actingAs($user);

        $profile = JournalistProfile::create([
            'user_id' => $user->id,
            'name' => 'Test Journalist',
            'title' => 'Reporter',
            'short_bio' => 'Bio.',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLogger::CREATED,
            'subject_type' => $profile->getMorphClass(),
            'subject_id' => $profile->id,
        ]);

        $profile->update(['title' => 'Senior Reporter']);

        $log = ActivityLog::where('action', ActivityLogger::UPDATED)
            ->where('subject_type', $profile->getMorphClass())
            ->where('subject_id', $profile->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Senior Reporter', $log->changes['after']['title']);
    }

    /** Password-ish attributes never land in the trail. */
    public function test_sensitive_attributes_are_never_logged(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user);

        $user->update(['password' => 'new-secret-hash']);

        $this->assertSame(
            0,
            ActivityLog::where('changes', 'like', '%new-secret-hash%')->count()
        );
    }
}
