<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Employee dari hcis (kpncorp) — tabel employees. Read-only.
 * Business Unit = group_company; Department = unit (tanpa bagian dalam kurung).
 */
class KpnEmployee extends Model
{
    protected $connection = 'kpncorp';
    protected $table = 'employees';

    /** Ambil record employee berdasarkan email (null bila tak ada). */
    public static function forEmail(?string $email): ?self
    {
        $email = trim((string) $email);

        return $email === '' ? null : static::where('email', $email)->first();
    }

    /** Business Unit = field group_company. */
    public function businessUnitName(): ?string
    {
        $bu = trim((string) $this->group_company);

        return $bu !== '' ? $bu : null;
    }

    /** Department = field unit, dibuang bagian dalam kurung. Mis. "HC Information System (CRPHC_ISD)" → "HC Information System". */
    public function departmentName(): ?string
    {
        return self::stripUnit($this->unit);
    }

    /** Buang keterangan dalam kurung dari sebuah unit. */
    public static function stripUnit(?string $unit): ?string
    {
        $clean = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', (string) $unit));

        return $clean !== '' ? $clean : null;
    }

    /** Daftar Unit/Department (tanpa kurung) untuk sebuah Business Unit (group_company). */
    /**
     * Daftar Job Level unik (1A, 2A, ... 10B) untuk dropdown Filter Job Level.
     * Diurut angka dulu baru hurufnya supaya 10A tidak mendahului 2A.
     */
    public static function jobLevels(): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereNull('deleted_at')
            ->whereNotNull('job_level')->where('job_level', '!=', '')
            ->distinct()
            ->pluck('job_level')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->sortBy(fn ($v) => [(int) preg_replace('/\D/', '', $v) ?: 999, $v], SORT_REGULAR)
            ->values();
    }

    public static function unitsFor(string $businessUnit): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('group_company', $businessUnit)
            ->whereNull('deleted_at')
            ->whereNotNull('unit')->where('unit', '!=', '')
            ->pluck('unit')
            ->map(fn ($u) => self::stripUnit($u))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Employee (fullname + email) untuk dropdown approver:
     * difilter Business Unit (group_company) dan opsional Unit/Department.
     */
    public static function forSelection(string $businessUnit, ?string $unit = null): \Illuminate\Support\Collection
    {
        $q = static::query()
            ->where('group_company', $businessUnit)
            ->whereNull('deleted_at')
            ->whereNotNull('email')->where('email', '!=', '');

        if ($unit !== null && trim($unit) !== '') {
            $u = trim($unit);
            $q->where(fn ($w) => $w->where('unit', $u)->orWhere('unit', 'like', $u . ' (%'));
        }

        return $q->orderBy('fullname')
            ->get(['employee_id', 'fullname', 'email', 'job_level', 'unit'])
            ->unique('email')
            ->values();
    }

        public function grade(): ?int
    {
        if (empty($this->job_level)) {
            return null;
        }

        preg_match('/^\d+/', $this->job_level, $match);

        return isset($match[0]) ? (int) $match[0] : null;
    }

    /** Label dropdown: "Fullname - Designation - EmployeeID". */
    public function label(): string
    {
        return trim(collect([
            $this->fullname,
            $this->designation_name ?: $this->designation,
            $this->employee_id,
        ])->filter()->implode(' - '));
    }

    /**
     * Cari employee lintas BU (untuk dropdown searchable AJAX committee).
     * Match fullname / employee_id / designation / email.
     */
    public static function search(string $q, int $limit = 30): \Illuminate\Support\Collection
    {
        $q = trim($q);

        return static::query()
            ->whereNull('deleted_at')
            ->whereNotNull('email')->where('email', '!=', '')
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                $x->where('fullname', 'like', "%{$q}%")
                    ->orWhere('employee_id', 'like', "%{$q}%")
                    ->orWhere('designation_name', 'like', "%{$q}%")
                    ->orWhere('designation', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('fullname')
            ->limit($limit)
            ->get(['employee_id', 'fullname', 'email', 'designation', 'designation_name'])
            ->unique('email')
            ->values();
    }
}
