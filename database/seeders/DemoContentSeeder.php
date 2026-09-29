<?php

namespace Database\Seeders;

use App\Models\CareerHistory;
use App\Models\Category;
use App\Models\ContentItem;
use App\Models\JournalistProfile;
use App\Models\Publication;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the journalist's real identity (name, title, bio line, beats, career)
 * plus placeholder content so every page, content type and layout can be
 * reviewed end-to-end before he publishes his own work.
 *
 * Rule for this file: the *identity* is real and was supplied by the
 * journalist. Everything else is a placeholder and is labelled as one. No
 * award, degree, byline, outlet URL or social handle is invented — those are
 * facts only he can confirm, and they are added from the admin panel.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'owner')->first()
            ?? User::first()
            ?? User::factory()->create(['role' => 'owner']);

        // ── Taxonomy ──────────────────────────────────────────
        $categories = collect([
            ['name' => 'Crime', 'slug' => 'crime', 'sort_order' => 1],
            ['name' => 'Justice', 'slug' => 'justice', 'sort_order' => 2],
            ['name' => 'Human Rights', 'slug' => 'human-rights', 'sort_order' => 3],
            ['name' => 'Bangladesh', 'slug' => 'bangladesh', 'sort_order' => 4],
        ])->map(fn (array $c) => Category::firstOrCreate(['slug' => $c['slug']], $c));

        $tags = collect(['Courtrooms', 'Law Enforcement', 'Rights', 'Policy'])
            ->map(fn (string $name) => Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            ));

        $topics = collect([
            ['name' => 'Human Rights', 'slug' => 'human-rights',
                'description' => 'Reporting on human rights, civil liberties, and accountability.'],
            ['name' => 'Crime & Justice', 'slug' => 'crime-justice',
                'description' => 'Coverage of crime, policing, courts, and the justice system in Bangladesh.'],
        ])->map(fn (array $t) => Topic::firstOrCreate(['slug' => $t['slug']], $t));

        // ── Publications ──────────────────────────────────────
        // Only his own outlet is seeded. Do not add others until he has
        // actually published there — the /publications page lists every
        // publication, so an unused outlet still implies a byline.
        $dailyStar = Publication::firstOrCreate(
            ['slug' => 'the-daily-star'],
            ['name' => 'The Daily Star', 'website_url' => 'https://www.thedailystar.net',
                'description' => 'Bangladesh\'s leading English-language daily.']
        );

        // ── Profile ───────────────────────────────────────────
        // Identity and beats are the journalist's own, as supplied. Nothing
        // here is invented: no awards, no education, no bylines, and no
        // social handles, because those are facts only he can confirm. The
        // empty ones are filled from Admin → Journalist → Profile.
        $profile = JournalistProfile::firstOrCreate(
            ['user_id' => $author->id],
            [
                'name' => 'Emrul Hasan Bappi',
                'title' => 'Staff Journalist, The Daily Star',
                // His author bio line as it appears on the paper's site.
                'short_bio' => 'Covering Human Rights, Crime & Justice.',
                'long_bio' => "Emrul Hasan Bappi is a staff journalist at The Daily Star, Bangladesh's leading English-language daily, where his author bio reads \"Covering Human Rights, Crime & Justice.\"\n\nHis byline has run on the paper's crime and justice desk and its Bangladesh news desk since 2019.\n\nThis site is his own archive rather than a property of the paper: every piece he has written — hosted here or published in another outlet — collected in one searchable place, with outbound links always pointing back to the original publisher.",
                'skills' => ['Human Rights', 'Crime & Justice', 'Bangladesh'],
                // Left empty on purpose — placeholder handles would otherwise
                // ship as if they were his real accounts.
                'social_links' => [
                    'twitter' => null,
                    'linkedin' => null,
                    'email' => null,
                ],
            ]
        );

        $profile->publications()->syncWithoutDetaching([$dailyStar->id]);

        CareerHistory::firstOrCreate(
            ['journalist_profile_id' => $profile->id, 'role' => 'Staff Journalist'],
            ['organization' => 'The Daily Star', 'start_date' => '2019-01-01',
                'end_date' => null,
                'description' => 'Crime and justice desk and Bangladesh news desk, covering human rights, crime and justice.',
                'sort_order' => 1]
        );

        // Education and awards are intentionally NOT seeded. Inventing a degree
        // or an award for a real, named journalist is not acceptable placeholder
        // content — add the real ones from Admin → Journalist.

        $this->seedContent($author, $categories, $tags, $topics, $dailyStar);
        $this->seedSettings();
    }

    /**
     * Placeholder entries that exist only to exercise every content_type and
     * layout. None of them is a real report and none claims real publication.
     *
     * The external entries deliberately point at example.com rather than a
     * real article: a summary page reading "Published in The Daily Star" and
     * linking to the paper's homepage would be a fabricated byline, which is
     * not acceptable content to ship under a real journalist's name. Replace
     * these with genuine external work — real headline, real outlet, real URL.
     */
    private function seedContent(User $author, $categories, $tags, $topics, $dailyStar): void
    {
        $now = now();

        $items = [
            // Internal news (featured)
            ['content_type' => 'news', 'source_type' => 'internal', 'slug' => 'demo-internal-news-one',
                'title' => 'PLACEHOLDER — Internal news item',
                'summary' => 'Placeholder entry demonstrating the on-site reading experience. Delete or replace from Admin → Content.',
                'body' => '<p>This is placeholder body copy for an internally hosted item. It includes <strong>formatted text</strong>, a link to <a href="https://example.com">an external source</a>, and enough paragraphs to show the reading-column typography.</p><blockquote>Pull quotes use the accent border and serif italic styling.</blockquote><p>Replace this from the admin panel — every field maps to the content model in CONTENT_MODEL.md.</p>',
                'category' => $categories[0], 'is_featured' => true, 'published_at' => $now->copy()->subDays(2)],

            // Investigation (featured)
            ['content_type' => 'investigation', 'source_type' => 'internal', 'slug' => 'demo-investigation-one',
                'title' => 'PLACEHOLDER — Investigation / long-form item',
                'summary' => 'Placeholder long-form entry showing long-form typography and the related-content fallback.',
                'body' => '<p>Long-form placeholder copy. Investigations are a content_type on the same unified model — not a separate table — which keeps search, archive, and taxonomies unified.</p><p>Each paragraph demonstrates the 1.7 line-height reading experience designed for long-form journalism.</p>',
                'category' => $categories[2], 'is_featured' => true, 'topics' => [$topics[1]],
                'published_at' => $now->copy()->subDays(10)],

            // Interview
            ['content_type' => 'interview', 'source_type' => 'internal', 'slug' => 'demo-interview-one',
                'title' => 'PLACEHOLDER — Interview item',
                'summary' => 'Placeholder Q&A entry demonstrating the interviewee metadata fields.',
                'body' => '<p>Placeholder transcript copy. The interviewee name and title appear in the article header via the type-specific fields.</p>',
                'interviewee_name' => 'Interviewee Name', 'interviewee_title' => 'Their Role',
                'category' => $categories[0], 'published_at' => $now->copy()->subDays(20)],

            // Opinion
            ['content_type' => 'opinion', 'source_type' => 'internal', 'slug' => 'demo-opinion-one',
                'title' => 'PLACEHOLDER — Opinion / analysis item',
                'summary' => 'Placeholder first-person analysis piece, hosted on-site.',
                'body' => '<p>Placeholder opinion copy. Opinion/Analysis is a content_type, sharing all infrastructure with news and investigations.</p>',
                'category' => $categories[1], 'published_at' => $now->copy()->subDays(30)],

            // External work — outlet linked, URL is a placeholder, not a byline.
            ['content_type' => 'news', 'source_type' => 'external', 'slug' => 'demo-external-news-one',
                'title' => 'PLACEHOLDER — External work (link-out entry)',
                'summary' => 'Demonstrates the external-work pattern: this site stores only a summary and an outbound link, never the full text. Add the real article URL before publishing.',
                'publication' => $dailyStar, 'external_url' => 'https://example.com/replace-with-real-article-url',
                'category' => $categories[3], 'tags' => [$tags[1]], 'is_featured' => true,
                'published_at' => $now->copy()->subDays(5)],

            ['content_type' => 'opinion', 'source_type' => 'external', 'slug' => 'demo-external-opinion-one',
                'title' => 'PLACEHOLDER — Second external work entry',
                'summary' => 'Second link-out placeholder — demonstrates the outbound call-to-action and the publication filter.',
                'publication' => $dailyStar, 'external_url' => 'https://example.com/replace-with-real-article-url-2',
                'category' => $categories[2], 'topics' => [$topics[0]], 'tags' => [$tags[2]],
                'published_at' => $now->copy()->subDays(8)],

            // Draft (must NOT appear publicly)
            ['content_type' => 'news', 'source_type' => 'internal', 'slug' => 'demo-draft-hidden',
                'title' => 'PLACEHOLDER — Draft (not publicly visible)',
                'summary' => 'Draft item used to verify visibility rules.',
                'body' => '<p>Draft body.</p>', 'status' => 'draft',
                'category' => $categories[0], 'published_at' => null],

            // Scheduled in future (must NOT appear publicly)
            ['content_type' => 'news', 'source_type' => 'internal', 'slug' => 'demo-scheduled-future-hidden',
                'title' => 'PLACEHOLDER — Scheduled (not publicly visible yet)',
                'summary' => 'Scheduled item used to verify visibility rules.',
                'body' => '<p>Scheduled body.</p>', 'status' => 'scheduled',
                'category' => $categories[0], 'published_at' => $now->copy()->addDays(7)],
        ];

        foreach ($items as $data) {
            $tagsFor = $data['tags'] ?? [];
            $topicsFor = $data['topics'] ?? [];
            unset($data['tags'], $data['topics']);

            $category = $data['category'] ?? null;
            $publication = $data['publication'] ?? null;
            unset($data['category'], $data['publication']);

            $data += [
                'author_id' => $author->id,
                'status' => 'published',
                'category_id' => $category?->id,
                'publication_id' => $publication?->id,
                'meta' => null,
            ];

            $item = ContentItem::firstOrCreate(['slug' => $data['slug']], $data);

            if ($tagsFor) {
                $item->tags()->syncWithoutDetaching(collect($tagsFor)->pluck('id'));
            }
            if ($topicsFor) {
                $item->topics()->syncWithoutDetaching(collect($topicsFor)->pluck('id'));
            }
        }
    }

    /**
     * Site-wide settings (FR-406). Read on the front end by
     * App\Support\SiteSettings — changing a row here changes the site.
     *
     * `google_analytics_id` is intentionally NOT rendered anywhere: the site
     * ships a strict CSP (SECURITY.md §19) with no third-party script
     * origins, and a self-hosted first-party analytics script is the
     * supported path. See SiteSettings for the full reasoning.
     */
    private function seedSettings(): void
    {
        $settings = [
            'site_name' => 'Emrul Hasan Bappi',
            'site_tagline' => 'Covering Human Rights, Crime & Justice',
            'default_seo_description' => 'Personal archive and work of Emrul Hasan Bappi, staff journalist at The Daily Star covering human rights, crime and justice.',
            'default_og_image_media_id' => null,
            'default_card_image_media_id' => null,
            'google_analytics_id' => null,
        ];

        foreach ($settings as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
