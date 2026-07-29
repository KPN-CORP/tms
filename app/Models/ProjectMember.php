<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    protected $fillable = ['project_id', 'user_id', 'role', 'joined_at', 'is_active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
