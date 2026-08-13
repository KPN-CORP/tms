<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return ($this->role ?: 'Member') . ' (project #' . $this->project_id . ')';
    }

    protected $fillable = ['project_id', 'user_id', 'role', 'joined_at', 'is_active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
