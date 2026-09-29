<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `content_item_media` pivot as a first-class model.
 *
 * The public photo-story gallery renders `$media->pivot->caption` and orders
 * rows by `content_item_media.sort_order`
 * (see resources/views/sections/show.blade.php), but a `BelongsToMany`
 * relationship repeater in Filament saves to the *related* model — so those
 * per-item values have to be edited through their own model, as a `HasMany`
 * (Filament's documented pattern for BelongsToMany + repeater).
 *
 * Only the gallery-specific data lives here: `sort_order` and `caption`.
 * Image alt text stays on the shared Media row, where the library owns it.
 */
class ContentItemMedia extends Model
{
    protected $table = 'content_item_media';

    protected $fillable = [
        'content_item_id',
        'media_id',
        'sort_order',
        'caption',
    ];

    protected $casts = [
        'content_item_id' => 'integer',
        'media_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
