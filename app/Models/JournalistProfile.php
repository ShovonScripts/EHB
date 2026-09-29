<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalistProfile extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'user_id',
        'name',
        'title',
        'photo_media_id',
        'short_bio',
        'long_bio',
        'skills',
        'social_links',
    ];

    protected $casts = [
        'photo_media_id' => 'integer',
        'skills' => 'array',
        'social_links' => 'array',
    ];

    /**
     * The one and only profile.
     *
     * This site has a single author, so the profile is a singleton and every
     * read of it should go through here rather than a bare `first()`. A bare
     * `first()` has no `ORDER BY`, so the row it returns is whatever the
     * storage engine hands back — deterministic in practice only while there
     * happens to be one row. The public site reads the profile in seven
     * separate places (home, about, contact, feed, header, footer, 404), so a
     * second row would give a site showing one biography on the homepage and a
     * different name in the footer, with nothing throwing.
     *
     * `id` is ordered explicitly so the result is at least *stable* if a second
     * row is ever created by hand in the database — it will be the original
     * profile on every page, consistently, rather than a different one per
     * query. Creating a second row is not supported; the admin resource is
     * edit-only for exactly this reason.
     */
    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    public function careerHistory(): HasMany
    {
        return $this->hasMany(CareerHistory::class)->orderBy('sort_order');
    }

    public function education(): HasMany
    {
        return $this->hasMany(Education::class)->orderBy('sort_order');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class)->orderBy('sort_order');
    }

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'journalist_profile_publication');
    }

    public function contentItems(): HasManyThrough
    {
        return $this->hasManyThrough(ContentItem::class, User::class, 'id', 'author_id', 'user_id', 'id');
    }
}
