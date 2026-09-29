<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Publication;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Emrul Hasan Bappi's published work — 210 pieces filed in The Daily Star
 * between 18 March 2019 and 20 September 2026.
 *
 * Every piece is stored as external work (FR-202): a headline, the dek, the
 * outlet, the real article URL and the publication date. No body text is
 * reproduced — the site links out to The Daily Star, which is the whole point
 * of the dual internal/external content model.
 *
 * The rows live in `data/published_work.php`, which also carries each piece's
 * section and beat assignments. Notes that file makes about the standfirst and
 * URL columns apply here too: **a row with no `url` is seeded as a draft**, not
 * published, because a link-out entry with nowhere to link to is worse than no
 * entry. Backfill the URL and re-run this seeder; the row publishes itself.
 *
 * Notes:
 * - The `?utm_source=gemini` tracking parameter is stripped from every URL. It
 *   is an artefact of how the list was exported; keeping it would leak a
 *   third-party referral into every outbound link on the site.
 * - No featured images are imported. The supplied image URLs are
 *   `styles/medium_300_170` derivatives — 300x170 thumbnails, far too small for
 *   a portfolio — and they are The Daily Star's news photography rather than
 *   the journalist's own to redistribute. Add images from the admin panel if
 *   licensed.
 * - `?utm_source` free URLs are stored verbatim otherwise, including the
 *   `/news-0/` path segment that the outlet's own site emits for one piece.
 */
class PublishedWorkSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'owner')->first()
            ?? User::first();

        if (! $author) {
            $this->command?->warn('No owner user found — run AdminUserSeeder first.');

            return;
        }

        $rows = $this->articles();

        $publication = $this->publication();
        $sections = $this->lookup(Category::class, 'slug');
        $beats = $this->lookup(Topic::class, 'slug');

        $published = 0;
        $drafts = 0;
        $updated = 0;
        $needsBeatReview = [];

        foreach ($rows as $index => $row) {
            $sectionId = $sections->get($row['section']);

            if (! $sectionId) {
                $this->command?->error("Unknown section '{$row['section']}' — skipping: {$row['title']}");

                continue;
            }

            $beatIds = collect($row['beats'])
                ->map(fn (string $slug) => $beats->get($slug))
                ->filter()
                ->all();

            $unknown = array_diff($row['beats'], $beats->keys()->all());

            if ($unknown !== []) {
                $this->command?->error('Unknown beat(s) '.implode(', ', $unknown)." on: {$row['title']}");
            }

            $hasUrl = filled($row['url']);

            $attributes = [
                'author_id' => $author->id,
                'content_type' => 'news',
                'source_type' => 'external',
                'title' => $row['title'],
                'summary' => $this->cleanSummary($row['summary']),
                'body' => null,
                'publication_id' => $publication->id,
                'category_id' => $sectionId,
                'external_url' => $hasUrl ? $this->cleanUrl($row['url']) : null,
                // No URL means not yet publishable — see the class docblock.
                'status' => $hasUrl ? 'published' : 'draft',
                'published_at' => Carbon::parse($row['date'])->setTime(9, 0),
                'is_featured' => false,
                'meta' => null,
            ];

            $item = ContentItem::firstOrNew(['slug' => Str::slug($row['title'])]);

            if ($item->exists) {
                $updated++;
            }

            $item->fill($attributes)->save();
            $item->topics()->sync($beatIds);

            $hasUrl ? $published++ : $drafts++;

            if ($row['beats'] === [] && in_array($index + 1, BEAT_REVIEW_REQUIRED, true)) {
                $needsBeatReview[] = $row['title'];
            }
        }

        $this->retireCatchAllCategory();

        $this->command?->info(sprintf(
            'Seeded %d published article(s) by Emrul Hasan Bappi (%d updated); %d awaiting URL backfill as draft.',
            $published,
            $updated,
            $drafts,
        ));

        if ($needsBeatReview !== []) {
            $this->command?->warn(sprintf(
                '%d piece(s) have no beat assigned — the headline did not identify one. Assign from Admin → Content → Topics: %s',
                count($needsBeatReview),
                implode('; ', $needsBeatReview),
            ));
        }
    }

    /**
     * Only his own outlet is seeded. Do not add others until he has actually
     * published there — the /publications page lists every publication, so an
     * unused outlet still implies a byline.
     */
    private function publication(): Publication
    {
        return Publication::firstOrCreate(
            ['slug' => 'the-daily-star'],
            ['name' => 'The Daily Star', 'website_url' => 'https://www.thedailystar.net',
                'description' => 'Bangladesh\'s leading English-language daily.']
        );
    }

    /**
     * Slug => primary key, for resolving the data file's slug references.
     *
     * @param  class-string<Model>  $model
     * @return Collection<string, int>
     */
    private function lookup(string $model, string $key): Collection
    {
        return $model::query()->pluck('id', $key);
    }

    /**
     * An earlier revision filed every piece under one "Human Rights, Crime &
     * Justice" catch-all category, before the outlet's real sections were
     * broken out. Now that nothing uses it, drop it — an empty category in the
     * admin panel is a trap for whoever edits next.
     */
    private function retireCatchAllCategory(): void
    {
        $catchAll = Category::where('slug', 'human-rights-crime-justice')->first();

        if (! $catchAll) {
            return;
        }

        if (ContentItem::where('category_id', $catchAll->id)->exists()) {
            return;
        }

        $catchAll->delete();
    }

    /**
     * Drop the export's tracking parameter so the live outbound link is clean.
     */
    private function cleanUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content']);

        if ($query === []) {
            return $parts['scheme'].'://'.$parts['host'].$parts['path'];
        }

        return $parts['scheme'].'://'.$parts['host'].$parts['path'].'?'.http_build_query($query);
    }

    /**
     * The source snippets are truncated mid-sentence; drop the trailing
     * ellipsis so the dek doesn't read as clipped on the page.
     */
    private function cleanSummary(?string $summary): ?string
    {
        if ($summary === null) {
            return null;
        }

        return trim((string) preg_replace('/\s*\.{2,}$/', '', trim($summary)));
    }

    /**
     * @return list<array{title: string, date: string, section: string, beats: list<string>, summary: ?string, url: ?string}>
     */
    private function articles(): array
    {
        return require database_path('seeders/data/published_work.php');
    }
}
