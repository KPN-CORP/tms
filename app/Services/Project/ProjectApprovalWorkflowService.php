<?php

namespace App\Services\Project;

use App\Models\CommitteeAssignment;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\User;

/**
 * Approval Committee multi-layer (waterfall) untuk Project — generik.
 * Satu mekanik dipakai beberapa "flow" (proposal, completion, dst) yang
 * dibedakan oleh STATUS review project, memakai committee_assignments
 * (approval_type per flow).
 */
class ProjectApprovalWorkflowService
{
    /** Catatan pada approval otomatis (pengaju = committee layer tsb). */
    public const AUTO_APPROVE_NOTE = 'Auto-approved: the requester is the assigned committee for this layer.';

    /** Batas aman jumlah layer yang boleh dilewati otomatis. */
    private const MAX_AUTO_SKIP = 20;

    /** Layer 1 project_proposal dicadangkan untuk Project Sponsor. */
    public const SPONSOR_LAYER = 1;

    /** Total budget project (Σ qty × unit_price) sebagai subquery, dipakai di reviewQueueFor. */
    private const TOTAL_BUDGET_SQL = '(SELECT COALESCE(SUM(pb.qty * pb.unit_price), 0) FROM project_budgets pb WHERE pb.project_id = projects.id)';

    /**
     * Peta flow berdasarkan status review project:
     * status review => [approval_type committee, status final, status reject].
     */
    private const FLOWS = [
        // no_committee: status bila belum ada committee untuk flow ini.
        // Project Proposal → tetap 'submitted' (tidak auto-approve).
        'committee_review'  => ['type' => 'project_proposal',   'final' => 'approved',  'reject' => 'rejected', 'no_committee' => 'submitted'],
        'completion_review' => ['type' => 'project_completion', 'final' => 'completed', 'reject' => 'approved'],
    ];

    /**
     * Department efektif untuk routing project (dari ide-nya): department ide bila
     * ada assignment department-specific (untuk proposal: yang budget-nya cocok),
     * selain itu NULL (pakai chain BU-wide).
     */
    private function effectiveDepartment(Project $project, string $type): ?int
    {
        $buId   = $project->businessUnitId();
        $deptId = $project->departmentId();

        if (! $buId || ! $deptId) {
            return null;
        }

        if (CommitteeAssignment::usesBudgetRange($type)) {
            // Dept-specific dipakai bila ada range budget yang cocok utk dept ini.
            return $this->matchedBudgetRange($project, $type, $deptId) ? $deptId : null;
        }

        $hasSpecific = CommitteeAssignment::where('approval_type', $type)
            ->where('business_unit_id', $buId)
            ->where('department_id', $deptId)
            ->exists();

        return $hasSpecific ? $deptId : null;
    }

    /**
     * Range budget (budget_min – budget_max) yang MEMUAT total budget project,
     * untuk BU + department tertentu. Bila lebih dari satu cocok (range tumpang
     * tindih), pilih range paling SEMPIT (paling spesifik).
     */
    private function matchedBudgetRange(Project $project, string $type, ?int $dept): ?object
    {
        $total = $project->budgetTotal();

        $groups = CommitteeAssignment::where('approval_type', $type)
            ->where('business_unit_id', $project->businessUnitId())
            ->when(
                $dept === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $dept)
            )
            ->select('budget_min', 'budget_max')
            ->distinct()
            ->get();

