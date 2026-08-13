<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectApproval extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return ucfirst((string) $this->decision) . ' — Layer ' . $this->layer . ' (project #' . $this->project_id . ')';
    }

    protected $fillable = ['project_id', 'layer', 'user_id', 'decision', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
