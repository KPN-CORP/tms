<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\ImplementationIndicator;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Dashboard sesuai "Dashboard Matrix":
 *  Filter : Business Unit & Unit (sumber Darwinbox/hcis), Period From & Period To (TMS).
 *  Data   : 5 metrik Idea + 6 metrik Project.
 *
 * Cakupan data tambahan lewat dropdown "Act as": Admin = seluruh organisasi,
 * Myself = milik sendiri. Dropdown-nya dikendalikan permission 'dashboard.act-as'
 * (bisa dicentang per role lewat Role Management), bukan nama role di dalam kode —
 * sehingga role baru pun bisa diberi akses ini tanpa mengubah kode. Tanpa permission
 * tersebut, cakupan selalu dikunci ke "Myself".
 */
class DashboardController extends Controller
{
    /** Pilihan "Act as" pada dropdown dashboard. */
    private const VIEWS = ['myself' => 'Myself', 'admin' => 'Admin'];

    /** Permission yang memunculkan dropdown "Act as" (diatur di Role Management). */
    private const PERMISSION_ACT_AS = 'dashboard.act-as';

    public function index(Request $request)
    {
        $user = $request->user();

        $canActAs = $user->can(self::PERMISSION_ACT_AS);
        $as       = $request->query('as');
        $as       = array_key_exists($as, self::VIEWS) ? $as : ($canActAs ? 'admin' : 'myself');
        if (! $canActAs) {
            $as = 'myself'; // tanpa izin, hanya boleh melihat data sendiri
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        // Business Unit & Unit disaring memakai NAMA (snapshot dari Darwinbox yang
        // ikut tersimpan di ide), sama seperti filter di halaman My Ideas / My Project.
        $filters = [
            'bu'   => $request->filled('bu') ? $request->get('bu') : null,
            'unit' => $request->filled('unit') ? $request->get('unit') : null,
            'from' => $request->filled('from') ? $request->date('from')->startOfDay() : null,
            'to'   => $request->filled('to') ? $request->date('to')->endOfDay() : null,
        ];

        $mine = $as === 'myself';

        return view('dashboard', [
            'idea'      => $this->ideaMetrics($user, $mine, $filters),
            'project'   => $this->projectMetrics($user, $mine, $filters),
            'canActAs'  => $canActAs,
            'as'        => $as,
            'views'     => self::VIEWS,
            'filters'   => $filters,
            'hasFilter' => (bool) array_filter($filters),
        ]);
    }

    /* ---- Scope ------------------------------------------------------- */

    /** Ide: filter periode memakai tanggal ide dibuat. */
    private function ideaScope(User $user, bool $mine, array $f): Builder
    {
        return Idea::query()
            ->when($mine, fn ($q) => $q->where('user_id', $user->id))
            ->when($f['bu'], fn ($q, $v) => $q->where('business_unit_name', $v))
            ->when($f['unit'], fn ($q, $v) => $q->where('department_name', $v))
            ->when($f['from'], fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->where('created_at', '<=', $v));
    }

    /** BU/Unit project mengikuti ide asalnya; periode memakai tanggal project dibuat. */
    private function applyProjectFilters(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['bu'], fn ($qq, $v) => $qq->whereHas('idea', fn ($i) => $i->where('business_unit_name', $v)))
            ->when($f['unit'], fn ($qq, $v) => $qq->whereHas('idea', fn ($i) => $i->where('department_name', $v)))
            ->when($f['from'], fn ($qq, $v) => $qq->where('created_at', '>=', $v))
            ->when($f['to'], fn ($qq, $v) => $qq->where('created_at', '<=', $v));
    }

    /**
     * Project yang LAHIR DARI IDE user ini — dipakai kartu "Cumulative Total Project
     * Generated from Approved Idea" agar konsisten dengan angka ide di sebelahnya.
     */
    private function projectsFromMyIdeasScope(User $user, bool $mine, array $f): Builder
    {
        return $this->applyProjectFilters(
            Project::query()->when(
                $mine,
                fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('user_id', $user->id))
            ),
            $f
        );
    }

    /**
     * Project milik sendiri = lahir dari ide user ini, ATAU user sebagai Project
     * Leader / Sponsor-nya. Keanggotaan committee tidak dihitung — itu peran
     * mereview, bukan "data yang dibuat sendiri".
     */
    private function projectScope(User $user, bool $mine, array $f): Builder
    {
        return $this->applyProjectFilters(
            Project::query()->when($mine, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereHas('idea', fn ($i) => $i->where('user_id', $user->id))
                    ->orWhere('project_leader_id', $user->id)
                    ->orWhere('project_sponsor_id', $user->id);
            })),
            $f
        );
    }

    /* ---- Metrik ------------------------------------------------------ */

    /** 5 metrik Idea pada Dashboard Matrix. */
    private function ideaMetrics(User $user, bool $mine, array $f): array
    {
        $nonDraft = ['submitted', 'review', 'approved', 'rejected', 'project_created'];
        $ideas    = fn () => $this->ideaScope($user, $mine, $f);

        return [
            'draft'              => $ideas()->where('status', 'draft')->count(),
            'cumulative_submit'  => $ideas()->whereIn('status', $nonDraft)->count(),
            'in_submitted'       => $ideas()->where('status', 'submitted')->count(),
            'approved'           => $ideas()->whereIn('status', ['approved', 'project_created'])->count(),
            'projects_generated' => $this->projectsFromMyIdeasScope($user, $mine, $f)->count(),
        ];
    }

    /** 6 metrik Project pada Dashboard Matrix. */
    private function projectMetrics(User $user, bool $mine, array $f): array
    {
        $approvedPlus = ['approved', 'ongoing', 'delayed', 'completion_review', 'completed'];
        $projects     = fn () => $this->projectScope($user, $mine, $f);

        $avgImprovement = ImplementationIndicator::whereIn(
            'project_id',
            $projects()->where('status', 'completed')->pluck('id')
        )->avg('improvement');

        return [
            'in_submitted'    => $projects()->where('status', 'submitted')->count(),
            'cumulative_appr' => $projects()->whereIn('status', $approvedPlus)->count(),
            'ongoing'         => $projects()->where('status', 'ongoing')->count(),
            'delayed'         => $projects()->where('status', 'delayed')->count(),
            'completed'       => $projects()->where('status', 'completed')->count(),
            'avg_improvement' => $avgImprovement ? round((float) $avgImprovement, 2) : 0,
        ];
    }
}
