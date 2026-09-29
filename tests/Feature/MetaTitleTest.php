<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Support\MetaTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `<title>` composition (SEO.md §1).
 *
 * The bug these exist for: appending the site name unconditionally produced
 * titles no search result could show. 75% of the detail pages exceeded 60
 * characters, the worst at 120, which pushed the site name off the end of
 * every one of those results — the branding was invisible exactly where it
 * helps most, and what survived was the front of a cut-off sentence.
 */
class MetaTitleTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'Emrul Hasan Bappi';

    public function test_a_short_title_keeps_the_site_suffix(): void
    {
        $this->assertSame(
            'About | Emrul Hasan Bappi',
            MetaTitle::compose('About', self::SITE)
        );
    }

    public function test_a_title_already_naming_the_site_is_not_suffixed_twice(): void
    {
        $this->assertSame(
            'About Emrul Hasan Bappi',
            MetaTitle::compose('About Emrul Hasan Bappi', self::SITE)
        );
    }

    public function test_no_title_falls_back_to_name_and_tagline(): void
    {
        $this->assertSame(
            'Emrul Hasan Bappi — Independent journalist',
            MetaTitle::compose(null, self::SITE, 'Independent journalist')
        );
    }

    public function test_an_empty_site_name_is_tolerated(): void
    {
        $result = MetaTitle::compose('Some page title', '');

        $this->assertSame('Some page title', $result);
    }

    /**
     * The actual defect. A real outlet headline plus the site name must still
     * fit the budget, and the site name must be the part that survives —
     * otherwise the truncation has cost the branding on every long-titled page.
     */
    public function test_a_long_title_is_cut_but_keeps_the_site_suffix(): void
    {
        $headline = 'Unprofessional, deeply worrying: Court slams ACC for politically-motivated prosecution in Zia Orphanage graft case';

        $result = MetaTitle::compose($headline, self::SITE);

        $this->assertLessThanOrEqual(MetaTitle::LIMIT, mb_strlen($result));
        $this->assertStringEndsWith('| Emrul Hasan Bappi', $result);
        $this->assertStringContainsString('Unprofessional', $result);
    }

    public function test_truncation_is_marked_with_an_ellipsis(): void
    {
        $result = MetaTitle::compose(str_repeat('very long headline ', 10), self::SITE);

        $this->assertStringContainsString('…', $result, 'A cut title must not look complete.');
    }

    /** A title that already fits must not gain a spurious ellipsis. */
    public function test_an_untruncated_title_has_no_ellipsis(): void
    {
        $this->assertStringNotContainsString('…', MetaTitle::compose('About', self::SITE));
    }

    public function test_every_generated_title_fits_the_budget(): void
    {
        $headlines = ContentItem::published()
            ->pluck('title')
            ->push('A', 'Plain', 'Bangla বিচারের রায়ে দুর্নীতির ফাঁস খুলে দিলেন আদালত', str_repeat('x', 300))
            ->all();

        foreach ($headlines as $headline) {
            foreach ([self::SITE, 'A Much Longer Site Name Than Expected Here', ''] as $site) {
                $result = MetaTitle::compose((string) $headline, $site);

                $this->assertLessThanOrEqual(
                    MetaTitle::LIMIT,
                    mb_strlen($result),
                    "Title over budget for: {$headline}"
                );
                $this->assertNotSame('', $result, 'A title must never render empty.');
            }
        }
    }

    /** Titles carry Bangla; the budget must be counted in characters, not bytes. */
    public function test_multibyte_titles_are_counted_in_characters(): void
    {
        $bangla = 'বিচারের রায়ে দুর্নীতির ফাঁস খুলে দিলেন আদালত, তদন্তে অসংগতি';

        $result = MetaTitle::compose($bangla, self::SITE);

        $this->assertLessThanOrEqual(MetaTitle::LIMIT, mb_strlen($result));
        $this->assertStringEndsWith(self::SITE, $result);
    }
}
