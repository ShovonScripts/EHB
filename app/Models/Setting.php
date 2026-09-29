<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\SeoSettings;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    // Settings changes are audited (SECURITY.md §15) and — via
    // PageCache::shouldInvalidate() — bust the public response cache, so
    // editing the site name in the admin is live on the next request.
    use RecordsActivity;

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Drop the memoized/cached settings so the next read reflects the write.
     *
     * Note: this is a model event, so it fires for `$setting->update(...)` and
     * for any Filament save — but NOT for a mass update such as
     * `Setting::where('key', ...)->update(...)`, which bypasses Eloquent
     * events. Prefer saving the model (or calling SiteSettings::flush() after
     * a raw query) so a change is never served stale.
     */
    protected static function booted(): void
    {
        $invalidate = fn () => SiteSettings::flush();

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    // ── Presentational accessors ───────────────────────────────────
    //
    // The preset's metadata, exposed as real attributes so the admin table can
    // render it with plain columns.
    //
    // These exist as accessors rather than as `TextColumn::state()` closures on
    // columns named after things the table does not have. A column whose name
    // resolves to nothing *and* carries a `state()` closure sends Filament
    // down a resolution path that never terminates: the page renders until the
    // PHP process dies with a stack overflow. A real attribute is both the
    // fix and the better shape — the metadata is then available anywhere the
    // model is, not only in this one table.

    /** The human label from the preset, or the raw key if it has none. */
    public function getDisplayLabelAttribute(): string
    {
        return SeoSettings::label($this->key);
    }

    /** The preset group, or the "Other" bucket for a row the preset does not know. */
    public function getSettingGroupAttribute(): string
    {
        return SeoSettings::groupOf($this->key) ?? SeoSettings::GROUP_UNKNOWN;
    }

    public function getSettingTypeAttribute(): ?string
    {
        return SeoSettings::typeOf($this->key);
    }

    /**
     * Whether this setting does anything.
     *
     * A retired row is inert by design — see the preset — so it reports false
     * rather than being quietly presented as working.
     */
    public function getIsSupportedAttribute(): bool
    {
        return $this->setting_type !== 'retired';
    }

    /**
     * The value, prepared for display.
     *
     * A media id means nothing to a human, so an image setting shows the file
     * it points at; a toggle shows On/Off. The stored value is untouched.
     */
    public function getDisplayValueAttribute(): string
    {
        $value = $this->value;

        if (is_bool($value)) {
            return $value ? 'On' : 'Off';
        }

        $value = (string) $value;

        if ($this->setting_type === 'image' && $value !== '') {
            return Media::find($value)?->file_path ?? "missing image #{$value}";
        }

        return $value;
    }
}
