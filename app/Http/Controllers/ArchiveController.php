<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Publication;
use App\Models\Tag;
use App\Models\Topic;
use App\Support\ContentTypes;
use App\Support\PageCache;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    /**
     * /archive — the full chronological, filterable index across
     * all content types (FR-111). Filtered permutations are
     * noindex,follow per SEO.md §13.
     */
    public function index(Request $request)
    {
        $filters = [
            'type' => $request->query('type'),
            'source' => $request->query('source'),
            'category' => $request->query('category'),
            'tag' => $request->query('tag'),
            'topic' => $request->query('topic'),
            'publication' => $request->query('publication'),
            'year' => $request->query('year'),
        ];

        $hasFilters = (bool) array_filter($filters);

        $data = PageCache::remember(
            'archive',
            ['filters' => $filters, 'page' => (int) $request->query('page', 1)],
            fn () => $this->buildData($filters)
        );

        return view('archive', $data + [
            'filters' => $filters,
            'hasFilters' => $hasFilters,
        ]);
    }

    /**
     * Filter bar options + result page (cached; ROADMAP Phase 8).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildData(array $filters): array
    {
        $query = ContentItem::published()
            ->with(['category', 'publication', 'featuredImage'])
            ->orderByDesc('published_at');

        if ($filters['type'] && array_key_exists($filters['type'], ContentTypes::typeOptions())) {
            $query->where('content_type', $filters['type']);
        }

        if (in_array($filters['source'], ['internal', 'external'], true)) {
            $query->where('source_type', $filters['source']);
        }

        if ($filters['category']) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if ($filters['tag']) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $filters['tag']));
        }

        if ($filters['topic']) {
            $query->whereHas('topics', fn ($q) => $q->where('slug', $filters['topic']));
        }

        if ($filters['publication']) {
            $query->whereHas('publication', fn ($q) => $q->where('slug', $filters['publication']));
        }

        if ($filters['year'] && ctype_digit((string) $filters['year'])) {
            $query->whereYear('published_at', (int) $filters['year']);
        }

        return [
            'items' => $this->abortIfPastLastPage($query->paginate(20)->withQueryString()),
            'types' => ContentTypes::typeOptions(),
            // Only offer terms that can actually match something, so the filter
            // form never submits to an empty result set.
            'categories' => Category::whereHas('contentItems', fn ($q) => $q->published())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'tags' => Tag::whereHas('contentItems', fn ($q) => $q->published())
                ->orderBy('name')
                ->get(),
            'topics' => Topic::whereHas('contentItems', fn ($q) => $q->published())
                ->orderBy('name')
                ->get(),
            'publications' => Publication::whereHas('contentItems', fn ($q) => $q->published())
                ->orderBy('name')
                ->get(),
            // Extract years in PHP — YEAR()/strftime differ per DB driver.
            'years' => ContentItem::published()
                ->whereNotNull('published_at')
                ->pluck('published_at')
                ->map(fn ($date) => (int) $date->format('Y'))
                ->unique()
                ->sortDesc()
                ->values(),
        ];
    }
}
