<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'slug',
        'title',
        'body',
        'seo_title',
        'seo_description',
        'seo_og_image_media_id',
        'canonical_url_override',
    ];

    protected $casts = [
        'seo_og_image_media_id' => 'integer',
    ];

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'seo_og_image_media_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
