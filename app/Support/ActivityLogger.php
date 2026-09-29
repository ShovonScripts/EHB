<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * SECURITY.md §15 — lightweight audit trail.
 *
 * Records create / update / publish / schedule / delete actions with the
 * acting user, the subject, and a readable diff of what actually changed, so
 * there is always an answer to "who changed what, when" — and so the
 * accountability story is already in place when a second (editor) role is
 * introduced (DATABASE.md §4).
 */
class ActivityLogger
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const PUBLISHED = 'published';

    public const SCHEDULED = 'scheduled';

    public const DELETED = 'deleted';

    /** Attribute values longer than this are truncated in the audit payload. */
    private const MAX_LENGTH = 500;

    /** Never written to the audit trail. */
    private const EXCLUDED = ['password', 'remember_token', 'created_at', 'updated_at'];

    /**
     * Persist one audit row. Never throws: auditing must not be able to break
     * publishing (e.g. on a checkout where the migration has not run yet).
     */
    public static function record(Model $subject, string $action, ?array $changes = null): ?ActivityLog
    {
        try {
            return ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'changes' => $changes,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Action name for an update — escalates to the lifecycle action
     * (`published` / `scheduled`) when the status field itself moved.
     */
    public static function actionForUpdate(Model $model): string
    {
        if (! $model->wasChanged('status')) {
            return self::UPDATED;
        }

        return match ($model->getAttribute('status')) {
            'published' => self::PUBLISHED,
            'scheduled' => self::SCHEDULED,
            default => self::UPDATED,
        };
    }

    /**
     * Payload stored in `activity_logs.changes` (JSON column).
     *
     * Shape: `['before' => [...], 'after' => [...]]` for updates (only the
     * changed attributes) and a single side for creates/deletes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function changesFor(Model $model, string $action): array
    {
        return match ($action) {
            self::CREATED => ['after' => self::summarize($model->getAttributes())],
            self::DELETED => ['before' => self::summarize($model->getOriginal())],
            default => self::updatedChanges($model),
        };
    }

    /** True when the update touched nothing worth logging. */
    public static function updateIsEmpty(Model $model): bool
    {
        return Arr::except($model->getChanges(), self::EXCLUDED) === [];
    }

    /** @return array{before: array<string, mixed>, after: array<string, mixed>} */
    private static function updatedChanges(Model $model): array
    {
        $original = $model->getOriginal();
        $before = [];
        $after = [];

        foreach (array_keys(Arr::except($model->getChanges(), self::EXCLUDED)) as $attribute) {
            $before[$attribute] = self::summarizeValue(Arr::get($original, $attribute));
            $after[$attribute] = self::summarizeValue($model->getAttribute($attribute));
        }

        return ['before' => $before, 'after' => $after];
    }

    /** @return array<string, mixed> */
    private static function summarize(array $attributes): array
    {
        $summary = [];

        foreach (Arr::except($attributes, self::EXCLUDED) as $attribute => $value) {
            $summary[$attribute] = self::summarizeValue($value);
        }

        return $summary;
    }

    private static function summarizeValue(mixed $value): mixed
    {
        if (is_string($value) && mb_strlen($value) > self::MAX_LENGTH) {
            return mb_substr($value, 0, self::MAX_LENGTH).'…';
        }

        return $value;
    }
}
