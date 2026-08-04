<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Guideline extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'file_name',
        'file_path',
        'file_size',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Ukuran file human-readable, mis. "1.2 MB". */
    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }

    /** Label untuk audit trail (LogsActivity). */
    public function activityLabel(): string
    {
        return $this->title;
    }
}
