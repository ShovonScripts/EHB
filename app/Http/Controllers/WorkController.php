<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Topic;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkController extends Controller
{
    /**
     * /work — the reporting archive: every piece published in another outlet,
     * newest first, filterable by the outlet's section, by editorial dossier
     * and by year.
     *
     * This is the site's primary browse surface. /sections and /topics are the
     * same collection sliced two other ways and each has its own landing page,
     * so the filters here are links to those rather than a second, competing
     * way of navigating. Cached (ROADMAP Phase 8); invalidated on any
     * publish/edit/delete.
     */
    public function index(Request $request)
    {
        $filters = [
            'publication' => $request->query('publication') ?: null,
            'category' => $request->query('category') ?: null,
            'topic' => $request->query('topic') ?: null,
            'year' => $request->query('year') ?: null,
        ];

        $data = PageCache::remember(
            'work:index',
            $filters + ['page' => (int) $request->query('page', 1)],
            fn () => $this->buildData($filters)
        );

        return view('work.index', $data + [
            'filters' => $filters,
            // SEO.md §13 — every filter state duplicates a page that already
            // exists (/sections/{slug} or /topics/{slug}), so only the unfiltered
            // archive is indexable.
            'hasFilters' => (bool) array_filter($filters),
        ]);
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, mixed>
     */
    private function buildData(array $filters): array
    {
        $query = ContentItem::published()
            ->external()
            ->with(['category', 'publication', 'featuredImage'])
            ->orderByDesc('published_at');

        if ($filters['publication']) {
            $query->whereHas('publication', fn ($q) => $q->where('slug', $filters['publication']));
        }

        if ($filters['category']) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if ($filters['topic']) {
            $query->whereHas('topics', fn ($q) => $q->where('slug', $filters['topic']));
        }

        if ($filters['year'] && ctype_digit((string) $filters['year'])) {
            $query->whereYear('published_at', (int) $filters['year']);
        }

        $sections = Category::whereHas('contentItems', fn ($q) => $q->published()->external())
            ->withCount(['contentItems' => fn ($q) => $q->published()->external()])
            ->orderByDesc('content_items_count')
            ->orderBy('name')
            ->get();

        $topics = Topic::whereHas('contentItems', fn ($q) => $q->published()->external())
            ->withCount(['contentItems' => fn ($q) => $q->published()->external()])
            ->orderByDesc('content_items_count')
            ->orderBy('name')
            ->get();

        $years = ContentItem::published()
            ->external()
            ->whereNotNull('published_at')
            ->pluck('published_at')
            ->map(fn ($date) => (int) $date->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        $span = ContentItem::published()
            ->external()
            ->whereNotNull('published_at')
            ->selectRaw('MIN(published_at) as first_at, MAX(published_at) as last_at')
            ->first();

        return [
            'items' => $this->abortIfPastLastPage($query->paginate(20)->withQueryString()),
            'activePublication' => $filters['publication'],
            'activeCategory' => $filters['category'],
            'activeTopic' => $filters['topic'],
            'activeYear' => $filters['year'],
            'total' => ContentItem::published()->external()->count(),
            'firstAt' => $span?->first_at ? Carbon::parse($span->first_at) : null,
            'lastAt' => $span?->last_at ? Carbon::parse($span->last_at) : null,
            'sections' => $sections,
            'topics' => $topics,
            'years' => $years,
            // Chip URLs are built here, not in the view: every chip is the
            // current filter set with one axis swapped, so the three axes
            // compose and a chip never silently drops the other two.
            'sectionChips' => $this->chips($filters, 'category', $sections->all()),
            'topicChips' => $this->chips($filters, 'topic', $topics->all()),
            'yearChips' => $this->chips(
                $filters,
                'year',
                $years->map(fn ($year) => ['slug' => (string) $year, 'name' => (string) $year])->all()
            ),
            'clearAll' => route('work.index'),
        ];
    }

    /**
     * One chip row: the "All" reset plus one link per option, each carrying
     * the other axes' active values.
     *
     * @param  array<string, string|null>  $filters
     * @param  array<int, Category|Topic|array<string, string>>  $options
     * @return array<int, array{label: string, value: string|null, url: string, count: int|null, active: bool}>
     */
    private function chips(array $filters, string $axis, array $options): array
    {
        $chips = [[
            'label' => 'All',
            'value' => null,
            'url' => route('work.index', $this->withoutAxis($filters, $axis)),
            'count' => null,
            'active' => $filters[$axis] === null,
        ]];

        foreach ($options as $option) {
            $value = is_array($option) ? $option['slug'] : $option->slug;
            $label = is_array($option) ? $option['name'] : $option->name;

            $chips[] = [
                'label' => (string) $label,
                'value' => (string) $value,
                'url' => route('work.index', $this->withAxis($filters, $axis, (string) $value)),
                'count' => is_array($option) ? null : ($option->content_items_count ?? null),
                'active' => (string) $filters[$axis] === (string) $value,
            ];
        }

        return $chips;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, string>
     */
    private function withoutAxis(array $filters, string $axis): array
    {
        unset($filters[$axis]);

        return array_filter($filters);
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, string>
     */
    private function withAxis(array $filters, string $axis, string $value): array
    {
        $filters[$axis] = $value;

        return array_filter($filters);
    }

    /**
     * /work/{slug} — summary page + prominent outbound link (FR-114/115).
     */
    public function show(string $slug)
    {
        $data = PageCache::remember('work:show', ['slug' => $slug], function () use ($slug) {
            $item = ContentItem::published()
                ->external()
                ->with([
                    'author',
                    'category',
                    'publication.logo',
                    'featuredImage',
                    'tags',
                    'topics',
                    'relatedContent' => fn ($q) => $q->published()
                        ->with(['category', 'publication', 'featuredImage'])
                        ->limit(3),
                ])
                ->where('slug', $slug)
                ->firstOrFail();

            $related = $item->relatedContentWithFallback('publication_id', fn ($q) => $q->external());

            return [
                'item' => $item,
                'related' => $related,
            ];
        });

        return view('work.show', $data);
    }
}
