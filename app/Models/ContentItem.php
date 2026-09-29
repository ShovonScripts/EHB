<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\ContentTypes;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ContentItem extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'author_id',
        'content_type',
        'source_type',
        'title',
        'slug',
        'summary',
        'body',
        'news_body',
        'featured_image_media_id',
        'category_id',
        'publication_id',
        'external_url',
        'interviewee_name',
        'interviewee_title',
        'video_url',
        'meta',
        'is_featured',
        'status',
        'published_at',
        'reading_time_minutes',
        'seo_title',
        'seo_description',
        'seo_og_image_media_id',
        'canonical_url_override',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'reading_time_minutes' => 'integer',
        'featured_image_media_id' => 'integer',
        'category_id' => 'integer',
        'publication_id' => 'integer',
        'seo_og_image_media_id' => 'integer',
        'meta' => 'array',
    ];

    /**
     * Auto-calculate reading time for internal bodies (UTF-8/Bangla safe),
     * default published_at, and sanitize rich text server-side
     * (SECURITY.md §6).
     */
    protected static function booted(): void
    {
        static::saving(function (ContentItem $item) {
            if ($item->body) {
                $item->body = HtmlSanitizer::sanitize($item->body);
            }

            // The same guarantee for the second rich-text field, and for the
            // same reason: the editor that produced this markup runs in the
            // browser, so nothing about it can be trusted. A `<script>` or an
            // `onerror` pasted into the custom editor does not survive here.
            if ($item->news_body) {
                $item->news_body = HtmlSanitizer::sanitize($item->news_body);
            }

            if ($item->source_type === 'internal' && $item->body && (! $item->reading_time_minutes || $item->isDirty('body'))) {
                preg_match_all('/\p{L}+/u', strip_tags($item->body), $matches);
                $item->reading_time_minutes = max(1, (int) ceil(count($matches[0]) / 200));
            }

            if ($item->status === 'published' && ! $item->published_at) {
                $item->published_at = now();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_media_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'seo_og_image_media_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'content_item_tag');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'content_item_topic');
    }

    public function relatedContent(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class, 'related_content', 'content_item_id', 'related_content_item_id');
    }

    public function relatedContentWithFallback(string $fallbackColumn, ?callable $queryCustomizer = null, array $with = ['category', 'publication', 'featuredImage']): Collection
    {
        $related = $this->relatedContent;

        if ($related->isEmpty()) {
            $query = ContentItem::published()
                ->with($with)
                ->where('id', '!=', $this->id)
                ->orderByDesc('published_at')
                ->take(3);

            if ($queryCustomizer) {
                $queryCustomizer($query);
            }

            $related = $query
                ->when($this->{$fallbackColumn}, fn ($q) => $q->where($fallbackColumn, $this->{$fallbackColumn}))
                ->get();
        }

        return $related;
    }

    public function gallery(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'content_item_media')
            ->withPivot('sort_order', 'caption')
            ->orderBy('content_item_media.sort_order')
            ->withTimestamps();
    }

    /**
     * The same pivot rows as `gallery()`, but as their own models so the admin
     * can edit the per-item order and caption (a BelongsToMany repeater cannot
     * write pivot columns — see ContentItemMedia).
     */
    public function galleryItems(): HasMany
    {
        return $this->hasMany(ContentItemMedia::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query
            // Draft never shows; scheduled shows once its time has passed
            // (ADMIN_PANEL.md §4 — query-layer visibility, no cron needed);
            // published shows only once its (optional) future date arrives.
            ->where(function ($q) {
                $q->where('status', 'published')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'scheduled')
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
            })
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            // External items must actually link out somewhere (FR-115).
            ->where(function ($q) {
                $q->where('source_type', 'internal')
                    ->orWhereNotNull('external_url');
            });
    }

    public function scopeInternal($query)
    {
        return $query->where('source_type', 'internal');
    }

    public function scopeExternal($query)
    {
        return $query->where('source_type', 'external');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeOfTypes($query, array $types)
    {
        return $query->whereIn('content_type', $types);
    }

    /**
     * The public URL path for this item (single canonical location):
     * internal -> /{section}/{slug}, external -> /work/{slug}.
     */
    public function publicPath(): string
    {
        if ($this->isExternal()) {
            return '/work/'.$this->slug;
        }

        $section = ContentTypes::sectionForType($this->content_type) ?? 'articles';

        return '/'.$section.'/'.$this->slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isInternal(): bool
    {
        return $this->source_type === 'internal';
    }

    public function isExternal(): bool
    {
        return $this->source_type === 'external';
    }

    /**
     * The video URL, only when it is safe to render inside a frame.
     *
     * Filament's form field accepts any string, so this is the last line of
     * defence against `javascript:`-style values reaching the iframe src
     * (SECURITY.md §6 — a sandboxed <iframe> cannot escape, but a
     * javascript:/data: src is still treated as same-origin content).
     */
    public function getSafeVideoUrlAttribute(): ?string
    {
        if (! is_string($this->video_url) || $this->video_url === '') {
            return null;
        }

        if (! str_starts_with(strtolower($this->video_url), 'https://')
            && ! str_starts_with(strtolower($this->video_url), 'http://')) {
            return null;
        }

        return $this->video_url;
    }
}
