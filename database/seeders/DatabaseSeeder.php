<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: PublishedWorkSeeder needs the owner account from
     * AdminUserSeeder, and both need the profile from DemoContentSeeder.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            // Before DemoContentSeeder, which shares a couple of slugs
            // ('bangladesh') — the real definition must win.
            TaxonomySeeder::class,
            DemoContentSeeder::class,
            PublishedWorkSeeder::class,
        ]);

        $this->unpublishDemoContentInProduction();
    }

    /**
     * The demo entries exist to exercise every content_type in the admin panel
     * and to give the test suite its fixtures — they are not written to be read
     * by visitors. The suite seeds DemoContentSeeder directly and needs them
     * published, so rather than branching the seeder (which would also strip
     * the journalist's real profile in production) the placeholders are
     * unpublished once the environment is production.
     *
     * Matching the "PLACEHOLDER —" title prefix is deliberate: the seeder owns
     * that prefix, so this can never catch a real article by accident.
     */
    private function unpublishDemoContentInProduction(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $count = ContentItem::where('title', 'like', 'PLACEHOLDER%')
            ->where('status', '!=', 'draft')
            ->update(['status' => 'draft']);

        $this->command?->info("Production: unpublished {$count} demo placeholder article(s).");
    }
}
