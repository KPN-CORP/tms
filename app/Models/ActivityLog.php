<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris audit trail (§9.2). Ditulis oleh trait LogsActivity.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event',
        'subject_type',
        'subject_id',
        'subject_label',
        'causer_id',
        'causer_name',
        'changes',
        'created_at',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /** Nama pendek model subject, mis. "Idea", "Project". */
    public function getSubjectShortAttribute(): string
    {
        return class_basename($this->subject_type);
    }

    /** [label, kelas badge] untuk event. */
    public function eventBadge(): array
    {
        return match ($this->event) {
            'created' => ['Created', 'bg-green-100 text-green-700'],
            'updated' => ['Updated', 'bg-blue-100 text-blue-700'],
            'deleted' => ['Deleted', 'bg-red-100 text-red-700'],
            default   => [ucfirst($this->event), 'bg-gray-100 text-gray-700'],
        };
    }
}
