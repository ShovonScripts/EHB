<?php

namespace App\Models\Concerns;

use App\Support\ActivityLogger;
use App\Support\PageCache;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * SECURITY.md §15 — audit create / update / publish / delete on a model.
 *
 * Kept as a concern (rather than a model event registered in a provider) so
 * each model states for itself that it is audited, and so the events are
 * registered exactly once per model.
 *
 * The same events also bump the public page-cache version (ROADMAP Phase 8),
 * which is what makes a publish show up on the front end immediately even
 * though the listing pages themselves are cached.
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(function (Model $model): void {
            ActivityLogger::record($model, ActivityLogger::CREATED, ActivityLogger::changesFor($model, ActivityLogger::CREATED));

            static::flushPageCacheWhenRelevant($model);
        });

        static::updated(function (Model $model): void {
            if (ActivityLogger::updateIsEmpty($model)) {
                return;
            }

            $action = ActivityLogger::actionForUpdate($model);

            ActivityLogger::record($model, $action, ActivityLogger::changesFor($model, $action));

            static::flushPageCacheWhenRelevant($model);
        });

        static::deleted(function (Model $model): void {
            ActivityLogger::record($model, ActivityLogger::DELETED, ActivityLogger::changesFor($model, ActivityLogger::DELETED));

            static::flushPageCacheWhenRelevant($model);
        });
    }

    /** Bump the cached-content version so the change is live on the next request. */
    protected static function flushPageCacheWhenRelevant(Model $model): void
    {
        if (PageCache::shouldInvalidate($model)) {
            PageCache::flush();

            // SiteSettings derives the default OG image from the journalist's
            // portrait, so uploading or replacing a photo has to drop that
            // cache too — otherwise the new portrait would take up to the
            // settings TTL to appear in social previews.
            SiteSettings::flush();
        }
    }
}
