<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Topic;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PublishedWorkSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The outlet archive is imported from a plain data file, which means a typo in
 * a section or beat slug would silently drop a piece out of a browse axis
 * rather than raise. These tests read the data file directly and assert it
 * stays internally consistent with the taxonomy it references.
 */
class PublishedWorkDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{title: string, date: string, section: string, beats: list<string>, summary: ?string, url: ?string}>
     */
    private function rows(): array
    {
        return require database_path('seeders/data/published_work.php');
    }

    /** @return list<array{slug: string}> */
    private function sectionSlugs(): array
    {
        return require database_path('seeders/data/sections.php');
    }

    /** @return list<array{slug: string}> */
    private function beatSlugs(): array
    {
        return require database_path('seeders/data/beats.php');
    }

    public function test_every_row_references_a_known_section_and_beat(): void
    {
        $sections = array_column($this->sectionSlugs(), 'slug');
        $beats = array_column($this->beatSlugs(), 'slug');

        $errors = [];

        foreach ($this->rows() as $i => $row) {
            if (! in_array($row['section'], $sections, true)) {
                $errors[] = 'row '.($i + 1).": unknown section '{$row['section']}'";
            }

            foreach ($row['beats'] as $beat) {
                if (! in_array($beat, $beats, true)) {
                    $errors[] = 'row '.($i + 1).": unknown beat '{$beat}'";
                }
            }
        }

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    public function test_every_row_has_a_title_date_and_required_keys(): void
    {
        foreach ($this->rows() as $i => $row) {
            foreach (['title', 'date', 'section', 'beats', 'summary', 'url'] as $key) {
                $this->assertArrayHasKey($key, $row, 'row '.($i + 1).' is missing '.$key);
            }

            $this->assertNotSame('', trim($row['title']), 'row '.($i + 1).' has an empty title');
            $this->assertNotFalse(
                Carbon::parse($row['date'])->format('Y-m-d') === $row['date'] ? true : false,
                'row '.($i + 1).' has an unparseable date: '.$row['date'],
            );
        }
    }

    /** Slugs must be unique, or two pieces would fight over one URL. */
    public function test_slugs_are_unique(): void
    {
        $slugs = array_map(fn (array $row) => Str::slug($row['title']), $this->rows());

        $duplicates = array_keys(array_filter(array_count_values($slugs), fn (int $n) => $n > 1));

        $this->assertSame([], $duplicates, 'duplicate slugs: '.implode(', ', $duplicates));
    }

    /** A piece with a URL but no dek would render an empty summary. */
    public function test_rows_with_a_url_also_have_a_summary(): void
    {
        foreach ($this->rows() as $i => $row) {
            if ($row['url'] !== null) {
                $this->assertNotNull($row['summary'], 'row '.($i + 1).' has a url but no summary');
            }
        }
    }

    /** A row is publishable only when it can actually link out. */
    public function test_rows_without_a_url_are_drafts(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(TaxonomySeeder::class);
        $this->seed(PublishedWorkSeeder::class);

        foreach ($this->rows() as $row) {
            $item = ContentItem::where('slug', Str::slug($row['title']))->first();

            $this->assertNotNull($item, 'not seeded: '.$row['title']);

            if ($row['url'] === null) {
                $this->assertSame('draft', $item->status, 'should be a draft: '.$row['title']);
            } else {
                $this->assertSame('published', $item->status, 'should be published: '.$row['title']);
            }
        }
    }

    /** Every seeded piece must land in its declared section, not a catch-all. */
    public function test_seeded_pieces_land_in_their_declared_section(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(TaxonomySeeder::class);
        $this->seed(PublishedWorkSeeder::class);

        $sectionIds = Category::pluck('id', 'slug');

        foreach ($this->rows() as $row) {
            $item = ContentItem::where('slug', Str::slug($row['title']))->first();

            $this->assertSame(
                $sectionIds[$row['section']],
                $item->category_id,
                'wrong section for: '.$row['title'],
            );
        }
    }

    /** Beat assignments must round-trip, and no piece may lose them on re-seed. */
    public function test_beat_assignments_round_trip_and_are_idempotent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(TaxonomySeeder::class);
        $this->seed(PublishedWorkSeeder::class);

        $beatIds = Topic::pluck('id', 'slug');

        foreach ($this->rows() as $row) {
            $item = ContentItem::where('slug', Str::slug($row['title']))->first();

            $expected = collect($row['beats'])->map(fn (string $s) => $beatIds[$s])->sort()->values()->all();
            $actual = $item->topics->pluck('id')->sort()->values()->all();

            $this->assertSame($expected, $actual, 'beats wrong for: '.$row['title']);
        }

        // Re-running must not duplicate or drop anything.
        $before = ContentItem::count();
        $this->seed(PublishedWorkSeeder::class);

        $this->assertSame($before, ContentItem::count());
    }

    /** The two taxonomy files must not disagree about a slug being both. */
    public function test_section_and_beat_slugs_are_disjoint(): void
    {
        $sections = array_column($this->sectionSlugs(), 'slug');
        $beats = array_column($this->beatSlugs(), 'slug');

        $this->assertSame([], array_intersect($sections, $beats));
    }

    /** The file is the archive; its size is worth pinning so a truncation is loud. */
    public function test_archive_row_count(): void
    {
        $this->assertCount(210, $this->rows());
    }

    /** Every section and beat the data file uses must actually appear in it. */
    public function test_no_taxonomy_entry_is_orphaned(): void
    {
        $rows = $this->rows();

        $usedSections = array_unique(array_column($rows, 'section'));
        $usedBeats = collect($rows)->flatMap(fn (array $r) => $r['beats'])->unique()->all();

        $this->assertSame([], array_diff(array_column($this->sectionSlugs(), 'slug'), $usedSections));
        $this->assertSame([], array_diff(array_column($this->beatSlugs(), 'slug'), $usedBeats));
    }
}
