<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStatusLog extends Model
{
    public $timestamps = false; // tabel hanya punya created_at

    protected $fillable = ['project_id', 'old_status', 'new_status', 'changed_by', 'remarks'];

    protected $casts = ['created_at' => 'datetime'];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
