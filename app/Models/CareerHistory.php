<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerHistory extends Model
{
    use RecordsActivity;

    protected $table = 'career_history';

    protected $fillable = [
        'journalist_profile_id',
        'role',
        'organization',
        'start_date',
        'end_date',
        'description',
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
