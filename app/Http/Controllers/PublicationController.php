<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Support\PageCache;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    /** Cached (ROADMAP Phase 8); invalidated on any publish/edit/delete. */
    public function index()
    {
        $data = PageCache::remember('publications:index', [], fn () => [
            'publications' => Publication::with('logo')
                ->withCount(['contentItems' => fn ($q) => $q->published()])
                ->orderBy('name')
                ->get(),
        ]);

        return view('publications.index', $data);
    }

    public function show(Request $request, string $slug)
    {
        $page = (int) $request->query('page', 1);

        $data = PageCache::remember('publications:show', ['slug' => $slug, 'page' => $page], function () use ($slug) {
            $publication = Publication::with('logo')
                ->where('slug', $slug)
                ->firstOrFail();

            $items = $this->abortIfPastLastPage(
                $publication->contentItems()
                    ->published()
                    ->with(['category', 'publication', 'featuredImage'])
                    ->orderByDesc('published_at')
                    ->paginate(12)
                    ->withQueryString()
            );

            return compact('publication', 'items');
        });

        return view('publications.show', $data);
    }
}
