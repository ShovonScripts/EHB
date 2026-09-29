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
     *
     * Deliberately root-relative — see config/filesystems.php and
     * tests/Feature/MediaUrlTest.php: an absolute URL built from APP_URL points
     * every image at a different origin as soon as the site is browsed anywhere
     * else (127.0.0.1:8000, a preview domain, a staging vhost), and the whole
     * page 404s its images while looking otherwise fine.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * The same URL, made absolute.
     *
     * For the consumers that cannot resolve a relative one: `<image:loc>` in
     * the XML sitemap (a crawler has no page to resolve against, so
     * `/storage/…` there is an invalid image entry Google discards) and
     * JSON-LD `image`, where structured data wants a crawler-followable URL.
     *
     * Everything that renders a picture on a page keeps using `url`, because a
     * relative src is what makes the site host-independent.
     */
    public function getAbsoluteUrlAttribute(): string
    {
        return preg_match('#^https?://#i', $this->url) === 1
            ? $this->url
            : url($this->url);
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
