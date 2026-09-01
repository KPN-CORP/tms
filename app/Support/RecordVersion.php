<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Token versi record untuk optimistic locking.
 *
 * Berupa sidik jari ISI record, bukan updated_at — sehingga tetap akurat walau
 * dua perubahan terjadi pada detik yang sama (kolom timestamp MySQL hanya
 * berpresisi detik), dan tidak memerlukan kolom tambahan di tabel mana pun.
 */
final class RecordVersion
{
    /** Nama field tersembunyi pembawa versi pada form edit. */
    public const FIELD = '_version';

    public static function of(?Model $model): ?string
    {
        if (! $model || ! $model->exists) {
            return null;
        }

        $attrs = $model->getAttributes();
        ksort($attrs);

        return sha1(json_encode($attrs));
    }
}
