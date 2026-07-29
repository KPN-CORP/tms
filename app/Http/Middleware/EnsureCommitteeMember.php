<?php

namespace App\Http\Middleware;

use App\Models\CommitteeAssignment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Izinkan akses bila user adalah anggota committee (terdaftar di
 * committee_assignments). Opsional dibatasi per jenis: committee.member:idea.
 *
 * Model "assignment = kapabilitas": ditetapkan sebagai committee sudah cukup
 * untuk membuka halaman review — tanpa perlu permission terpisah. Otorisasi
 * per-item (layer/ide mana) tetap dicek di controller (isCurrentReviewer, dll).
 */
class EnsureCommitteeMember
{
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        $isMember = CommitteeAssignment::query()
            ->where('user_id', $user->id)
            ->when($type, fn ($q) => $q->where('approval_type', $type))
            ->exists();

        abort_unless($isMember, 403, 'Halaman ini hanya untuk anggota committee.');

        return $next($request);
    }
}