<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasListQuery;
use App\Models\Idea;
use App\Models\KpnBusinessUnit;
use App\Models\Project;
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

    /** Jenis laporan: key => label pada dropdown. */
    public const TYPES = [
        'ideas'                  => 'Ideas',
        'project_shell'          => 'Project Shell',
        'project_proposal'       => 'Project Proposal',
        'project_implementation' => 'Project Implementation',
        'project_completion'     => 'Project Completion',
    ];

    /** Status yang tercakup tiap laporan project (null = semua). */
    private const PHASE_STATUSES = [
        'project_shell'          => null,
        'project_proposal'       => null,   // pakai EXCLUDE di bawah
        'project_implementation' => Project::EXECUTION_STATUSES,
        'project_completion'     => ['completion_review', 'completed'],
    ];

    /** Status milik fase lain yang dibuang dari Project Proposal (samakan dgn menu-nya). */
    private const PROPOSAL_EXCLUDE = ['ongoing', 'delayed', 'completion_review'];

    public function index(Request $request)
    {
        $type = $this->resolveType($request);

        [$query, $config] = $this->queryFor($type, $request);

        // Hitungan per status untuk tab — sadar filter & search, tanpa sort.
        $counts = (clone $query)->reorder()->getQuery()
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $tab = (string) $request->get('tab', 'all');
        if ($tab !== 'all' && $counts->has($tab)) {
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
        ] + $this->listSortState($request, $config));
    }

    /** Unduh hasil (filter & search yang sama) sebagai CSV. */
    public function download(Request $request): StreamedResponse
    {
        $type = $this->resolveType($request);
        [$query] = $this->queryFor($type, $request);

        $tab = (string) $request->get('tab', 'all');
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
        $config = $type === 'ideas'
            ? [
                'searchable'   => ['idea_id', 'idea_name', 'problem'],
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
                'searchable'   => ['project_id', 'project_name'],
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

        if ($type === 'ideas') {
            // TANPA visibleTo / kepemilikan: laporan memang lintas organisasi.
            $query = Idea::query()
                ->when($request->filled('bu'), fn ($q) => $q->where('business_unit_name', $request->get('bu')))
                ->when($request->filled('unit'), fn ($q) => $q->where('department_name', $request->get('unit')))
                ->with(['user']);
        } else {
            $query = Project::query()
                ->when($request->filled('bu'), fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('business_unit_name', $request->get('bu'))))
                ->when($request->filled('unit'), fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('department_name', $request->get('unit'))))
                ->with(['idea', 'leader', 'sponsor', 'category', 'budgets']);

            if ($statuses = self::PHASE_STATUSES[$type] ?? null) {
                $query->whereIn('status', $statuses);
            }
            if ($type === 'project_proposal') {
                $query->whereNotIn('status', self::PROPOSAL_EXCLUDE);
            }
        }

        $this->applyListSearchSort($query, $request, $config);

        return [$query, $config];
    }

    private function resolveType(Request $request): string
    {
        $type = (string) $request->get('type', 'ideas');

        return array_key_exists($type, self::TYPES) ? $type : 'ideas';
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

        // Fase eksekusi & penyelesaian: realisasi biaya ikut ditampilkan.
        if (in_array($type, ['project_implementation', 'project_completion'], true)) {
            $kolom[] = ['label' => 'Total Cost', 'value' => fn ($r) => (float) $r->budgets->sum(
                fn ($b) => (float) ($b->actual_cost ?? 0)
            )];
        }

        $kolom[] = ['label' => 'Created',      'sort' => 'created_at', 'value' => fn ($r) => $r->created_at];
        $kolom[] = ['label' => 'Last Updated', 'sort' => 'updated_at', 'value' => fn ($r) => $r->updated_at];

        return $kolom;
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
