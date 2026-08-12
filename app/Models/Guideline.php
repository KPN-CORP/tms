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
        'employee_can_view',
        'employee_can_download',
        'committee_can_view',
        'committee_can_download',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'employee_can_view'      => 'boolean',
        'employee_can_download'  => 'boolean',
        'committee_can_view'     => 'boolean',
        'committee_can_download' => 'boolean',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Boleh VIEW (baca inline) bila hak Employee mengizinkan, atau user committee
     * dan hak Committee mengizinkan. (Admin/pengelola dicek terpisah di controller.)
     */
    public function viewableBy(bool $isCommittee): bool
    {
        return $this->employee_can_view || ($isCommittee && $this->committee_can_view);
    }

    /** Boleh DOWNLOAD — logika sama seperti view, untuk hak download. */
    public function downloadableBy(bool $isCommittee): bool
    {
        return $this->employee_can_download || ($isCommittee && $this->committee_can_download);
    }

    /** Ekstensi file (lowercase) — untuk menentukan bisa dibuka inline (pdf/gambar). */
    public function extension(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    /** Bisa ditampilkan inline di browser (PDF/gambar). */
    public function isInlineViewable(): bool
    {
        return in_array($this->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true);
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
