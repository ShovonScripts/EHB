<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Topic;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TopicController extends Controller
{
    /**
     * /topics — every dossier that has published pieces, with counts.
     *
     * The show() route existed with no index to reach it from, so a dossier
     * was only ever linkable by hand. Cached (ROADMAP Phase 8).
     */
    public function index()
    {
        $data = PageCache::remember('topics:index', [], function () {
            $topics = Topic::whereHas('contentItems', fn ($q) => $q->published())
                ->with(['featuredImage'])
                ->withCount(['contentItems' => fn ($q) => $q->published()])
                // Beats carry an explicit editorial order (TaxonomySeeder) so
                // the nav does not reshuffle every time a piece is added.
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            // First/last filed date per dossier in one grouped query. Joined on
            // the pivot (content_item_topic) rather than on the topics table,
            // since content_items is the base of the query.
            $spans = ContentItem::published()
                ->join('content_item_topic', 'content_item_topic.content_item_id', '=', 'content_items.id')
                ->whereNotNull('content_items.published_at')
                ->selectRaw('content_item_topic.topic_id as topic_id, MIN(content_items.published_at) as first_at, MAX(content_items.published_at) as last_at')
                ->groupBy('content_item_topic.topic_id')
                ->get()
                ->keyBy('topic_id');

            $topics = $topics->map(function (Topic $topic) use ($spans) {
                $span = $spans->get($topic->id);

                $topic->setAttribute('first_at', $span?->first_at ? Carbon::parse($span->first_at) : null);
                $topic->setAttribute('last_at', $span?->last_at ? Carbon::parse($span->last_at) : null);

                return $topic;
            });

            return [
                'topics' => $topics,
                'total' => $topics->sum('content_items_count'),
            ];
        });

        return view('topics.index', $data);
    }

    /**
     * /topics/{slug} — editorial dossier mixing content types
     * and sources (FR-110 / CONTENT_MODEL.md §7).
     * Cached (ROADMAP Phase 8); invalidated on any publish/edit/delete.
     */
    public function show(Request $request, string $slug)
    {
        $page = (int) $request->query('page', 1);

        $data = PageCache::remember('topics:show', ['slug' => $slug, 'page' => $page], function () use ($slug) {
            $topic = Topic::with('featuredImage')
                ->where('slug', $slug)
                ->firstOrFail();

            $items = $this->abortIfPastLastPage(
                $topic->contentItems()
                    ->published()
                    ->with(['category', 'publication', 'featuredImage'])
                    ->orderByDesc('published_at')
                    ->paginate(12)
                    ->withQueryString()
            );

            return compact('topic', 'items');
        });

        return view('topics.show', $data);
    }
}
