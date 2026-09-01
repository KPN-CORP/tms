<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityLog;
use App\Support\RecordVersion;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencegah bentrokan saat beberapa pengguna mengubah objek yang sama.
 *
 * Dua lapis, dipakai bersama lewat withRecordLock():
 *
 *  1. SERIALISASI (FIFO di level record) — baris dikunci dengan lockForUpdate()
 *     di dalam transaksi, sehingga submit kedua atas record yang sama MENUNGGU
 *     submit pertama selesai, bukan berjalan bersamaan. Urutan pemrosesan =
 *     urutan kedatangan di database.
 *
 *  2. OPTIMISTIC LOCK — form membawa sidik jari versi yang dibaca pengguna.
 *     Bila isi record sudah berubah sejak itu, submit DITOLAK dengan pesan yang
 *     menyebut siapa yang mengubah dan kapan — bukan menimpanya diam-diam.
 *
 * Dipilih optimistic (bukan mengunci record saat dialog dibuka) karena pengguna
 * kerap menutup tab tanpa menyimpan; lock semacam itu akan menggantung dan
 * butuh mekanisme kedaluwarsa tersendiri.
 */
trait PreventsConcurrentEdits
{
    /**
     * Jalankan $callback atas $model dengan baris terkunci + pemeriksaan versi.
     * $callback menerima instance model yang SUDAH di-refresh di dalam kunci.
     */
    protected function withRecordLock(Request $request, Model $model, Closure $callback)
    {
        $expected = $request->input(RecordVersion::FIELD);

        return DB::transaction(function () use ($request, $model, $callback, $expected) {
            // Kunci baris: submit lain atas record yang sama antre di sini.
            $fresh = $model->newQuery()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                abort(404);
            }

            // Form lama tanpa token versi tetap dilayani (kompatibilitas), tapi
            // begitu token dikirim ia wajib cocok dengan isi terkini.
            if ($expected !== null && $expected !== '' && $expected !== RecordVersion::of($fresh)) {
                throw ValidationException::withMessages([
                    RecordVersion::FIELD => $this->concurrentEditMessage($fresh),
                ]);
            }

            return $callback($fresh);
        });
    }

    /** Pesan penolakan yang menyebut pengubah terakhir bila jejaknya ada. */
    private function concurrentEditMessage(Model $model): string
    {
        $last = ActivityLog::where('subject_type', $model::class)
            ->where('subject_id', $model->getKey())
            ->latest('created_at')
            ->first();

        $who  = $last?->causer_name;
        $when = $last?->created_at?->utc()->format('d M Y H:i') . ' UTC';

        return $who
            ? "This record was changed by {$who} at {$when}. Reload the page and reapply your change so their update is not overwritten."
            : 'This record was changed by someone else while you were editing. Reload the page and reapply your change.';
    }
}
