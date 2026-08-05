<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Location dari hcis (kpncorp) — tabel locations.
 * company_name = nama_bisnis (Business Unit). Tampilkan `area`. Read-only.
 */
class KpnLocation extends Model
{
    protected $connection = 'kpncorp';
    protected $table = 'locations';

    /** Daftar area untuk sebuah Business Unit (company_name = nama_bisnis), unik & terurut. */
    public static function areasFor(string $businessUnit): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('company_name', $businessUnit)
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->orderBy('area')
            ->pluck('area')
            ->unique()
            ->values();
    }
}
