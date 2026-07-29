<?php

namespace App\Services\Project;

use App\Models\CommitteeAssignment;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Routing & audit untuk Project Update Request (T-260-268).
 *
 * Aturan approver:
 *  - Budget      -> Committee (project_proposal, layer terakhir)     [T-268]
 *  - Planning    -> Sponsor bila diajukan Team; Committee bila Sponsor [T-266/267]
 *  - Team/General-> Sponsor bila diajukan Team; auto-approved bila Sponsor [T-261/262]
 */
class ProjectUpdateService
{
    public function resolveApprover(string $changeType, bool $requesterIsSponsor): string
    {
        return match ($changeType) {
            'budget'   => 'committee',
            'planning' => $requesterIsSponsor ? 'committee' : 'sponsor',
            default    => $requesterIsSponsor ? 'auto' : 'sponsor', // team, general
        };
    }

    /** Snapshot seluruh section proposal untuk before/after (T-78). */
    public function snapshot(Project $project): array
    {
        return [
            'indicators'     => $project->indicators()->get(['indicator', 'baseline', 'achievement', 'uom', 'weightage', 'type', 'improvement'])->toArray(),
            'implementation' => $project->implementationPlans()->get(['activity', 'planning_start', 'planning_end', 'actual_start', 'actual_end', 'pic_user_ids', 'remarks'])->toArray(),
            'budget'         => $project->budgets()->get(['item', 'qty', 'uom', 'unit_price'])->toArray(),
            'team'           => $project->members()->get(['user_id', 'role'])->toArray(),
        ];
    }

    /** User committee project_proposal di layer terakhir untuk BU project. */
    public function proposalCommitteeLastLayer(Project $project): Collection
    {
        $buId = $project->businessUnitId();
        if (! $buId) {
            return collect();
        }

        $maxLayer = CommitteeAssignment::where('approval_type', 'project_proposal')
            ->where('business_unit_id', $buId)->max('layer');

        if (! $maxLayer) {
            return collect();
        }

        return CommitteeAssignment::where('approval_type', 'project_proposal')
            ->where('business_unit_id', $buId)
            ->where('layer', $maxLayer)
            ->pluck('user_id');
    }

    public function isReviewer(ProjectUpdate $update, User $user): bool
    {
        if ($update->status !== 'pending') {
            return false;
        }

        $project = $update->project;

        return match ($update->approver_role) {
            'sponsor'   => $project->project_sponsor_id === $user->id,
            'committee' => $this->proposalCommitteeLastLayer($project)->contains($user->id),
            default     => false,
        };
    }
}
