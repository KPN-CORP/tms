<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectAttachment extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->file_name ?? ('Attachment #' . $this->getKey());
    }

    public $timestamps = false; // tabel hanya punya created_at

    protected $fillable = ['project_id', 'file_name', 'file_path', 'file_size', 'uploaded_by'];

    protected $casts = ['created_at' => 'datetime'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
