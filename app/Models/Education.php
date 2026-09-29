<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Education extends Model
{
    use RecordsActivity;

    protected $table = 'education';

    protected $fillable = [
        'journalist_profile_id',
        'institution',
        'program',
        'start_date',
        'end_date',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function journalistProfile(): BelongsTo
    {
        return $this->belongsTo(JournalistProfile::class);
    }
}
