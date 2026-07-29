<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAttachment extends Model
{
    public $timestamps = false; // tabel hanya punya created_at

    protected $fillable = ['project_id', 'file_name', 'file_path', 'file_size', 'uploaded_by'];

    protected $casts = ['created_at' => 'datetime'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
