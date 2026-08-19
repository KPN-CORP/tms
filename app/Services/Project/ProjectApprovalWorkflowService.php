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

    public function committeeExists(Project $project, string $type): bool
    {
        return $project->businessUnitId() && $this->committeeQuery($project, $type)->exists();
    }

    public function isCurrentReviewer(Project $project, User $user): bool
    {
        $flow = self::FLOWS[$project->status] ?? null;

        return $flow && $project->businessUnitId() && (clone $this->committeeQuery($project, $flow['type']))
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

        // Belum ada committee untuk flow ini → JANGAN masuk review.
        // Project Proposal: tetap 'submitted' (no_committee). Flow lain: langsung final.
        if (! $this->committeeExists($project, $flow['type'])) {
            $fallback = $flow['no_committee'] ?? $flow['final'];
            $this->transition($project, $fallback, $user, "No committee for {$flow['type']}; status set to {$fallback}.");

            return;
        }

        $this->transition($project, $reviewStatus, $user, $remarks);

        // Mulai dari layer committee TERKECIL. Untuk project_proposal, Layer 1
        // dicadangkan Project Sponsor sehingga committee mulai dari Layer 2.
        $firstLayer = (int) $this->committeeQuery($project, $flow['type'])->min('layer');
        $project->update(['current_layer' => $firstLayer ?: 1]);
    }

    public function approve(Project $project, User $user, ?string $note = null): void
    {
        $flow = self::FLOWS[$project->status] ?? null;
        if (! $flow) {
            return;
        }

        $this->record($project, $user, 'approve', $note);

        $current = $project->current_layer;
        $next    = $current + 1;

        $hasNext = (clone $this->committeeQuery($project, $flow['type']))
            ->where('layer', $next)
            ->exists();

        if ($hasNext) {
            $project->update(['current_layer' => $next]);
            $this->log($project, $project->status, $project->status, $user, "Approved layer {$current}, continuing to layer {$next}.");
        } else {
            $this->transition($project, $flow['final'], $user, $note ?? "Approved final layer ({$current}).");
        }
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
                foreach (self::FLOWS as $status => $flow) {
                    $outer->orWhere(function ($q) use ($user, $status, $flow) {
                        $q->where('status', $status)
                            ->whereExists(function ($sub) use ($user, $flow) {
                                $sub->selectRaw('1')
                                    ->from('committee_assignments as ca')
                                    ->join('ideas as ix', 'ix.idea_id', '=', 'projects.idea_id')
                                    ->where('ca.approval_type', $flow['type'])
                                    ->whereColumn('ca.business_unit_id', 'ix.business_unit_id')
                                    ->whereColumn('ca.layer', 'projects.current_layer')
                                    ->where('ca.user_id', $user->id)
                                    // Khusus project_proposal: cocokkan kondisi budget (operator + nominal)
                                    // terhadap total budget project (Σ qty×price).
                                    ->when($flow['type'] === 'project_proposal', fn ($q) => $q->whereRaw(
                                        '(SELECT COALESCE(SUM(pb.qty * pb.unit_price), 0) FROM project_budgets pb WHERE pb.project_id = projects.id) BETWEEN ca.budget_min AND ca.budget_max'
                                    ))
                                    ->where(function ($w) use ($flow) {
                                        // department-specific ATAU BU-wide (bila tak ada yang specific utk unit ide).
                                        $w->whereColumn('ca.department_id', 'ix.department_id')
                                            ->orWhere(function ($ww) use ($flow) {
                                                $ww->whereNull('ca.department_id')
                                                    ->whereNotExists(function ($s) use ($flow) {
                                                        $s->selectRaw('1')
                                                            ->from('committee_assignments as cs')
                                                            ->where('cs.approval_type', $flow['type'])
                                                            ->whereColumn('cs.business_unit_id', 'ix.business_unit_id')
                                                            ->whereColumn('cs.department_id', 'ix.department_id');
                                                    });
                                            });
                                    });
                            });
                    });
                }
            });
    }

    /* ----------------------------------------------------------------- */

    private function record(Project $project, User $user, string $decision, ?string $note): void
    {
        ProjectApproval::create([
            'project_id' => $project->id,
            'layer'      => $project->current_layer,
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
