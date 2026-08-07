<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Department dari database hcis (kpncorp) — tabel departments.
 * parent_company_id = nama_bisnis (Business Unit). Read-only.
 */
class KpnDepartment extends Model
{
    protected $connection = 'kpncorp';
    protected $table = 'departments';

    /**
     * Alias nama BU dari master_bisnisunits → parent_company_id di departments.
     * (mis. "Plantations" di master = "KPN Plantations" di departments).
     */
    public const BU_ALIAS = [
        'Plantations' => 'KPN Plantations',
    ];

    /** Daftar department_name untuk satu business unit (parent_company_id), terurut & unik. */
    public static function namesFor(string $businessUnit): \Illuminate\Support\Collection
    {
        $parent = self::BU_ALIAS[$businessUnit] ?? $businessUnit;

        return static::query()
            ->where('parent_company_id', $parent)
            ->whereNotNull('department_name')
            ->where('department_name', '!=', '')
            ->where('department_name', '!=', '-')
            ->orderBy('department_name')
            ->pluck('department_name')
            ->unique()
            ->values();
    }
}
