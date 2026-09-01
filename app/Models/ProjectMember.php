<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        // Nama di-snapshot ke label log supaya audit trail tetap menyebut siapa
        // orangnya walau ia sudah dikeluarkan dari tim (atau hilang dari hcis).
        $name = optional($this->user)->name ?? ('#' . $this->user_id);

        return $name . ' — ' . ($this->role ?: 'Member') . ' (project #' . $this->project_id . ')';
    }

    protected $fillable = ['project_id', 'user_id', 'role', 'joined_at', 'is_active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
