<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'journalist_profile_id',
        'title',
        'awarding_body',
        'year',
        'description',
        'url',
        'media_id',
        'sort_order',
    ];

    protected $casts = [
        'year' => 'integer',
        'sort_order' => 'integer',
        'media_id' => 'integer',
    ];

    public function journalistProfile(): BelongsTo
    {
        return $this->belongsTo(JournalistProfile::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
