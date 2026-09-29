<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Topic extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'description',
        'featured_image_media_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'featured_image_media_id' => 'integer',
    ];

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_media_id');
    }

    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class, 'content_item_topic');
    }
}
