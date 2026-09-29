<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Backup/restore verification (Phase 9).
 *
 * Verifies that the backup:database and restore:database commands
 * correctly preserve and recover application data.
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function wipeDatabase(): void
    {
        $tables = \DB::select('SELECT name FROM sqlite_master WHERE type="table" AND name NOT LIKE "sqlite_%"');

        \DB::beginTransaction();
        try {
            foreach ($tables as $table) {
                \DB::statement('DELETE FROM '.$table->name);
            }
            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /** @return array{users: array, content: array, categories: array} */
    private function seedAndSnapshot(): array
    {
        $owner = $this->owner();
        $category = Category::create(['name' => 'News', 'slug' => 'news']);

        $item = ContentItem::create([
            'author_id' => $owner->id,
            'content_type' => 'news',
            'source_type' => 'internal',
            'title' => 'Backup Test Article',
            'slug' => 'backup-test-article',
            'summary' => 'Summary.',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
            'category_id' => $category->id,
        ]);

        return [
            'users' => User::all()->map(fn ($u) => $u->only(['id', 'name', 'email', 'role']))->toArray(),
            'content' => ContentItem::all()->map(fn ($c) => $c->only(['id', 'title', 'slug', 'status']))->toArray(),
            'categories' => Category::all()->map(fn ($c) => $c->only(['id', 'name', 'slug']))->toArray(),
        ];
    }

    /** Backup captures data; after wipe + restore, the snapshot matches. */
    public function test_backup_and_restore_preserves_data(): void
    {
        $backupDir = storage_path('app/backups');

        if (File::exists($backupDir)) {
            File::cleanDirectory($backupDir);
        }

        $snapshot = $this->seedAndSnapshot();

        $backupDir = storage_path('app/backups');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $this->artisan('backup:database', ['--path' => $backupDir])
            ->expectsOutputToContain('backed up')
            ->assertExitCode(Command::SUCCESS);

        $backupFiles = File::glob($backupDir.'/database-*.sql');

        $backupFiles = array_filter($backupFiles, function ($file) {
            return File::size($file) > 100;
        });

        $backupFiles = array_reverse($backupFiles);

        $this->assertNotEmpty($backupFiles, 'Expected a valid backup file to be created');

        $backupPath = $backupFiles[0];

        $this->wipeDatabase();

        $this->assertEmpty(User::all());
        $this->assertEmpty(ContentItem::all());
        $this->assertEmpty(Category::all());

        $this->artisan('restore:database', ['path' => $backupPath, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame($snapshot['users'], User::all()->map(fn ($u) => $u->only(['id', 'name', 'email', 'role']))->toArray());
        $this->assertSame($snapshot['content'], ContentItem::all()->map(fn ($c) => $c->only(['id', 'title', 'slug', 'status']))->toArray());
        $this->assertSame($snapshot['categories'], Category::all()->map(fn ($c) => $c->only(['id', 'name', 'slug']))->toArray());
    }
}
