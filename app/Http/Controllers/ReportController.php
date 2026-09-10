<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasListQuery;
use App\Models\Idea;
use App\Models\KpnBusinessUnit;
use App\Models\Project;
use App\Models\User;
use App\Services\Dashboard\MetricScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report — akses seluruh data Idea & Project lintas organisasi.
 *
 * My Ideas / My Project sengaja tetap terbatas pada milik sendiri atau yang user
 * terlibat di dalamnya; halaman ini yang menyediakan pandangan menyeluruh, dan
 * karena itu dikunci permission 'report.view'.
 *
 * Satu halaman, jenis laporannya dipilih lewat dropdown "Select Report".
 */
class ReportController extends Controller
{
    use HasListQuery;

    /**
     * Jenis laporan: key => label pada dropdown. Cukup dua — Idea dan Project.
     * Pemilahan per fase tidak lagi jadi jenis laporan tersendiri karena tab
     * status di atas tabel sudah menyediakan penyaringan yang sama.
     */
    public const TYPES = [
        'ideas'   => 'Ideas',
        'project' => 'Project',
    ];

    public function index(Request $request)
    {
        $type = $this->resolveType($request);
        $metric = $request->get('metric');
        $metric = MetricScopeService::exists($metric) ? $metric : null;

        [$query, $config] = $this->queryFor($type, $request);

        // Hitungan per status untuk tab — sadar filter & search, tanpa sort.
        $counts = (clone $query)->reorder()->getQuery()
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        // Disaring berdasarkan DAFTAR STATUS yang sah, bukan berdasarkan ada-tidaknya
        // hasil. Kalau bersandar pada $counts, tab yang kebetulan kosong akan
        // dilewati dan justru menampilkan SELURUH baris.
        $tab = $this->resolveTab($request, $type);
        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $perPage = $this->listPerPage($request, 25);

        return view('reports.index', [
            'type'      => $type,
            'types'     => self::TYPES,
            'rows'      => $query->paginate($perPage)->withQueryString(),
            'perPage'   => $perPage,
            'columns'   => $this->columns($type),
            'counts'    => $counts,
            'tab'       => $tab,
            'statusMap' => $this->statusLabels($type),
            'buNames'   => KpnBusinessUnit::names(),
            'isIdea'    => $type === 'ideas',
            // Judul & tombol hapus filter saat halaman dibuka dari kartu Dashboard.
            'metric'      => $metric,
            'metricLabel' => $metric ? MetricScopeService::labelOf($metric) : null,
            // Ditampilkan sebagai badge agar jelas daftar ini bukan lingkup organisasi.
            'mineOnly'    => $this->mineOnly($request),
        ] + $this->listSortState($request, $config));
    }

