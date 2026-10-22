<?php

namespace App\Http\Controllers;

use App\Models\ImplementationIndicator;
use App\Models\KpnBusinessUnit;
use App\Models\User;
use App\Services\Dashboard\MetricScopeService;
use App\Support\Navigation\InertiaRoutes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
 *
 * Kartu dikirim ke Vue sebagai daftar siap render (label, angka, nada warna, dan
 * URL tujuan). Pemilihan tujuan drill-down bergantung pada izin 'report.view',
 * jadi perhitungannya tetap di server — halaman tidak boleh menebak hak akses.
 */
class DashboardController extends Controller
{
    /** Pilihan "Act as" pada dropdown dashboard. */
    private const VIEWS = ['myself' => 'Myself', 'admin' => 'Admin'];

    /** Permission yang memunculkan dropdown "Act as" (diatur di Role Management). */
    private const PERMISSION_ACT_AS = 'dashboard.act-as';

    /**
     * Halaman tujuan bila user TIDAK punya 'report.view'. My Ideas / My Project
     * sudah tersaring ke datanya sendiri, jadi tidak perlu konteks Report.
     *
     * [nama route, nilai ?phase].
     */
    private const FALLBACK_ROUTES = [
        'idea_draft' => ['ideas.index', null],
        'idea_cumulative_submit' => ['ideas.index', null],
        'idea_in_submitted' => ['ideas.index', null],
        'idea_approved' => ['ideas.index', null],
        'idea_projects_generated' => ['projects.index', null],
        'project_in_submitted' => ['projects.index', null],
        'project_cumulative_appr' => ['projects.index', null],
        'project_ongoing' => ['projects.implementation', 'implementation'],
        'project_delayed' => ['projects.implementation', 'implementation'],
        'project_completed' => ['projects.completion', 'completion'],
        'project_avg_improvement' => ['projects.completion', 'completion'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $canActAs = $user->can(self::PERMISSION_ACT_AS);
        $as = $request->query('as');
        $as = array_key_exists($as, self::VIEWS) ? $as : ($canActAs ? 'admin' : 'myself');

        if (! $canActAs) {
            $as = 'myself'; // tanpa izin, hanya boleh melihat data sendiri
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // Business Unit & Unit disaring memakai NAMA (snapshot dari Darwinbox yang
        // ikut tersimpan di ide), sama seperti filter di halaman My Ideas / My Project.
        $filters = [
            'bu' => $request->filled('bu') ? $request->get('bu') : null,
            'unit' => $request->filled('unit') ? $request->get('unit') : null,
            'from' => $request->filled('from') ? $request->date('from')->startOfDay() : null,
            'to' => $request->filled('to') ? $request->date('to')->endOfDay() : null,
        ];

        $mine = $as === 'myself';

        return Inertia::render('Dashboard', [
            'ideaCards' => $this->ideaCards($user, $mine, $filters, $as),
            'projectCards' => $this->projectCards($user, $mine, $filters, $as),
            'canActAs' => $canActAs,
            'as' => $as,
            'views' => self::VIEWS,
            'filters' => [
                'bu' => $filters['bu'],
                'unit' => $filters['unit'],
                'from' => $filters['from']?->format('Y-m-d'),
                'to' => $filters['to']?->format('Y-m-d'),
            ],
            'hasFilter' => (bool) array_filter($filters),

            // Daftar Business Unit cukup kecil untuk ikut payload; daftar Unit
            // tetap diambil saat dibutuhkan (cascade) karena bergantung BU.
            'businessUnits' => KpnBusinessUnit::names()
                ->reject(fn ($name) => mb_strtolower(trim((string) $name)) === 'others')
                ->values(),
        ]);
    }

    /* ---- Metrik ------------------------------------------------------ */

    /**
     * 5 metrik Idea pada Dashboard Matrix.
     *
     * Cacahnya diambil dari MetricScopeService — definisi yang sama persis dipakai
     * Report saat kartu diklik, sehingga angka dan daftarnya tidak mungkin beda.
     */
    private function ideaCards(User $user, bool $mine, array $f, string $as): array
    {
        $scope = app(MetricScopeService::class);

        $count = fn (string $metric) => $scope->query($metric, $user, $mine, $f)->count();

        return [
            $this->card('idea_draft', 'Total Idea Draft', $count('idea_draft'), 'neutral', $f, $as),
            $this->card('idea_cumulative_submit', 'Cumulative Total Submitted Ideas', $count('idea_cumulative_submit'), 'neutral', $f, $as),
            $this->card('idea_in_submitted', 'Total Idea in Submitted Status', $count('idea_in_submitted'), 'info', $f, $as),
            $this->card('idea_approved', 'Total Idea Approved for Implementation', $count('idea_approved'), 'success', $f, $as),
            $this->card('idea_projects_generated', 'Cumulative Total Project Generated from Approved Idea', $count('idea_projects_generated'), 'brand', $f, $as),
        ];
    }

    /** 6 metrik Project pada Dashboard Matrix. */
    private function projectCards(User $user, bool $mine, array $f, string $as): array
    {
        $scope = app(MetricScopeService::class);

        $count = fn (string $metric) => $scope->query($metric, $user, $mine, $f)->count();

        // Rata-rata improvement dihitung dari indikator milik project yang selesai —
        // himpunan project-nya sama dengan kartu "Total Completed Projects".
        $avgImprovement = ImplementationIndicator::whereIn(
            'project_id',
            $scope->query('project_completed', $user, $mine, $f)->pluck('id')
        )->avg('improvement');

        return [
            $this->card('project_in_submitted', 'Total Project in Submitted Status', $count('project_in_submitted'), 'info', $f, $as),
            $this->card('project_cumulative_appr', 'Cumulative Total Approved Projects', $count('project_cumulative_appr'), 'success', $f, $as),
            $this->card('project_ongoing', 'Total Project in Ongoing Status', $count('project_ongoing'), 'neutral', $f, $as),
            $this->card('project_delayed', 'Total Projects in Delayed Status', $count('project_delayed'), 'warning', $f, $as),
            $this->card('project_completed', 'Total Completed Projects', $count('project_completed'), 'success', $f, $as),
            $this->card(
                'project_avg_improvement',
                'Average % Improvement',
                $avgImprovement ? round((float) $avgImprovement, 2) : 0,
                'neutral',
                $f,
                $as,
                suffix: '%',
            ),
        ];
    }

    private function card(string $metric, string $label, float|int $value, string $tone, array $f, string $as, string $suffix = ''): array
    {
        [$href, $route] = $this->drillDown($metric, $f, $as);

        return [
            'metric' => $metric,
            'label' => $label,
            'value' => $suffix === '' ? $value : $value . $suffix,
            'tone' => $tone,
            'href' => $href,
            // Report masih halaman Blade; tautan ke sana tidak boleh berupa
            // kunjungan Inertia. Lihat App\Support\Navigation\InertiaRoutes.
            'external' => ! InertiaRoutes::has($route),
        ];
    }

    /**
     * Tujuan saat kartu diklik.
     *
     * Pemegang 'report.view' diarahkan ke menu Report (satu-satunya tampilan
     * lintas organisasi), lengkap dengan filter yang sama. Selain itu ke My
     * Ideas / My Project sesuai fase metriknya.
     *
     * @return array{0: string, 1: string} [URL, nama route]
     */
    private function drillDown(string $metric, array $f, string $as): array
    {
        $common = array_filter([
            'metric' => $metric,
            'bu' => $f['bu'] ?: null,
            'unit' => $f['unit'] ?: null,
        ]);

        if (request()->user()?->can('report.view')) {
            return [
                route('reports.index', $common + array_filter([
                    'from' => $f['from']?->format('Y-m-d'),
                    'to' => $f['to']?->format('Y-m-d'),
                    'mine' => $as === 'myself' ? 1 : null,
                ])),
                'reports.index',
            ];
        }

        [$route] = self::FALLBACK_ROUTES[$metric] ?? ['ideas.index', null];

        return [route($route, $common), $route];
    }
}
