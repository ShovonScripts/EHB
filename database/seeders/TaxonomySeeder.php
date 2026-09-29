<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    public function run(): void
    {
        $this->seedCategories();
        $this->seedTopics();
    }

    /**
     * firstOrCreate on slug, but `update`d when the definition changes, so
     * re-seeding refreshes a description or sort order instead of silently
     * keeping the first version ever written.
     */
    private function seedCategories(): void
    {
        foreach ($this->data('sections.php') as $section) {
            Category::updateOrCreate(['slug' => $section['slug']], $section);
        }
    }

    private function seedTopics(): void
    {
        foreach ($this->data('beats.php') as $topic) {
            Topic::updateOrCreate(['slug' => $topic['slug']], $topic);
        }
    }

    /**
     * Load a data file from `database/seeders/data/`.
     *
     * Definitions are plain PHP arrays rather than a model or a config file so
     * the taxonomy can be reviewed in one place and diffed cleanly, and so the
     * article data file next to them has the same shape.
     *
     * @return list<array<string, mixed>>
     */
    private function data(string $file): array
    {
        return $this->cache[$file] ??= require database_path('seeders/data/'.$file);
    }
}
