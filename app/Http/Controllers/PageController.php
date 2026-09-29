<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\PageCache;
use Illuminate\Http\Request;

/**
 * Standalone CMS pages authored in the admin panel.
 *
 * `DATABASE.md` §97 describes the `pages` table as "small number of
 * static/semi-static pages ... CMS-editable copy outside of dedicated fields".
 * Each page is served at its own slug; the ones keyed `about` and `contact`
 * additionally act as the intro block for those routes (see AboutController /
 * ContactController), which is what the table was originally specified for.
 */
class PageController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $data = PageCache::remember('page:show', ['slug' => $slug], fn () => [
            'page' => Page::with('ogImage')->where('slug', $slug)->firstOrFail(),
        ]);

        return view('pages.show', $data);
    }
}
