<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\ImplementationIndicator;
use App\Models\Project;
use App\Models\User;
use App\Services\Dashboard\MetricScopeService;
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

    /* ---- Metrik ------------------------------------------------------ */

    /**
     * 5 metrik Idea pada Dashboard Matrix.
     *
     * Cacahnya diambil dari MetricScopeService — definisi yang sama persis dipakai
     * Report saat kartu diklik, sehingga angka dan daftarnya tidak mungkin beda.
     */
    private function ideaMetrics(User $user, bool $mine, array $f): array
    {
        $scope = app(MetricScopeService::class);

        return [
            'draft'              => $scope->query('idea_draft', $user, $mine, $f)->count(),
            'cumulative_submit'  => $scope->query('idea_cumulative_submit', $user, $mine, $f)->count(),
            'in_submitted'       => $scope->query('idea_in_submitted', $user, $mine, $f)->count(),
            'approved'           => $scope->query('idea_approved', $user, $mine, $f)->count(),
            'projects_generated' => $scope->query('idea_projects_generated', $user, $mine, $f)->count(),
        ];
    }

    /** 6 metrik Project pada Dashboard Matrix. */
    private function projectMetrics(User $user, bool $mine, array $f): array
    {
        $scope = app(MetricScopeService::class);

        // Rata-rata improvement dihitung dari indikator milik project yang selesai —
        // himpunan project-nya sama dengan kartu "Total Completed Projects".
        $avgImprovement = ImplementationIndicator::whereIn(
            'project_id',
            $scope->query('project_completed', $user, $mine, $f)->pluck('id')
        )->avg('improvement');

        return [
            'in_submitted'    => $scope->query('project_in_submitted', $user, $mine, $f)->count(),
            'cumulative_appr' => $scope->query('project_cumulative_appr', $user, $mine, $f)->count(),
            'ongoing'         => $scope->query('project_ongoing', $user, $mine, $f)->count(),
            'delayed'         => $scope->query('project_delayed', $user, $mine, $f)->count(),
            'completed'       => $scope->query('project_completed', $user, $mine, $f)->count(),
            'avg_improvement' => $avgImprovement ? round((float) $avgImprovement, 2) : 0,
        ];
    }
}