    /** Unduh hasil (filter & search yang sama) sebagai CSV. */
    public function download(Request $request): StreamedResponse
    {
        $type = $this->resolveType($request);
        [$query] = $this->queryFor($type, $request);

        $tab = $this->resolveTab($request, $type);
        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $columns = $this->columns($type);
        $nama    = 'TMS-' . str_replace('_', '-', $type) . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel membaca karakter non-ASCII dengan benar.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 'label'));

            // chunk agar ekspor besar tidak menghabiskan memori.
            $query->chunk(500, function ($items) use ($out, $columns) {
                foreach ($items as $row) {
                    fputcsv($out, array_map(fn ($c) => $this->plain(($c['value'])($row)), $columns));
                }
            });

            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ---- Query per jenis laporan ------------------------------------- */

    /** @return array{0:Builder,1:array} */
    private function queryFor(string $type, Request $request): array
    {
        // 'searchable' sengaja kosong: pencarian ditangani applySearch() karena
        // harus menjangkau NAMA orang yang tersimpan di database hcis (koneksi lain),
        // sehingga tidak bisa lewat whereHas biasa.
        $config = $type === 'ideas'
            ? [
                'searchable'   => [],
                'sortable'     => [
                    'idea_id'    => 'idea_id',
                    'idea_name'  => 'idea_name',
                    'status'     => 'status',
                    'created_at' => 'created_at',
                    'updated_at' => 'updated_at',
                ],
                'default_sort' => 'created_at',
                'default_dir'  => 'desc',
            ]
            : [
                'searchable'   => [],
                'sortable'     => [
                    'project_id'   => 'project_id',
                    'project_name' => 'project_name',
                    'status'       => 'status',
                    'created_at'   => 'created_at',
                    'updated_at'   => 'updated_at',
                ],
                'default_sort' => 'created_at',
                'default_dir'  => 'desc',
            ];

        $metric = $request->get('metric');
        $scope  = app(MetricScopeService::class);
        $mine   = $this->mineOnly($request);
        $f      = $this->filters($request);

        if (MetricScopeService::exists($metric)) {
            // Basis diambil dari definisi metrik Dashboard supaya jumlah barisnya
            // sama persis dgn angka pada kartu yang diklik.
            $query = $scope->query($metric, $request->user(), $mine, $f);
        } elseif ($type === 'ideas') {
            $query = $scope->ideaScope($request->user(), $mine, $f);
        } else {
            $query = $scope->projectScope($request->user(), $mine, $f);
        }

        $query->with($type === 'ideas'
            ? ['user']
            : ['idea', 'leader', 'sponsor', 'category', 'budgets']);

        $this->applySearch($query, $type, trim((string) $request->get('q')));
        $this->applyListSearchSort($query, $request, $config);

        return [$query, $config];
    }

    /**
     * Pencarian satu kotak untuk seluruh laporan.
     *
     *   Ideas   : Idea ID, Idea Name, nama pengaju (Submitted By)
     *   Project : Project ID, Project Name, nama SELURUH anggota tim —
     *             Project Leader, Project Sponsor, dan anggota lain.
     *
     * Nama orang tinggal di database hcis (koneksi terpisah), sehingga tidak bisa
     * di-join langsung. Nama dicocokkan lebih dulu menjadi daftar user id, baru
     * dipakai menyaring di koneksi aplikasi.
     */
    private function applySearch(Builder $query, string $type, string $term): void
    {
        if ($term === '') {
            return;
        }

        $userIds = User::where('name', 'like', "%{$term}%")
            ->orWhere('employee_id', 'like', "%{$term}%")
            ->limit(500)
            ->pluck('id');

        if ($type === 'ideas') {
            $query->where(function (Builder $q) use ($term, $userIds) {
                $q->where('idea_id', 'like', "%{$term}%")
                    ->orWhere('idea_name', 'like', "%{$term}%")
                    ->when($userIds->isNotEmpty(), fn ($w) => $w->orWhereIn('user_id', $userIds));
            });

            return;
        }

        $query->where(function (Builder $q) use ($term, $userIds) {
            $q->where('project_id', 'like', "%{$term}%")
                ->orWhere('project_name', 'like', "%{$term}%")
                ->when($userIds->isNotEmpty(), fn ($w) => $w
                    ->orWhereIn('project_leader_id', $userIds)
                    ->orWhereIn('project_sponsor_id', $userIds)
                    ->orWhereHas('members', fn ($m) => $m->whereIn('user_id', $userIds)));
        });
    }

    private function resolveType(Request $request): string
    {
        // Dibuka dari kartu Dashboard: jenis laporan mengikuti metriknya.
        $metric = $request->get('metric');
        if (MetricScopeService::exists($metric)) {
            return MetricScopeService::kindOf($metric) === 'idea' ? 'ideas' : 'project';
        }

        $type = (string) $request->get('type', 'ideas');

        if (array_key_exists($type, self::TYPES)) {
            return $type;
        }

        // Tautan lama (project_shell, project_proposal, dst) tetap mendarat di
        // laporan Project, bukan terlempar ke Ideas.
        return str_starts_with($type, 'project') ? 'project' : 'ideas';
    }

    /** Tab status yang sah untuk jenis laporan ini ('all' bila tidak dikenali). */
    private function resolveTab(Request $request, string $type): string
    {
        $tab = (string) $request->get('tab', 'all');

        return array_key_exists($tab, $this->statusLabels($type)) ? $tab : 'all';
    }

    /* ---- Kolom per jenis laporan ------------------------------------- */

    /** @return array<int, array{label:string, value:callable, sort?:string}> */
    private function columns(string $type): array
    {
        if ($type === 'ideas') {
            return [
                ['label' => 'Idea ID',       'sort' => 'idea_id',    'value' => fn ($r) => $r->idea_id],
                ['label' => 'Idea Name',     'sort' => 'idea_name',  'value' => fn ($r) => $r->idea_name],
                ['label' => 'Business Unit', 'value' => fn ($r) => $r->business_unit_name],
                ['label' => 'Unit',          'value' => fn ($r) => $r->department_name],
                ['label' => 'Submitted By',  'value' => fn ($r) => optional($r->user)->name],
                ['label' => 'Status',        'sort' => 'status',     'value' => fn ($r) => $r->status],
                ['label' => 'Layer',         'value' => fn ($r) => $r->current_layer],
                ['label' => 'Created',       'sort' => 'created_at', 'value' => fn ($r) => $r->created_at],
                ['label' => 'Last Updated',  'sort' => 'updated_at', 'value' => fn ($r) => $r->updated_at],
            ];
        }

        $kolom = [
            ['label' => 'Project ID',    'sort' => 'project_id',   'value' => fn ($r) => $r->project_id],
            ['label' => 'Project Name',  'sort' => 'project_name', 'value' => fn ($r) => $r->project_name],
            ['label' => 'Business Unit', 'value' => fn ($r) => optional($r->idea)->business_unit_name],
            ['label' => 'Unit',          'value' => fn ($r) => optional($r->idea)->department_name],
            ['label' => 'Leader',        'value' => fn ($r) => optional($r->leader)->name],
            ['label' => 'Sponsor',       'value' => fn ($r) => optional($r->sponsor)->name],
            ['label' => 'Status',        'sort' => 'status',       'value' => fn ($r) => $r->status],
            // Dihitung dari relasi budgets yang sudah di-eager-load, bukan
            // budgetTotal() yang menembak query per baris (N+1 pada daftar panjang).
            ['label' => 'Total Budget',  'value' => fn ($r) => (float) $r->budgets->sum(
                fn ($b) => (float) $b->qty * (float) $b->unit_price
            )],
        ];

        // Realisasi biaya selalu ikut: satu laporan Project mencakup semua fase.
        $kolom[] = ['label' => 'Total Cost', 'value' => fn ($r) => (float) $r->budgets->sum(
            fn ($b) => (float) ($b->actual_cost ?? 0)
        )];

        $kolom[] = ['label' => 'Created',      'sort' => 'created_at', 'value' => fn ($r) => $r->created_at];
        $kolom[] = ['label' => 'Last Updated', 'sort' => 'updated_at', 'value' => fn ($r) => $r->updated_at];

        return $kolom;
    }

    /**
     * Apakah daftar dikunci ke data milik user sendiri?
     *
     * Pemegang 'report.view' melihat seluruh organisasi (kecuali ia sendiri memilih
     * mine=1 lewat kartu Dashboard "Act as Myself"). User tanpa izin itu SELALU
     * dikunci — menghapus mine=1 dari URL tidak membuka data orang lain.
     */
    private function mineOnly(Request $request): bool
    {
        return ! $request->user()->can('report.view') || $request->boolean('mine');
    }

    /** Filter organisasi & periode dalam bentuk yang dipakai MetricScopeService. */
    private function filters(Request $request): array
    {
        return [
            'bu'   => $request->filled('bu') ? $request->get('bu') : null,
            'unit' => $request->filled('unit') ? $request->get('unit') : null,
            'from' => $request->filled('from') ? $request->date('from')->startOfDay() : null,
            'to'   => $request->filled('to') ? $request->date('to')->endOfDay() : null,
        ];
    }

    /** Tautan detail (read-only) untuk tombol mata di kolom Action. */
    public static function detailUrl(string $type, $row): string
    {
        // from=report dipakai halaman detail untuk mengarahkan tombol Back
        // kembali ke Report, bukan ke My Ideas / My Project.
        if ($type === 'ideas') {
            return route('ideas.show', ['idea' => $row->id, 'from' => 'report']);
        }

        return route('projects.show', [
            'project' => $row->id,
            'view'    => 1,
            'from'    => 'report',
        ]);
    }

    /** Label status untuk badge & tab. */
    private function statusLabels(string $type): array
    {
        if ($type === 'ideas') {
            return [
                'draft' => 'Draft', 'submitted' => 'Submitted', 'review' => 'On Review',
                'approved' => 'Approved', 'rejected' => 'Rejected',
            ];
        }

        return collect(Project::STATUS_BADGES)->map(fn ($b) => $b[0])->all();
    }

    /** Nilai untuk CSV: tanggal jadi teks, sisanya apa adanya. */
    private function plain($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}
