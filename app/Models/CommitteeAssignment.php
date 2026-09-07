<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class CommitteeAssignment extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return (self::TYPES[$this->approval_type] ?? $this->approval_type) . ' — Layer ' . $this->layer;
    }

    public const TYPES = [
        'idea'                  => 'Idea Submission',
        'project_proposal'      => 'Project Proposal',
        'team_change'           => 'Team Change',
        'plan_indicator_change' => 'Plan & Indicator Change',
        'budget_change'         => 'Budget Change',
        'project_completion'    => 'Project Completion',
    ];

    /** Approval type yang memakai dimensi budget (range min–max). */
    public const BUDGET_SCOPED_TYPES = ['project_proposal', 'budget_change'];

    /** Layer yang selalu dipegang Project Sponsor. */
    public const SPONSOR_LAYER = 1;

    /**
     * Approval type yang Layer 1-nya OTOMATIS Project Sponsor project terkait,
     * sehingga committee dikonfigurasi mulai Layer 2. Hanya "Idea Submission"
     * yang tidak punya sponsor, jadi ia bebas dikonfigurasi dari Layer 1.
     */
    public const SPONSOR_LAYER_TYPES = [
        'project_proposal', 'team_change', 'plan_indicator_change',
        'budget_change', 'project_completion',
    ];

    public static function usesSponsorLayer(?string $type): bool
    {
        return in_array((string) $type, self::SPONSOR_LAYER_TYPES, true);
    }

    /** Sentinel "tak hingga" untuk batas atas range (bilangan bulat, kolom decimal 20,2). */
    public const BUDGET_MAX_UNBOUNDED = 999999999999999;

    /** Apakah approval type ini memakai kondisi budget (range)? */
    public static function usesBudgetRange(string $type): bool
    {
        return in_array($type, self::BUDGET_SCOPED_TYPES, true);
    }

    /** Apakah total budget masuk dalam range [min, max] (inklusif). */
    public static function budgetRangeMatches($min, $max, float $total): bool
    {
        return $total >= (float) $min && $total <= (float) $max;
    }

    /** Label range, mis. "Rp 20.000.000 – Rp 100.000.000". */
    public static function budgetRangeLabel($min, $max): ?string
    {
        if ($min === null || $max === null) {
            return null;
        }

        $fmt = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        if ((float) $max >= self::BUDGET_MAX_UNBOUNDED) {
            return '≥ ' . $fmt($min);
        }

        return $fmt($min) . ' – ' . $fmt($max);
    }

    protected $fillable = ['approval_type', 'business_unit_id', 'department_id', 'budget_min', 'budget_max', 'layer', 'user_id'];

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
