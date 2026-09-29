<?php

use App\Models\Setting;
use App\Support\SeoSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Give `default_card_image_media_id` a row of its own.
 *
 * `2026_09_27_150000_seed_seo_settings_from_preset.php` loops the whole preset,
 * but a migration runs once: it inserted the keys the preset declared when it
 * ran, and the thumbnail setting was declared afterwards. A row is what makes a
 * setting editable — without one, the picker exists in the panel's code and
 * cannot be reached from the panel, which is precisely what
 * `SeoSettingsPresetTest::test_every_preset_setting_has_a_database_row` checks
 * for.
 *
 * Idempotent and additive only, like the migration it follows: it inserts the
 * row when the key has none and never touches a row that exists, so it cannot
 * overwrite a value the journalist has since set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (Setting::query()->where('key', 'default_card_image_media_id')->exists()) {
            return;
        }

        $definition = SeoSettings::definition('default_card_image_media_id');

        // Query builder, not `Setting::create()`, exactly like the migration
        // before this one: migrations run outside a request, and going through
        // the model would fire the events that flush the caches this deployment
        // does not have yet. The value is null — "no default thumbnail" is the
        // state this setting starts in, not a picture of the journalist.
        Setting::query()->insert([
            'key' => 'default_card_image_media_id',
            'value' => $definition['default'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally empty. Removing the row would discard the value the
        // journalist set, and a rollback that loses data is worse than one that
        // leaves a spare row behind.
    }
};
