<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Satu berkas lampiran pada sebuah baris Budget (boleh lebih dari satu). */
class ProjectBudgetAttachment extends Model
{
    use LogsActivity;

    protected $fillable = ['project_budget_id', 'file_name', 'file_path', 'file_size', 'uploaded_by'];

    protected function activityUserFields(): array
    {
        return ['uploaded_by'];
    }

    public function activityLabel(): string
    {
        return $this->file_name . ' (budget #' . $this->project_budget_id . ')';
    }

    public function budget()
    {
        return $this->belongsTo(ProjectBudget::class, 'project_budget_id');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    public function isInlineViewable(): bool
    {
        return in_array($this->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true);
    }
}
