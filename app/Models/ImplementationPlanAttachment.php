<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Satu berkas lampiran pada sebuah Implementation Plan (bisa lebih dari satu per activity). */
class ImplementationPlanAttachment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'implementation_plan_id', 'file_name', 'file_path', 'file_size', 'uploaded_by',
    ];

    /** Kolom ber-ID user, agar nama pengunggah ikut di-snapshot ke audit trail. */
    protected function activityUserFields(): array
    {
        return ['uploaded_by'];
    }

    public function activityLabel(): string
    {
        return $this->file_name . ' (plan #' . $this->implementation_plan_id . ')';
    }

    public function plan()
    {
        return $this->belongsTo(ImplementationPlan::class, 'implementation_plan_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Ekstensi huruf kecil, dipakai memilih ikon & menentukan bisa dibuka inline atau tidak. */
    public function extension(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    /** Bisa ditampilkan langsung di tab browser (bukan dipaksa unduh)? */
    public function isInlineViewable(): bool
    {
        return in_array($this->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true);
    }
}
