<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class IdeaAttachment extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->file_name ?? ('Attachment #' . $this->getKey());
    }

    public $timestamps = false; // tabel hanya punya created_at

    protected $fillable = ['idea_id', 'file_name', 'file_path', 'file_size', 'uploaded_by'];

    protected $casts = ['created_at' => 'datetime'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }
}
