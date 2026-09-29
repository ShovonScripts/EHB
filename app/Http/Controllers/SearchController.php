<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * /search — DB-driven full-text/LIKE search across title,
     * summary, and body (docs: DB-driven search for V1).
     * Always noindex,follow (SEO.md §9).
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $items = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

            $items = ContentItem::published()
                ->with(['category', 'publication', 'featuredImage'])
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('body', 'like', $like);
                })
                ->orderByDesc('published_at')
                ->paginate(12)
                ->withQueryString();

            $this->abortIfPastLastPage($items);
        }

        return view('search', compact('q', 'items'));
    }
}
