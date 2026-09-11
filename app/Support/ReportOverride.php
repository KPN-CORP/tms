<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Kewenangan lintas organisasi (Super Admin / izin 'override.role') HANYA hidup
 * bila halaman dibuka lewat menu Report.
 *
 * Di luar Report — My Ideas, My Project, Task Box, Project Shell — Super Admin
 * diperlakukan seperti employee biasa: ia hanya melihat dan menindak apa yang
 * memang melibatkan dirinya (committee layer, Leader, Sponsor, atau member).
 *
 * Penanda 'from=report' dibawa oleh tautan dari Report dan oleh form-form di
 * halaman yang dibuka dari sana. Penanda itu menyatakan NIAT, bukan izin: tanpa
 * 'override.role' ia tidak memberi kewenangan apa pun, jadi menambahkannya
 * sendiri di URL tidak menaikkan hak siapa pun.
 */
class ReportOverride
{
    /** Apakah permintaan saat ini berjalan dalam konteks Report? */
    public static function aktif(?Request $request = null): bool
    {
        $request ??= request();

        if (! $request) {
            return false;
        }

        $user = $request->user();

        return $user !== null
            && $request->input('from') === 'report'
            && $user->can('override.role');
    }

    /**
     * Parameter yang harus dibawa tautan/form agar konteks Report tidak hilang
     * saat berpindah halaman atau mengirim form.
     */
    public static function params(array $extra = []): array
    {
        return self::aktif() ? $extra + ['from' => 'report'] : $extra;
    }
}
