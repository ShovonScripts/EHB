<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| The application reads and writes its caches through the `database` driver
| (see .env), so expired rows accumulate in the `cache` table and are only
| removed when that exact key happens to be read again — which, for a URL
| nobody visits twice, is never. The response cache made this concrete: it
| stores a full rendered page per key, and the key space is derived from
| request input, so unvisited keys are the expensive ones to leave behind.
|
| `cache:prune-expired` deletes expired rows in batches. Running it hourly
| is far more often than needed for correctness and costs a single indexed
| delete; the alternative is a `cache` table that only ever grows. Laravel 12
| ships no equivalent command — see App\Console\Commands\PruneExpiredCacheEntries.
|
| This needs the scheduler to actually run. On XAMPP/Windows there is no cron,
| so either run `php artisan schedule:work` in a second terminal during
| development, or add a Windows Scheduled Task calling
| `php artisan schedule:run` every minute. Note this is a maintenance task and
| is deliberately not a correctness dependency: an unpruned cache still serves
| correct responses, it just wastes disk.
*/

Schedule::command('cache:prune-expired')->hourly();
