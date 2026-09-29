<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\WorkController;
use App\Support\ContentTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Core pages ────────────────────────────────────────────────
Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// ── SEO endpoints (SEO.md §8–§10) ────────────────────────────
// Registered before the section {slug} routes so /{section}/feed
// matches the feed route, not the detail-page wildcard.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/feed', [FeedController::class, 'site'])->name('feed');

foreach (['articles', 'investigations', 'interviews', 'opinions', 'multimedia'] as $feedSection) {
    Route::get('/'.$feedSection.'/feed', [FeedController::class, 'section'])
        ->defaults('section', $feedSection)
        ->name('feed.'.$feedSection);
}

// ── Content sections (articles, investigations, interviews,
//    opinions, multimedia, other) — FRONTEND.md §1 ─────────────
foreach (ContentTypes::SECTIONS as $key => $section) {
    Route::get('/'.$key, [SectionController::class, 'index'])
        ->defaults('section', $key)
        ->name('section.'.$key);

    Route::get('/'.$key.'/{slug}', [SectionController::class, 'show'])
        ->defaults('section', $key)
        ->name('section.'.$key.'.show');
}

// ── External work archive (cross-type) ───────────────────────
Route::get('/work', [WorkController::class, 'index'])->name('work.index');
Route::get('/work/{slug}', [WorkController::class, 'show'])->name('work.show');

// ── Outlet filing sections (The Daily Star's own sections) ───
// These are `categories` in the database, surfaced as landing pages. Distinct
// from the content_type pages above, which answer "what kind of piece" rather
// than "which beat was it filed under".
Route::get('/sections', [CategoryController::class, 'index'])->name('sections.index');
Route::get('/sections/{slug}', [CategoryController::class, 'show'])->name('sections.show');

// ── Publications ─────────────────────────────────────────────
Route::get('/publications', [PublicationController::class, 'index'])->name('publications.index');
Route::get('/publications/{slug}', [PublicationController::class, 'show'])->name('publications.show');

// ── Topic dossiers ───────────────────────────────────────────
Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
Route::get('/topics/{slug}', [TopicController::class, 'show'])->name('topics.show');

// ── Archive & search ─────────────────────────────────────────
Route::get('/archive', [ArchiveController::class, 'index'])->name('archive');
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:search')
    ->name('search');

// ── Test route for debug-leak verification ───────────────────
if (app()->environment('testing')) {
    Route::get('/trigger-500', function () {
        throw new RuntimeException('Test 500 error');
    });
}

// ── CMS pages ─────────────────────────────────────────────────
// Admin-authored pages (DATABASE.md §97), served at their own slug.
//
// This is a catch-all, so two details matter:
//
// 1. It is registered LAST, after every named route, so /archive, /articles,
//    /up, /admin/* and the test route all win over it. The negative lookahead
//    is a second belt-and-braces guard on the prefixes that are not ours.
// 2. It is registered for every HTTP method rather than GET only. A GET-only
//    wildcard would make Laravel answer an unknown POST path with
//    405 Method Not Allowed (the path matches, the verb does not) instead of
//    the 404 this site has always returned. Non-GET verbs are rejected inside
//    the closure so the 404 behaviour is preserved exactly.
Route::match(
    ['get', 'head', 'post', 'put', 'patch', 'delete', 'options'],
    '/{slug}',
    function (Request $request, string $slug) {
        if (! $request->isMethod('GET', 'HEAD')) {
            abort(404);
        }

        return app(PageController::class)->show($request, $slug);
    }
)
    ->where('slug', '^(?!admin$|up$|livewire$|build$|css$|js$|fonts$|storage$).*$')
    ->name('pages.show');
