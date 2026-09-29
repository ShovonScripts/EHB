<?php

namespace Tests\Feature;

use App\Support\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cache pruning for the `database` cache store.
 *
 * This command exists because Laravel 12 ships no equivalent, and the name
 * that appears in most snippets — `cache:prune-scheduled` — does not exist in
 * this framework version. Scheduling it produced a task that `schedule:list`
 * displayed happily and that then failed at runtime, which is the worst
 * possible failure mode: it looks configured and reclaims nothing.
 *
 * These tests are the reason that would be caught here rather than in
 * production: the previous version of this schedule entry had no test at all,
 * and nothing in the suite noticed.
 */
class PruneExpiredCacheEntriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test suite defaults to the array driver; these tests are about
        // the database store's expiry semantics.
        Config::set('cache.default', 'database');
    }

    private function seedCacheRows(): void
    {
        DB::table('cache')->insert([
            ['key' => 'expired-long-ago', 'value' => 'x', 'expiration' => time() - 3600],
            ['key' => 'expired-recently', 'value' => 'x', 'expiration' => time() - 1],
            ['key' => 'still-live', 'value' => 'x', 'expiration' => time() + 3600],
        ]);
    }

    private function remainingKeys(): array
    {
        return DB::table('cache')->orderBy('key')->pluck('key')->all();
    }

    public function test_only_expired_rows_are_deleted(): void
    {
        $this->seedCacheRows();

        $this->artisan('cache:prune-expired')
            ->assertSuccessful();

        $this->assertSame(['still-live'], $this->remainingKeys());
    }

    public function test_running_twice_is_harmless(): void
    {
        $this->seedCacheRows();

        $this->artisan('cache:prune-expired')->assertSuccessful();
        $this->artisan('cache:prune-expired')
            ->assertSuccessful()
            ->expectsOutputToContain('No expired cache entries');

        $this->assertSame(['still-live'], $this->remainingKeys());
    }

    /**
     * A live entry is the whole point of the cache, so pruning must never
     * touch one — including one written moments ago by the response cache.
     */
    public function test_a_freshly_written_entry_survives(): void
    {
        $key = PageCache::key('response', ['http://localhost/articles', []]);

        PageCache::put($key, ['content' => '<html>fresh</html>', 'status' => 200, 'headers' => []]);

        $this->artisan('cache:prune-expired')->assertSuccessful();

        $this->assertNotNull(PageCache::payload($key), 'A live cache entry must survive pruning.');
    }

    /**
     * Guards the specific regression: the schedule entry pointed at a command
     * that does not exist. `schedule:list` renders any registered string, so
     * the only way to catch this is to actually run what is scheduled.
     */
    public function test_the_scheduled_command_actually_exists(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains(
            'cache:prune-expired',
            $commands,
            'routes/console.php schedules cache:prune-expired; it must exist.'
        );
    }

    public function test_it_is_a_no_op_on_a_non_database_store(): void
    {
        Config::set('cache.default', 'array');

        $this->artisan('cache:prune-expired')
            ->assertSuccessful()
            ->expectsOutputToContain('manages its own expiry');
    }
}
