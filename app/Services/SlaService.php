<?php

namespace App\Services;

use App\Models\SlaSetting;
use Illuminate\Support\Carbon;

/**
 * Menghitung status SLA sebuah item yang sedang di-review (info-only).
 * Tidak mengirim notifikasi/reminder — hanya menentukan On Time / Due Soon / Overdue.
 */
class SlaService
{
    /** Ambang "due soon": sisa hari <= nilai ini (tapi belum lewat). */
    private const DUE_SOON_THRESHOLD = 1;

    /**
     * @return array{state:string, days:?int, since:?Carbon, due:?Carbon, left:?int}
     *   state: none | on_time | due_soon | overdue
     *   left : sisa hari (negatif = terlambat)
     */
    public function evaluate(?string $approvalType, ?Carbon $since): array
    {
        $none = ['state' => 'none', 'days' => null, 'since' => null, 'due' => null, 'left' => null];

        if ($approvalType === null || $since === null) {
            return $none;
        }

        $days = SlaSetting::daysFor($approvalType);
        if ($days === null) {
            return $none;
        }

        $due  = $since->copy()->addDays($days);
        $left = Carbon::now()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);

        $state = $left < 0
            ? 'overdue'
            : ($left <= self::DUE_SOON_THRESHOLD ? 'due_soon' : 'on_time');

        return ['state' => $state, 'days' => $days, 'since' => $since, 'due' => $due, 'left' => $left];
    }
}
