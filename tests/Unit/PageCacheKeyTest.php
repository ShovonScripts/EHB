<?php

namespace Tests\Unit;

use App\Support\PageCache;
use Tests\TestCase;

/**
 * Cache-key canonicalization.
 *
 * The cache key is derived from request input, which makes an unbounded key
 * space a storage-exhaustion vector rather than a tidiness issue: each
 * distinct key is a `mediumText` row in the `cache` table holding a whole
 * rendered page, and rows are only reaped when that exact key is read again.
 * An anonymous visitor appending array parameters could grow the table
 * indefinitely.
 *
 * These tests pin both halves of the required behaviour, which pull in
 * opposite directions: equivalent requests must share a key, and distinct
 * requests must never share one.
 */
class PageCacheKeyTest extends TestCase
{
    private function key(array $parameters, string $url = 'http://localhost/articles'): string
    {
        return PageCache::key('response', [$url, $parameters]);
    }

    public function test_parameter_order_does_not_change_the_key(): void
    {
        $this->assertSame(
            $this->key(['a' => '1', 'b' => '2']),
            $this->key(['b' => '2', 'a' => '1'])
        );
    }

    /** `?page=2` and `?page[]=2` mean the same thing to every controller. */
    public function test_scalar_and_single_value_array_agree(): void
    {
        $this->assertSame($this->key(['page' => '2']), $this->key(['page' => ['2']]));
    }

    public function test_array_member_order_does_not_change_the_key(): void
    {
        $this->assertSame(
            $this->key(['tag' => ['a', 'b']]),
            $this->key(['tag' => ['b', 'a']])
        );
    }

    public function test_duplicate_array_members_collapse(): void
    {
        $this->assertSame(
            $this->key(['tag' => ['a', 'a']]),
            $this->key(['tag' => ['a']])
        );
    }

    /**
     * Controllers read filters with `?:`, so an empty value is already
     * "absent" everywhere. `?year=` must not get its own row.
     */
    public function test_empty_and_null_parameters_are_dropped(): void
    {
        $noParameter = $this->key([]);

        $this->assertSame($noParameter, $this->key(['year' => '']));
        $this->assertSame($noParameter, $this->key(['year' => null]));
        $this->assertSame($noParameter, $this->key(['year' => []]));
    }

    public function test_booleans_normalize_consistently(): void
    {
        $this->assertSame($this->key(['featured' => true]), $this->key(['featured' => '1']));
        $this->assertSame($this->key(['featured' => false]), $this->key(['featured' => '0']));
    }

    // ── Distinctness: the half that must never regress ───────────────

    public function test_different_urls_get_different_keys(): void
    {
        $this->assertNotSame(
            PageCache::key('response', ['http://localhost/articles', []]),
            PageCache::key('response', ['http://localhost/work', []])
        );
    }

    public function test_different_namespaces_get_different_keys(): void
    {
        $this->assertNotSame(
            PageCache::key('response', ['http://localhost/articles', []]),
            PageCache::key('work:show', ['http://localhost/articles', []])
        );
    }

    /** Pagination must not collapse, or every page would serve page 1. */
    public function test_different_page_numbers_get_different_keys(): void
    {
        $this->assertNotSame($this->key(['page' => '1']), $this->key(['page' => '2']));
        $this->assertNotSame($this->key(['page' => '2']), $this->key(['page' => '3']));
    }

    /**
     * The regression this file exists for.
     *
     * An oversized parameter must be digested, not dropped. Dropping it makes
     * the key identical to the one for the same URL without the parameter, so
     * `/articles?q=<5KB>` would be served the cached `/articles` page — wrong
     * content for the request that asked.
     */
    public function test_oversized_values_do_not_collide_with_absent_ones(): void
    {
        $oversized = str_repeat('a', 5000);

        $this->assertNotSame(
            $this->key([]),
            $this->key(['q' => $oversized]),
            'An oversized parameter must not collapse into the key for its absence.'
        );
    }

    public function test_distinct_oversized_values_do_not_collide(): void
    {
        $this->assertNotSame(
            $this->key(['q' => str_repeat('a', 5000)]),
            $this->key(['q' => str_repeat('b', 5000)])
        );
    }

    /** A huge array must not collapse into the key for its absence either. */
    public function test_huge_arrays_do_not_collide_with_absent_ones(): void
    {
        $this->assertNotSame(
            $this->key([]),
            $this->key(['page' => array_map('strval', range(1, 10000))])
        );
    }

    /**
     * The key must fit the store's key column (`string`, 255 chars on MySQL)
     * however large the request.
     */
    public function test_key_length_is_bounded_regardless_of_input_size(): void
    {
        $keys = [
            $this->key(['q' => str_repeat('a', 100000)]),
            $this->key(['page' => array_map('strval', range(1, 100000))]),
            PageCache::key('a-namespace-longer-than-the-whole-key-budget', [
                'http://localhost/articles',
                ['q' => str_repeat('z', 50000)],
            ]),
        ];

        foreach ($keys as $key) {
            $this->assertLessThanOrEqual(
                255,
                strlen($key),
                'Cache keys must fit the cache store key column.'
            );
        }
    }

    public function test_nested_arrays_are_reduced_to_leaves(): void
    {
        // No controller reads `?year[a]=b`, but the key space must stay finite.
        $this->assertSame(
            $this->key(['year' => 'b']),
            $this->key(['year' => ['a' => 'b']])
        );
    }

    /** The normalizer is public so it can be asserted directly. */
    public function test_normalize_returns_a_sorted_string_keyed_array(): void
    {
        $this->assertSame(
            ['a' => '1', 'b' => '2'],
            PageCache::normalize(['b' => 2, 'a' => '1', 'c' => null, 'd' => ''])
        );
    }
}
