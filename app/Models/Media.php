<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'type',
        'file_path',
        'disk',
        'original_filename',
        'alt_text',
        'caption',
        'uploaded_by',
    ];

    protected $casts = [
        'uploaded_by' => 'integer',
    ];

    /**
     * Public URL for this file (used across the Blade frontend).
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function contentItemsAsFeatured(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'featured_image_media_id');
    }

    public function contentItemsAsOgImage(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'seo_og_image_media_id');
    }

    public function journalistProfilesAsPhoto(): HasMany
    {
        return $this->hasMany(JournalistProfile::class, 'photo_media_id');
    }

    public function publicationsAsLogo(): HasMany
    {
        return $this->hasMany(Publication::class, 'logo_media_id');
    }

    public function topicsAsFeatured(): HasMany
    {
        return $this->hasMany(Topic::class, 'featured_image_media_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class, 'media_id');
    }
}
