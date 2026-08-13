<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ImplementationPlan extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->activity ?? ('Activity #' . $this->getKey());
    }

    protected $fillable = [
        'project_id', 'activity', 'planning_start', 'planning_end',
        'actual_start', 'actual_end', 'pic_user_ids', 'remarks', 'sequence_no',
    ];

    protected $casts = [
        'planning_start' => 'date',
        'planning_end'   => 'date',
        'actual_start'   => 'date',
        'actual_end'     => 'date',
        'pic_user_ids'   => 'array',
    ];

    public function getPlanningDaysAttribute(): ?int
    {
        return $this->planning_start && $this->planning_end
            ? $this->planning_start->diffInDays($this->planning_end) + 1
            : null;
    }

    public function getActualDaysAttribute(): ?int
    {
        return $this->actual_start && $this->actual_end
            ? $this->actual_start->diffInDays($this->actual_end) + 1
            : null;
    }

    /** Status otomatis (T-63). */
    public function getStatusLabelAttribute(): string
    {
        if (! $this->actual_start) {
            return 'Not Started';
        }

        if (! $this->actual_end) {
            return ($this->planning_end && now()->gt($this->planning_end)) ? 'Delayed' : 'On Going';
        }

        if ($this->planning_end && $this->actual_end->gt($this->planning_end)) {
            return 'Completed (Late)';
        }

        return 'Completed (On Time)';
    }

    /** User yang jadi PIC (multi-select). */
    public function pics()
    {
        return User::whereIn('id', $this->pic_user_ids ?? [])->get();
    }
}
