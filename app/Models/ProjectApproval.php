<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectApproval extends Model
{
    protected $fillable = ['project_id', 'layer', 'user_id', 'decision', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
