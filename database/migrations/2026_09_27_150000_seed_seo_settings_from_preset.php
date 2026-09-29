<?php

use App\Models\Setting;
use App\Support\SeoSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Create any `settings` row the SEO preset declares but the database lacks.
 *
 * The preset is the definition of what settings exist; the table holds their
 * values. A new entry in the preset therefore needs a row before the admin can
 * edit it, and forgetting that would mean a setting that is fully wired into
 * the markup but impossible to set.
 *
 * Idempotent and additive only: it inserts what is missing and never touches an
 * existing row, so re-running it cannot overwrite a value the journalist has
 * since edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $existing = Setting::query()->pluck('key')->flip();

        $missing = [];

        foreach (SeoSettings::preset() as $setting) {
            if (isset($existing[$setting['key']])) {
                continue;
            }

            // Only a toggle has a meaningful default to seed. Seeding null for
            // a text setting is the same as leaving the row absent, so a row is
            // only created where it would actually change behaviour.
            $missing[] = [
                'key' => $setting['key'],
                'value' => $setting['default'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($missing !== []) {
            Setting::query()->insert($missing);
        }
    }

    public function down(): void
    {
        // Intentionally empty. Deleting rows on rollback would throw away
        // values the journalist had edited, and a migration that loses data on
        // the way down is worse than one that leaves a spare row behind.
    }
};
