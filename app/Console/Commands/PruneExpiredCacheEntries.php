<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete expired rows from the `cache` table.
 *
 * ## Why this exists
 *
 * The application caches through the `database` driver (see .env), and the
 * response cache stores a whole rendered page per key — tens of kilobytes
 * each. `DatabaseStore` only ever deletes a row as a side effect of *reading*
 * a key that turns out to be expired, so a URL nobody visits a second time
 * leaves its row behind indefinitely. The key space is derived from request
 * input, so those unvisited keys are exactly the ones that accumulate.
 *
 * Laravel 12 ships no command for this. `cache:clear` truncates the entire
 * table, which would throw away every warm entry on a site whose whole point
 * is a warm cache, and `cache:prune-scheduled` — the name that appears in
 * most snippets online — does not exist in this framework version, so
 * scheduling it fails at runtime while `schedule:list` still cheerfully
 * displays it.
 *
 * This deletes only rows whose `expiration` has passed, in batches so a large
 * table does not produce one enormous DELETE, and it is a no-op when the
 * active cache store is not the database one.
 */
class PruneExpiredCacheEntries extends Command
{
    protected $signature = 'cache:prune-expired
                            {--store= : Cache store to prune (defaults to the configured default)}
                            {--chunk=500 : Rows to delete per statement}';

    protected $description = 'Delete expired rows from the database cache table';

    public function handle(): int
    {
        $store = $this->option('store') ?: config('cache.default');

        if ($store !== 'database') {
            $this->warn("The active cache store is '{$store}', which manages its own expiry. Nothing to prune.");

            return Command::SUCCESS;
        }

        $connection = config('cache.stores.database.connection') ?: config('database.default');
        $table = config('cache.stores.database.table', 'cache');

        if (! Schema::connection($connection)->hasTable($table)) {
            $this->error("Cache table '{$table}' does not exist on connection '{$connection}'.");

            return Command::FAILURE;
        }

        $deleted = 0;

        // delete() in batches rather than one statement, so a table with a
        // lot of accumulated rows does not lock for the length of a single
        // huge DELETE on a live MySQL/MariaDB server.
        do {
            $affected = DB::connection($connection)
                ->table($table)
                ->where('expiration', '<', time())
                ->limit((int) $this->option('chunk'))
                ->delete();

            $deleted += $affected;
        } while ($affected > 0);

        if ($deleted === 0) {
            $this->info('No expired cache entries to prune.');

            return Command::SUCCESS;
        }

        $this->info("Pruned {$deleted} expired cache ".($deleted === 1 ? 'entry' : 'entries').'.');

        return Command::SUCCESS;
    }
}
