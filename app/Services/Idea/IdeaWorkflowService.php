<?php

namespace App\Services\Idea;

use App\Models\CommitteeAssignment;
use App\Models\Idea;
use App\Models\IdeaApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alur review ide multi-layer (waterfall).
 *
 * submitted -> (layer 1 buka) review -> layer approve -> layer berikutnya
 *           -> layer terakhir approve -> approved
 *           -> reject di layer mana pun -> rejected (routing berhenti)
 *
 * T-95: committee routing di-tie dengan Business Unit + Unit (Department).
 * Assignment department-specific (department_id = department ide) mengalahkan
 * assignment BU-wide (department_id NULL). Bila tidak ada yang department-specific
 * untuk department ide, dipakai chain BU-wide.
 */
class IdeaWorkflowService
{
    /**
     * Department efektif untuk routing ide: department ide bila ada assignment
     * department-specific, selain itu NULL (pakai chain BU-wide).
     */
    public function effectiveDepartment(Idea $idea): ?int
    {
        $hasSpecific = CommitteeAssignment::where('approval_type', 'idea')
            ->where('business_unit_id', $idea->business_unit_id)
            ->where('department_id', $idea->department_id)
            ->exists();

        return $hasSpecific ? $idea->department_id : null;
    }

    /** Query committee efektif (BU + department efektif) untuk sebuah ide. */
    private function committeeQuery(Idea $idea): Builder
    {
        $dept = $this->effectiveDepartment($idea);

        return CommitteeAssignment::where('approval_type', 'idea')
            ->where('business_unit_id', $idea->business_unit_id)
            ->when(
                $dept === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $dept)
            );
    }

    /** Layer terakhir (tertinggi) pada chain committee efektif ide ini. */
    public function maxLayer(Idea $idea): ?int
    {
        return $this->committeeQuery($idea)->max('layer');
    }

    /** Apakah $user committee di layer terakhir chain efektif ide ini. */
    public function isLastLayerCommittee(Idea $idea, User $user): bool
    {
        $max = $this->maxLayer($idea);

        return $max && (clone $this->committeeQuery($idea))
            ->where('layer', $max)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Apakah user adalah committee yang menangani layer ide saat ini. */
    public function isCurrentReviewer(Idea $idea, User $user): bool
    {
        return in_array($idea->status, ['submitted', 'review'])
            && (clone $this->committeeQuery($idea))
                ->where('layer', $idea->current_layer)
                ->where('user_id', $user->id)
                ->exists();
    }

    /** FR-078: begitu dibuka committee layer aktif, status jadi On Review. */
    public function markOnReview(Idea $idea): void
    {
        if ($idea->status === 'submitted') {
            $idea->update(['status' => 'review']);
        }
    }

    public function approve(Idea $idea, User $user, ?string $note = null): void
    {
        IdeaApproval::create([
            'idea_id'  => $idea->id,
            'layer'    => $idea->current_layer,
            'user_id'  => $user->id,
            'decision' => 'approve',
            'note'     => $note,
        ]);

        $next = $idea->current_layer + 1;

        $hasNextLayer = (clone $this->committeeQuery($idea))
            ->where('layer', $next)
            ->exists();

        if ($hasNextLayer) {
            $idea->update(['current_layer' => $next, 'status' => 'review']);
        } else {
            $idea->update(['status' => 'approved']);
        }
    }

    public function reject(Idea $idea, User $user, ?string $note = null): void
    {
        IdeaApproval::create([
            'idea_id'  => $idea->id,
            'layer'    => $idea->current_layer,
            'user_id'  => $user->id,
            'decision' => 'reject',
            'note'     => $note,
        ]);

        $idea->update(['status' => 'rejected']);
    }

    /**
     * Query ide yang menunggu review oleh $user (di layer-nya masing-masing).
     * Match assignment department-specific ATAU BU-wide (bila tak ada yang
     * department-specific untuk department ide tsb) — "specific overrides general".
     */
    public function reviewQueueFor(User $user): Builder
    {
        return Idea::query()
            ->whereIn('status', ['submitted', 'review'])
            ->whereExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('committee_assignments as ca')
                    ->where('ca.approval_type', 'idea')
                    ->whereColumn('ca.business_unit_id', 'ideas.business_unit_id')
                    ->whereColumn('ca.layer', 'ideas.current_layer')
                    ->where('ca.user_id', $user->id)
                    ->where(function ($w) {
                        $w->whereColumn('ca.department_id', 'ideas.department_id')
                            ->orWhere(function ($ww) {
                                $ww->whereNull('ca.department_id')
                                    ->whereNotExists(function ($s) {
                                        $s->selectRaw('1')
                                            ->from('committee_assignments as cs')
                                            ->where('cs.approval_type', 'idea')
                                            ->whereColumn('cs.business_unit_id', 'ideas.business_unit_id')
                                            ->whereColumn('cs.department_id', 'ideas.department_id');
                                    });
                            });
                    });
            });
    }
}
