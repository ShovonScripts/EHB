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
        'width',
        'height',
        'uploaded_by',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'uploaded_by' => 'integer',
    ];

    /**
     * Capture the stored image's pixel dimensions for share-card tags.
     *
     * Measured from the bytes on disk — after SecureUpload's re-encode and
     * downscale — so the recorded size is the size a social crawler actually
     * fetches, not the size that was uploaded. Runs on `saving` rather than
     * `created` so it costs no extra query and also heals rows whose file
     * was replaced.
     *
     * Deliberately total: a missing file, an unreadable disk, or bytes that
     * are not an image all resolve to \"unknown\" (null) rather than throwing,
     * because this hook runs on every Media save — including seeders and
     * tests that create rows for files which do not exist.
     */
    protected static function booted(): void
    {
        static::saving(function (Media $media): void {
            if ($media->type !== 'image' || ($media->width && $media->height)) {
                return;
            }

            $dimensions = self::storedDimensions($media->disk, $media->file_path);

            if ($dimensions !== null) {
                [$media->width, $media->height] = $dimensions;
            }
        });
    }

    /**
     * Pixel dimensions of a stored file, or null when they cannot be known.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function storedDimensions(?string $disk, ?string $path): ?array
    {
        if (! $disk || ! $path) {
            return null;
        }

        try {
            $bytes = Storage::disk($disk)->get($path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $size = @getimagesizefromstring($bytes);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return [(int) $size[0], (int) $size[1]];
    }

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
