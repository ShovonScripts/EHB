<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentItem;
use App\Support\ContentTypes;
use App\Support\PageCache;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Listing page for a section, e.g. /investigations.
     *
     * Scoped to work hosted on this site. A section page that also listed
     * external items would render a card for every one of the 210 outlet
     * pieces, and each click would 301 to /work/{slug} (see show()), so the
     * page would be an index of redirects rather than a place to read. The
     * outlet archive lives at /work, browsable by section and topic.
     */
    public function index(Request $request)
    {
        // Route params are injected positionally; read the section by name
        // so URI params (slug) and defaults (section) can't swap places.
        $section = (string) $request->route('section');
        $definition = ContentTypes::SECTIONS[$section] ?? abort(404);

        $category = $request->query('category') ?: null;
        $page = (int) $request->query('page', 1);

        $data = PageCache::remember(
            'section:index',
            ['section' => $section, 'category' => $category, 'page' => $page],
            function () use ($section, $definition, $category) {
                $query = ContentItem::published()
                    ->internal()
                    ->with(['category', 'publication', 'featuredImage'])
                    ->ofTypes($definition['types'])
                    ->orderByDesc('published_at');

                if ($category) {
                    $query->whereHas('category', fn ($q) => $q->where('slug', $category));
                }

                return [
                    'section' => $section,
                    'definition' => $definition,
                    'items' => $this->abortIfPastLastPage($query->paginate(12)->withQueryString()),
                    // Only categories that actually have published items in this
                    // section, otherwise the filter bar links to empty pages.
                    'categories' => Category::whereHas('contentItems', fn ($q) => $q
                        ->published()
                        ->internal()
                        ->ofTypes($definition['types'])
                    )
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(),
                    'activeCategory' => $category,
                ];
            }
        );

        return view('sections.index', $data);
    }

    /**
     * Detail page: internal renders full body, external renders the
     * summary + "Read Original Article" callout (FR-114 / FR-115).
     */
    public function show(Request $request, string $slug)
    {
        $section = (string) $request->route('section');
        $definition = ContentTypes::SECTIONS[$section] ?? abort(404);

        // Cheap lookup first: external works live canonically at /work/{slug}
        // (SEO.md §3), and the 301 must never be cached as page data.
        $isExternal = ContentItem::published()
            ->where('slug', $slug)
            ->whereIn('content_type', $definition['types'])
            ->where('source_type', 'external')
            ->exists();

        if ($isExternal) {
            return redirect()->route('work.show', $slug, 301);
        }

        $data = PageCache::remember('section:show', ['section' => $section, 'slug' => $slug], function () use ($section, $definition, $slug) {
            $item = ContentItem::published()
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
                ->whereIn('content_type', $definition['types'])
                ->firstOrFail();

            $related = $item->relatedContentWithFallback('category_id');

            return [
                'section' => $section,
                'definition' => $definition,
                'item' => $item,
                'related' => $related,
            ];
        });

        return view('sections.show', $data);
    }
}
