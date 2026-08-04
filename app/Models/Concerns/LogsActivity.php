<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/**
 * Audit trail generik (§9.2). Tempelkan ke model mana pun:
 *
 *     use App\Models\Concerns\LogsActivity;
 *     class Idea extends Model { use LogsActivity; ... }
 *
 * Otomatis mencatat create/update/delete beserta diff field (old → new).
 * Field sensitif/noisy (password, timestamp) dikecualikan. Model boleh:
 *  - override `activityLabel(): string` untuk ringkasan human-readable,
 *  - definisikan `protected array $activityExclude = [...]` untuk field tambahan.
 */
trait LogsActivity
{
    /** Field yang tidak pernah dicatat (semua model). */
    protected static array $activityGlobalExclude = [
        'password', 'remember_token', 'created_at', 'updated_at', 'modified_at',
    ];

    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->writeActivityLog('created', $model->activityLoggableAttributes());
        });

        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $field => $new) {
                if (in_array($field, $model->activityExcludedFields(), true)) {
                    continue;
                }
                $changes[$field] = ['old' => $model->getOriginal($field), 'new' => $new];
            }

            // Jangan tulis log bila hanya timestamp/field terkecuali yang berubah.
            if (! empty($changes)) {
                $model->writeActivityLog('updated', $changes);
            }
        });

        static::deleted(function ($model) {
            $model->writeActivityLog('deleted', $model->activityLoggableAttributes());
        });
    }

    /** Gabungan pengecualian global + per-model. */
    protected function activityExcludedFields(): array
    {
        return array_merge(
            static::$activityGlobalExclude,
            property_exists($this, 'activityExclude') ? $this->activityExclude : []
        );
    }

    /** Snapshot atribut yang layak dicatat (untuk create/delete). */
    protected function activityLoggableAttributes(): array
    {
        $excluded = $this->activityExcludedFields();

        return collect($this->getAttributes())
            ->except($excluded)
            ->map(fn ($value) => ['old' => null, 'new' => $value])
            ->all();
    }

    /** Ringkasan human-readable objek; boleh di-override model. */
    public function activityLabel(): string
    {
        return class_basename($this) . ' #' . $this->getKey();
    }

    protected function writeActivityLog(string $event, array $changes): void
    {
        $user = auth()->user();

        ActivityLog::create([
            'event'         => $event,
            'subject_type'  => static::class,
            'subject_id'    => $this->getKey(),
            'subject_label' => $this->activityLabel(),
            'causer_id'     => $user?->id,
            'causer_name'   => $user?->name,
            'changes'       => $changes,
            'created_at'    => now(),
        ]);
    }
}