        return $groups
            ->filter(fn ($g) => CommitteeAssignment::budgetRangeMatches($g->budget_min, $g->budget_max, $total))
            ->sortBy(fn ($g) => (float) $g->budget_max - (float) $g->budget_min)
            ->first();
    }

    /**
     * Query committee efektif untuk sebuah project & flow:
     * BU + department efektif, dan (khusus project_proposal) range budget yang cocok.
     */
    private function committeeQuery(Project $project, string $type)
    {
        $dept = $this->effectiveDepartment($project, $type);

        $query = CommitteeAssignment::where('approval_type', $type)
            ->where('business_unit_id', $project->businessUnitId())
            ->when(
                $dept === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $dept)
            );

        if (CommitteeAssignment::usesBudgetRange($type)) {
            $group = $this->matchedBudgetRange($project, $type, $dept);
            if (! $group) {
                return $query->whereRaw('1 = 0'); // tidak ada range cocok → kosong
            }
            $query->where('budget_min', $group->budget_min)
                ->where('budget_max', $group->budget_max);
        }

        return $query;
    }

    /**
     * Daftar assignment committee (urut layer) untuk sebuah project & approval type.
     * Dipakai alur change request agar aturan BU + unit efektif + range budget
     * TIDAK ditulis ulang di tempat lain.
     */
    public function committeeLayersFor(Project $project, string $type)
    {
        if (! $project->businessUnitId()) {
            return collect();
        }

        return $this->committeeQuery($project, $type)->orderBy('layer')->get();
    }

    public function committeeExists(Project $project, string $type): bool
    {
        return $project->businessUnitId() && $this->committeeQuery($project, $type)->exists();
    }

    /**
     * Apakah layer aktif project ini punya penanggung jawab? Dipakai laporan
     * coverage committee (Admin) untuk mendeteksi item yang tertahan tanpa reviewer.
     */
    public function currentLayerHasAssignee(Project $project): bool
    {
        $flow = self::FLOWS[$project->status] ?? null;
        if (! $flow || ! $project->businessUnitId()) {
            return true; // bukan sedang menunggu committee → bukan urusan laporan ini
        }

        return (clone $this->committeeQuery($project, $flow['type']))
            ->where('layer', $project->current_layer)
            ->exists();
    }

    public function isCurrentReviewer(Project $project, User $user): bool
    {
        $flow = self::FLOWS[$project->status] ?? null;
        if (! $flow) {
            return false;
        }

        // Layer 1 dipegang Project Sponsor untuk flow yang memakai sponsor layer
        // (mis. Project Completion); committee baru mulai Layer 2.
        if (CommitteeAssignment::usesSponsorLayer($flow['type'])
            && (int) $project->current_layer === self::SPONSOR_LAYER) {
            return $project->project_sponsor_id === $user->id;
        }

        return $project->businessUnitId() && (clone $this->committeeQuery($project, $flow['type']))
            ->where('layer', $project->current_layer)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Mulai sebuah flow approval (dipanggil saat Sponsor approve / submit completion).
     * Set status review + layer 1. Jika belum ada committee untuk flow itu -> final langsung.
     */
    public function start(Project $project, string $reviewStatus, User $user, ?string $remarks = null): void
    {
        $flow = self::FLOWS[$reviewStatus];

        // Project Completion: Layer 1 SELALU Project Sponsor, jadi alurnya tetap
        // masuk review walau committee (Layer 2+) belum dikonfigurasi.
        $lewatSponsor = CommitteeAssignment::usesSponsorLayer($flow['type'])
            && $reviewStatus === 'completion_review';

        // Belum ada committee untuk flow ini → JANGAN masuk review.
        // Project Proposal: tetap 'submitted' (no_committee). Flow lain: langsung final.
        if (! $lewatSponsor && ! $this->committeeExists($project, $flow['type'])) {
            $fallback = $flow['no_committee'] ?? $flow['final'];
            $this->transition($project, $fallback, $user, "No committee for {$flow['type']}; status set to {$fallback}.");

            return;
        }

        if ($lewatSponsor) {
            $this->transition($project, $reviewStatus, $user, $remarks);
            $project->update(['current_layer' => self::SPONSOR_LAYER]);
            $this->autoApproveRequesterLayers($project, $flow);

            return;
        }

        // Layer committee TERKECIL dihitung lebih dulu supaya bisa disebut di
        // catatan riwayat. Untuk project_proposal, Layer 1 dicadangkan Project
        // Sponsor sehingga committee mulai dari Layer 2.
        $firstLayer = (int) $this->committeeQuery($project, $flow['type'])->min('layer') ?: 1;

        // Project Proposal: persetujuan Sponsor ADALAH Layer 1. Dicatat sebagai
        // Layer 1 di project_approvals dan disebut eksplisit di status log, agar
        // riwayat terbaca berurutan L1 -> L2 -> dst dan tidak seolah mulai dari L2.
        if ($flow['type'] === 'project_proposal') {
            $this->record($project, $user, 'approve', $remarks, self::SPONSOR_LAYER);

            $line = 'Approved layer ' . self::SPONSOR_LAYER
                . " (Project Sponsor), continuing to layer {$firstLayer}.";
            $remarks = $remarks ? "{$line} Note: {$remarks}" : $line;
        }

        $this->transition($project, $reviewStatus, $user, $remarks);
        $project->update(['current_layer' => $firstLayer]);

        // Layer yang approver-nya = pengaju (Project Leader) langsung di-approve.
        $this->autoApproveRequesterLayers($project, $flow);
    }

    /**
     * Auto-approve layer yang approver-nya adalah PENGAJU project itu sendiri
     * (Project Leader — pihak yang submit proposal maupun completion).
     *
     * Layer dilewati satu per satu, masing-masing tetap tercatat di
     * project_approvals sebagai jejak, sampai bertemu layer dengan approver lain
     * — sehingga project langsung masuk Task Box layer berikutnya. Bila semua
     * layer dipegang pengaju, flow langsung mencapai status final.
     */
    private function autoApproveRequesterLayers(Project $project, array $flow): void
    {
        $requester = $project->project_leader_id ? User::find($project->project_leader_id) : null;
        if (! $requester) {
            return;
        }

        for ($i = 0; $i < self::MAX_AUTO_SKIP; $i++) {
            // Sudah keluar dari status review (final/reject) → berhenti.
            if (! isset(self::FLOWS[$project->status])) {
                break;
            }

            // Layer 1 milik Project Sponsor pada flow ber-sponsor layer; layer lain
            // dicocokkan ke committee_assignments seperti biasa.
            $isSelf = (CommitteeAssignment::usesSponsorLayer($flow['type'])
                    && (int) $project->current_layer === self::SPONSOR_LAYER)
                ? $project->project_sponsor_id === $requester->id
                : (clone $this->committeeQuery($project, $flow['type']))
                    ->where('layer', $project->current_layer)
                    ->where('user_id', $requester->id)
                    ->exists();

            if (! $isSelf) {
                break;
            }

            $this->approve($project, $requester, self::AUTO_APPROVE_NOTE);
            $project->refresh();
        }
    }

    public function approve(Project $project, User $user, ?string $note = null): void
    {
        $flow = self::FLOWS[$project->status] ?? null;
        if (! $flow) {
            return;
        }

        $this->record($project, $user, 'approve', $note);

        $current = (int) $project->current_layer;

        // Layer committee berikutnya yang benar-benar ada. Layer 1 dilewati bila
        // flow ini memakai sponsor layer, karena Layer 1 bukan committee.
        $minimal = CommitteeAssignment::usesSponsorLayer($flow['type'])
            ? max($current, self::SPONSOR_LAYER)
            : $current;

        $next = (clone $this->committeeQuery($project, $flow['type']))
            ->where('layer', '>', $minimal)
            ->min('layer');

        if ($next) {
            $project->update(['current_layer' => (int) $next]);
            $this->log($project, $project->status, $project->status, $user, "Approved layer {$current}, continuing to layer {$next}.");
        } else {
            $this->transition($project, $flow['final'], $user, $note ?? "Approved final layer ({$current}).");
        }
    }

    /**
     * Revision Required — berlaku di SEMUA layer approval proposal. Project
     * langsung dikembalikan ke Project Leader (status 'revision_required'),
     * tidak diteruskan ke layer berikutnya dan tidak pula ditolak permanen.
     *
     * current_layer dikembalikan ke Layer 1 agar setelah Leader submit ulang
     * alur mengulang dari Project Sponsor.
     */
    public function requestRevision(Project $project, User $user, string $note): void
    {
        // Hanya untuk flow Project Proposal. Completion review tidak dikembalikan
        // ke Leader lewat jalur ini karena project sudah berjalan.
        if ($project->status !== 'committee_review') {
            return;
        }

        $layer = (int) $project->current_layer;
        $this->record($project, $user, 'revision', $note, $layer);
        $project->update(['current_layer' => self::SPONSOR_LAYER]);

        $this->transition($project, 'revision_required', $user,
            "Revision required at layer {$layer}; returned to the Project Leader. Note: {$note}");
    }

    public function reject(Project $project, User $user, ?string $note = null): void
    {
        $flow = self::FLOWS[$project->status] ?? null;
        if (! $flow) {
            return;
        }

        $this->record($project, $user, 'reject', $note);
        $this->transition($project, $flow['reject'], $user, $note);
    }

    /** Antrean review (semua flow) untuk $user di layer masing-masing. */
    public function reviewQueueFor(User $user)
    {
        return Project::whereIn('status', array_keys(self::FLOWS))
            ->where(function ($outer) use ($user) {
                // Sponsor sebagai pemegang Layer 1 (mis. Project Completion).
                foreach (self::FLOWS as $statusSponsor => $flowSponsor) {
                    if (! CommitteeAssignment::usesSponsorLayer($flowSponsor['type'])) {
                        continue;
                    }
                    $outer->orWhere(fn ($q) => $q
                        ->where('status', $statusSponsor)
                        ->where('current_layer', self::SPONSOR_LAYER)
                        ->where('project_sponsor_id', $user->id));
                }

                foreach (self::FLOWS as $status => $flow) {
                    $budgetScoped = CommitteeAssignment::usesBudgetRange($flow['type']);

                    $outer->orWhere(function ($q) use ($user, $status, $flow, $budgetScoped) {
                        $q->where('status', $status)
                            ->whereExists(function ($sub) use ($user, $flow, $budgetScoped) {
                                $sub->selectRaw('1')
                                    ->from('committee_assignments as ca')
                                    ->join('ideas as ix', 'ix.idea_id', '=', 'projects.idea_id')
                                    ->where('ca.approval_type', $flow['type'])
                                    ->whereColumn('ca.business_unit_id', 'ix.business_unit_id')
                                    ->whereColumn('ca.layer', 'projects.current_layer')
                                    ->where('ca.user_id', $user->id)
                                    // Budget-scoped (project_proposal): range harus MEMUAT total
                                    // budget project, DAN harus range paling sempit di antara yang
                                    // memuat — meniru matchedBudgetRange() agar antrean Task Box
                                    // tidak menampilkan project ke committee yang bukan reviewernya.
                                    ->when($budgetScoped, fn ($q) => $q
                                        ->whereRaw(self::TOTAL_BUDGET_SQL . ' BETWEEN ca.budget_min AND ca.budget_max')
                                        ->whereNotExists(fn ($n) => $n
                                            ->selectRaw('1')
                                            ->from('committee_assignments as cn')
                                            ->whereColumn('cn.approval_type', 'ca.approval_type')
                                            ->whereColumn('cn.business_unit_id', 'ca.business_unit_id')
                                            ->whereRaw('((cn.department_id IS NULL AND ca.department_id IS NULL) OR cn.department_id = ca.department_id)')
                                            ->whereRaw(self::TOTAL_BUDGET_SQL . ' BETWEEN cn.budget_min AND cn.budget_max')
                                            ->whereRaw('(cn.budget_max - cn.budget_min) < (ca.budget_max - ca.budget_min)')))
                                    ->where(function ($w) use ($flow, $budgetScoped) {
                                        // department-specific ATAU BU-wide (bila tak ada yang specific utk unit ide).
                                        $w->whereColumn('ca.department_id', 'ix.department_id')
                                            ->orWhere(function ($ww) use ($flow, $budgetScoped) {
                                                $ww->whereNull('ca.department_id')
                                                    ->whereNotExists(function ($s) use ($flow, $budgetScoped) {
                                                        $s->selectRaw('1')
                                                            ->from('committee_assignments as cs')
                                                            ->where('cs.approval_type', $flow['type'])
                                                            ->whereColumn('cs.business_unit_id', 'ix.business_unit_id')
                                                            ->whereColumn('cs.department_id', 'ix.department_id')
                                                            // Assignment dept-specific hanya "mengalahkan" BU-wide bila
                                                            // range budgetnya benar-benar memuat total project ini —
                                                            // sama seperti effectiveDepartment().
                                                            ->when($budgetScoped, fn ($cq) => $cq->whereRaw(
                                                                self::TOTAL_BUDGET_SQL . ' BETWEEN cs.budget_min AND cs.budget_max'
                                                            ));
                                                    });
                                            });
                                    });
                            });
                    });
                }
            });
    }

    /* ----------------------------------------------------------------- */

    private function record(Project $project, User $user, string $decision, ?string $note, ?int $layer = null): void
    {
        ProjectApproval::create([
            'project_id' => $project->id,
            'layer'      => $layer ?? $project->current_layer,
            'user_id'    => $user->id,
            'decision'   => $decision,
            'note'       => $note,
        ]);
    }

    private function transition(Project $project, string $new, User $user, ?string $remarks): void
    {
        $old = $project->status;
        $project->update(['status' => $new]);
        $this->log($project, $old, $new, $user, $remarks);
    }

    private function log(Project $project, string $old, string $new, User $user, ?string $remarks): void
    {
        $project->statusLogs()->create([
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => $user->id,
            'remarks'    => $remarks,
        ]);
    }
}
