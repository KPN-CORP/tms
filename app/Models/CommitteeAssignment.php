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
        'idea'               => 'Idea',
        'project_proposal'   => 'Project Proposal',
        'project_completion' => 'Project Completion',
        'project_tracking'   => 'Project Tracking',
        'team_change'        => 'Team Change',
    ];

    /** Approval type yang memakai dimensi budget (range min–max). */
    public const BUDGET_SCOPED_TYPES = ['project_proposal'];

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
