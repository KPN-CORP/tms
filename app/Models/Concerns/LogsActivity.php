<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Models\User;

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

    /**
     * Field yang berisi ID user (tunggal maupun array). Nama pemiliknya di-SNAPSHOT
     * ke dalam log saat kejadian, supaya audit trail tetap menyebut nama walau
     * orangnya kemudian dikeluarkan dari tim atau hilang dari direktori hcis.
     * Model boleh override sesuai kolomnya.
     */
    protected function activityUserFields(): array
    {
        return ['user_id', 'pic_user_ids'];
    }

    /**
     * Sisipkan old_name/new_name pada field ber-ID user. Resolusi nama dilakukan
     * SEKARANG (saat menulis log), bukan saat log dibaca — itulah yang membuat
     * nama tetap ada meski user-nya nanti tidak bisa dilacak lagi.
     */
    protected function withUserNames(array $changes): array
    {
        $fields = array_intersect($this->activityUserFields(), array_keys($changes));
        if (! $fields) {
            return $changes;
        }

        // Nilai kolom JSON (mis. pic_user_ids) bisa datang sebagai STRING JSON dari
        // getChanges(); harus di-decode dulu agar tiap id terbaca satu per satu.
        $ids = collect($fields)
            ->flatMap(fn ($f) => array_merge(
                $this->activityIdList($changes[$f]['old'] ?? null),
                $this->activityIdList($changes[$f]['new'] ?? null)
            ))
            ->map(fn ($v) => is_numeric($v) ? (int) $v : null)
            ->filter()->unique()->values();

        $names = $ids->isEmpty()
            ? collect()
            : User::whereIn('id', $ids->all())->pluck('name', 'id');

        $label = function ($value) use ($names) {
            $list = $this->activityIdList($value);
            if (! $list) {
                return null;
            }

            return collect($list)->map(fn ($v) => $names[(int) $v] ?? ('#' . $v))->implode(', ');
        };

        foreach ($fields as $f) {
            $changes[$f]['old_name'] = $label($changes[$f]['old'] ?? null);
            $changes[$f]['new_name'] = $label($changes[$f]['new'] ?? null);
        }

        return $changes;
    }

    /** Normalisasi nilai field user menjadi daftar ID (menangani JSON string & array). */
    private function activityIdList($value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $value = json_decode($value, true) ?: [];
        }

        return collect((array) $value)
            ->map(fn ($v) => is_numeric($v) ? (int) $v : null)
            ->filter()->values()->all();
    }

    protected function writeActivityLog(string $event, array $changes): void
    {
        $user    = auth()->user();
        $changes = $this->withUserNames($changes);

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
