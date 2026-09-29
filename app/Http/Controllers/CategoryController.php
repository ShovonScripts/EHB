<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Models\Topic;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The outlet's filing sections — "Crime & Justice", "Bangladesh", "News" and
 * so on — as browsable landing pages at /sections/{slug}.
 *
 * Naming: The Daily Star calls these "sections" and the column in the author
 * export is headed "Section", so that is the word the journalist and his
 * readers use. In the database they are `categories`, which is what makes
 * them a filter axis everywhere else (archive, section pages, admin). The
 * content_type pages at /articles, /investigations, /opinions are a
 * *different* axis — what kind of piece it is, not what beat it was filed
 * under — and they only ever list work hosted on this site.
 */
class CategoryController extends Controller
{
    /**
     * /sections — every section that has at least one published item,
     * with its count and date span. Cached (ROADMAP Phase 8).
     */
    public function index()
    {
        $data = PageCache::remember('sections:index', [], function () {
            $sections = Category::whereHas('contentItems', fn ($q) => $q->published())
                ->withCount(['contentItems' => fn ($q) => $q->published()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $spans = self::dateSpans()
                ->keyBy('category_id');

            $sections = $sections->map(function (Category $section) use ($spans) {
                $span = $spans->get($section->id);

                $section->setAttribute('first_at', self::toDate($span?->first_at));
                $section->setAttribute('last_at', self::toDate($span?->last_at));

                return $section;
            });

            return [
                'sections' => $sections,
                'total' => $sections->sum('content_items_count'),
            ];
        });

        return view('categories.index', $data);
    }

    /**
     * /sections/{slug} — the pieces filed under one section, filterable by
     * the topic dossiers that run through it.
     */
    public function show(Request $request, string $slug)
    {
        $topic = $request->query('topic') ?: null;
        $page = (int) $request->query('page', 1);

        $data = PageCache::remember(
            'sections:show',
            ['slug' => $slug, 'topic' => $topic, 'page' => $page],
            function () use ($slug, $topic) {
                $section = Category::where('slug', $slug)
                    ->whereHas('contentItems', fn ($q) => $q->published())
                    ->firstOrFail();

                $query = ContentItem::published()
                    ->with(['category', 'publication', 'featuredImage', 'topics'])
                    ->where('category_id', $section->id)
                    ->orderByDesc('published_at');

                if ($topic) {
                    $query->whereHas('topics', fn ($q) => $q->where('slug', $topic));
                }

                // Dossiers that actually have pieces in this section, so the
                // chip row is never a row of dead links.
                $topics = Topic::whereHas('contentItems', fn ($q) => $q
                    ->published()
                    ->where('category_id', $section->id)
                )
                    ->withCount(['contentItems' => fn ($q) => $q
                        ->published()
                        ->where('category_id', $section->id),
                    ])
                    ->orderByDesc('content_items_count')
                    ->orderBy('name')
                    ->get();

                $span = ContentItem::published()
                    ->where('category_id', $section->id)
                    ->whereNotNull('published_at')
                    ->selectRaw('MIN(published_at) as first_at, MAX(published_at) as last_at')
                    ->first();

                return [
                    'section' => $section,
                    'items' => $this->abortIfPastLastPage($query->paginate(20)->withQueryString()),
                    'topics' => $topics,
                    'activeTopic' => $topic,
                    'total' => ContentItem::published()->where('category_id', $section->id)->count(),
                    'firstAt' => self::toDate($span?->first_at),
                    'lastAt' => self::toDate($span?->last_at),
                ];
            }
        );

        return view('categories.show', $data);
    }

    /**
     * First/last filed date per section, in one grouped query rather than a
     * sub-select per section.
     */
    private static function dateSpans(): Collection
    {
        return ContentItem::published()
            ->whereNotNull('published_at')
            ->selectRaw('category_id, MIN(published_at) as first_at, MAX(published_at) as last_at')
            ->groupBy('category_id')
            ->get();
    }

    /**
     * MIN()/MAX() come back from the driver as strings, so they need casting
     * before a view calls ->format() on them.
     */
    private static function toDate(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
