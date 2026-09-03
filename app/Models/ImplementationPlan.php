<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ImplementationPlan extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->activity ?? ('Activity #' . $this->getKey());
    }

    protected $fillable = [
        'project_id', 'activity', 'planning_start', 'planning_end',
        'actual_start', 'actual_end', 'pic_user_ids', 'remarks', 'sequence_no',
        'attachment_path', 'attachment_name',
        'attachment_uploaded_by', 'attachment_uploaded_at', 'attachment_replace_count',
    ];

    protected $casts = [
        'planning_start' => 'date',
        'planning_end'   => 'date',
        'actual_start'   => 'date',
        'actual_end'     => 'date',
        'pic_user_ids'   => 'array',
        'attachment_uploaded_at' => 'datetime',
    ];

    /** Kolom ber-ID user pada plan: PIC (array) & pengunggah lampiran. */
    protected function activityUserFields(): array
    {
        return ['pic_user_ids', 'attachment_uploaded_by'];
    }

    /** Lampiran activity ini — bisa lebih dari satu berkas. */
    public function attachments()
    {
        return $this->hasMany(ImplementationPlanAttachment::class, 'implementation_plan_id')->orderBy('id');
    }

    /** User yang terakhir mengunggah / mengganti berkas plan ini. */
    public function attachmentUploader()
    {
        return $this->belongsTo(User::class, 'attachment_uploaded_by');
    }

    public function getPlanningDaysAttribute(): ?int
    {
        return $this->planning_start && $this->planning_end
            ? $this->planning_start->diffInDays($this->planning_end) + 1
            : null;
    }

    public function getActualDaysAttribute(): ?int
    {
        return $this->actual_start && $this->actual_end
            ? $this->actual_start->diffInDays($this->actual_end) + 1
            : null;
    }

    /** Status otomatis (T-63). */
    public function getStatusLabelAttribute(): string
    {
        // Belum ada Actual Start: dianggap sudah berjalan begitu tanggal hari ini
        // mencapai Planned Start Date, walau realisasinya belum diinput.
        if (! $this->actual_start) {
            return ($this->planning_start && now()->startOfDay()->gte($this->planning_start->startOfDay()))
                ? 'On Going'
                : 'Not Started';
        }

        // Terlambat MULAI: Actual Start melewati Planned Start Date.
        if ($this->planning_start && $this->actual_start->gt($this->planning_start)) {
            return 'Delayed';
        }

        if (! $this->actual_end) {
            // Mulai tepat waktu tapi sudah lewat Planned End tanpa selesai.
            return ($this->planning_end && now()->gt($this->planning_end)) ? 'Delayed' : 'On Going';
        }

        if ($this->planning_end && $this->actual_end->gt($this->planning_end)) {
            return 'Completed (Late)';
        }

        return 'Completed (On Time)';
    }

    /** User yang jadi PIC (multi-select). */
    public function pics()
    {
        return User::whereIn('id', $this->pic_user_ids ?? [])->get();
    }
}
