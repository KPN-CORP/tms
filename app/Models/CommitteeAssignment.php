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

    protected $fillable = ['approval_type', 'business_unit_id', 'department_id', 'layer', 'user_id'];

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
