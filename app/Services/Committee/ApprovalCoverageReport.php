<?php

namespace App\Services\Committee;

use App\Models\CommitteeAssignment;
use App\Models\Idea;
use App\Models\Project;
use App\Services\Idea\IdeaWorkflowService;
use App\Services\Project\ProjectApprovalWorkflowService;
use Illuminate\Support\Collection;

/**
 * Laporan "layer approval yang belum di-assign" untuk Admin / Super Admin.
 *
 * Sengaja DITURUNKAN dari data (dihitung saat halaman dibuka), bukan notifikasi
 * yang disimpan saat submit. Konsekuensinya:
 *  - peringatan hilang sendiri begitu assignment dibuat (tidak perlu dismiss);
 *  - item yang sudah terlanjur tertahan sebelum fitur ini ada ikut terdeteksi;
 *  - tidak butuh tabel notifications / queue / SMTP.
 *
 * Tiga kategori:
 *  1. blocked — item SEDANG menunggu di layer yang tidak punya penanggung jawab.
 *  2. gaps    — chain committee berlubang (mis. ada L1 & L3, L2 kosong). Berbahaya
 *               karena approve() hanya mencari layer+1 sehingga sisa layer terlewat.
 *  3. risks   — belum tertahan, tapi pasti bermasalah begitu alurnya dijalankan.
 */
class ApprovalCoverageReport
{
    public function __construct(
        private IdeaWorkflowService $ideaFlow,
        private ProjectApprovalWorkflowService $projectFlow,
    ) {
    }

    /** @return array{blocked:Collection,gaps:Collection,risks:Collection,total:int} */
    public function build(): array
    {
        $blocked = $this->blockedIdeas()->concat($this->blockedProjects());
        $gaps    = $this->layerGaps();
        $risks   = $this->completionRisks();

        return [
            'blocked' => $blocked,
            'gaps'    => $gaps,
            'risks'   => $risks,
            'total'   => $blocked->sum('count') + $gaps->count() + $risks->sum('count'),
        ];
    }

    /* ------------------------------------------------------------------ */

    /** Ide submitted/review yang layer aktifnya tidak punya committee. */
    private function blockedIdeas(): Collection
    {
        return Idea::whereIn('status', ['submitted', 'review'])
            ->get()
            ->filter(function (Idea $idea) {
                $dept = $this->ideaFlow->effectiveDepartment($idea);

                return ! CommitteeAssignment::where('approval_type', 'idea')
                    ->where('business_unit_id', $idea->business_unit_id)
                    ->when(
                        $dept === null,
                        fn ($q) => $q->whereNull('department_id'),
                        fn ($q) => $q->where('department_id', $dept)
                    )
                    ->where('layer', $idea->current_layer)
                    ->exists();
            })
            ->groupBy(fn (Idea $i) => $i->business_unit_id . '|' . $i->department_id . '|' . $i->current_layer)
            ->map(fn (Collection $g) => [
                'type'       => 'idea',
                'type_label' => CommitteeAssignment::TYPES['idea'],
                'bu_id'      => $g->first()->business_unit_id,
                'bu'         => $g->first()->business_unit_name ?? optional($g->first()->businessUnit)->name ?? '—',
                'unit'       => $g->first()->department_name ?? optional($g->first()->department)->name ?? '—',
                'layer'      => (int) $g->first()->current_layer,
                'count'      => $g->count(),
                'labels'     => $g->take(5)->pluck('idea_id')->all(),
                'oldest'     => $g->min('updated_at'),
            ])
            ->values();
    }

    /** Project yang sedang review tapi layer aktifnya tidak punya committee. */
    private function blockedProjects(): Collection
    {
        return Project::whereIn('status', ['committee_review', 'completion_review'])
            ->with('idea')
            ->get()
            ->filter(fn (Project $p) => ! $this->projectFlow->currentLayerHasAssignee($p))
            ->groupBy(fn (Project $p) => $p->status . '|' . $p->businessUnitId() . '|' . $p->departmentId() . '|' . $p->current_layer)
            ->map(function (Collection $g) {
                $first = $g->first();
                $type  = $first->status === 'committee_review' ? 'project_proposal' : 'project_completion';

                return [
                    'type'       => $type,
                    'type_label' => CommitteeAssignment::TYPES[$type],
                    'bu_id'      => $first->businessUnitId(),
                    'bu'         => optional($first->idea)->business_unit_name ?? '—',
                    'unit'       => optional($first->idea)->department_name ?? '—',
                    'layer'      => (int) $first->current_layer,
                    'count'      => $g->count(),
                    'labels'     => $g->take(5)->pluck('project_id')->all(),
                    'oldest'     => $g->min('updated_at'),
                ];
            })
            ->values();
    }

    /**
     * Chain berlubang: dalam satu set (type + BU + unit + range budget), ada layer
     * antara 1..max yang tidak terisi. approve() hanya mencari layer+1, jadi lubang
     * membuat sisa layer TERLEWAT dan item langsung dianggap final.
     */
    private function layerGaps(): Collection
    {
        return CommitteeAssignment::with(['businessUnit', 'department'])
            ->get()
            ->groupBy(fn ($c) => implode('|', [
                $c->approval_type, $c->business_unit_id, $c->department_id ?? '-', $c->budget_min, $c->budget_max,
            ]))
            ->map(function (Collection $g) {
                // Rentang diperiksa dari layer TERKECIL yang dikonfigurasi, bukan dari 1:
                // untuk project_proposal Layer 1 memang sengaja dikosongkan karena
                // dicadangkan untuk Project Sponsor, dan start() mulai dari min layer.
                $layers  = $g->pluck('layer')->map(fn ($l) => (int) $l)->unique()->sort()->values();
                $missing = collect(range($layers->min(), $layers->max()))->diff($layers)->values();
                if ($missing->isEmpty()) {
                    return null;
                }

                $first = $g->first();

                return [
                    'type'       => $first->approval_type,
                    'type_label' => CommitteeAssignment::TYPES[$first->approval_type] ?? $first->approval_type,
                    'bu_id'      => $first->business_unit_id,
                    'bu'         => optional($first->businessUnit)->name ?? '—',
                    'unit'       => optional($first->department)->name ?? 'All units (BU-wide)',
                    'missing'    => $missing->all(),
                    'lowest'     => $layers->min(),
                    'highest'    => $layers->max(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Project yang sedang berjalan di BU/unit TANPA committee project_completion.
     * Belum tertahan, tapi begitu completion disubmit, start() langsung menandai
     * project 'completed' tanpa direview siapa pun — lolos diam-diam.
     */
    private function completionRisks(): Collection
    {
        return Project::whereIn('status', Project::EXECUTION_STATUSES)
            ->with('idea')
            ->get()
            ->filter(fn (Project $p) => ! $this->projectFlow->committeeExists($p, 'project_completion'))
            ->groupBy(fn (Project $p) => $p->businessUnitId() . '|' . $p->departmentId())
            ->map(function (Collection $g) {
                $first = $g->first();

                return [
                    'type'       => 'project_completion',
                    'type_label' => CommitteeAssignment::TYPES['project_completion'],
                    'bu_id'      => $first->businessUnitId(),
                    'bu'         => optional($first->idea)->business_unit_name ?? '—',
                    'unit'       => optional($first->idea)->department_name ?? '—',
                    'count'      => $g->count(),
                    'labels'     => $g->take(5)->pluck('project_id')->all(),
                ];
            })
            ->values();
    }
}
