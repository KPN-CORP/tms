<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCategory extends Model
{
    protected $fillable = [
        'name', 'code', 'description',
        'leader_grade_min', 'leader_grade_max',
        'sponsor_grade_min', 'sponsor_grade_max',
        'max_team_members', 'is_active',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'leader_grade_min'  => 'integer',
        'leader_grade_max'  => 'integer',
        'sponsor_grade_min' => 'integer',
        'sponsor_grade_max' => 'integer',
        'max_team_members'  => 'integer',
    ];

    /** Job level $level memenuhi syarat sebagai Leader kategori ini? (batas kosong = tak dibatasi) */
    public function eligibleAsLeader(?int $level): bool
    {
        return $this->withinRange($level, $this->leader_grade_min, $this->leader_grade_max);
    }

    /** Job level $level memenuhi syarat sebagai Sponsor kategori ini? */
    public function eligibleAsSponsor(?int $level): bool
    {
        return $this->withinRange($level, $this->sponsor_grade_min, $this->sponsor_grade_max);
    }

    /** Teks syarat grade untuk ditampilkan, mis. "Grade 3–5". */
    public function gradeRangeText(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return 'Semua grade';
        }

        return 'Grade ' . ($min ?? '≤') . '–' . ($max ?? '≥');
    }

    private function withinRange(?int $level, ?int $min, ?int $max): bool
    {
        if ($min === null && $max === null) {
            return true; // kategori tanpa batas grade
        }
        if ($level === null) {
            return false; // user tanpa job level tak lolos kategori yang membatasi grade
        }

        return ($min === null || $level >= $min) && ($max === null || $level <= $max);
    }
}
