<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'name',
        'slug',
        'logo_media_id',
        'website_url',
        'description',
    ];

    protected $casts = [
        'logo_media_id' => 'integer',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    public function journalistProfiles(): BelongsToMany
    {
        return $this->belongsToMany(JournalistProfile::class, 'journalist_profile_publication');
    }
}
