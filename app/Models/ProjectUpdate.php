<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectUpdate extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return (self::CHANGE_TYPES[$this->change_type] ?? $this->change_type) . ' request';
    }

    public const CHANGE_TYPES = [
        'budget'   => 'Budget',
        'planning' => 'Planning / Target (Impl. Plan & Indicators)',
        'team'     => 'Team Member',
        'general'  => 'General',
    ];

    protected $fillable = [
        'project_id', 'requested_by', 'change_type', 'description',
        'approver_role', 'status', 'reviewed_by', 'review_note',
        'snapshot_before', 'snapshot_after',
    ];

    protected $casts = [
        'snapshot_before' => 'array',
        'snapshot_after'  => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
