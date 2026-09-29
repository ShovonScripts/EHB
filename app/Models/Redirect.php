<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
    ];

    protected $casts = [
        'status_code' => 'integer',
    ];

    /**
     * Keep the ApplyRedirects middleware's cached map in sync when
     * redirects are created/edited/deleted from the admin.
     */
    protected static function booted(): void
    {
        $invalidate = fn () => Cache::forget('redirects.map');

        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
