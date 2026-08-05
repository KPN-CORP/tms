<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Business Unit dari database hcis (kpncorp) — tabel master_bisnisunits.
 * Sumber dropdown Business Unit (nama_bisnis). Read-only.
 */
class KpnBusinessUnit extends Model
{
    protected $connection = 'kpncorp';
    protected $table = 'master_bisnisunits';
    public $timestamps = false;

    /** Daftar nama business unit (nama_bisnis), terurut. */
    public static function names(): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereNotNull('nama_bisnis')
            ->where('nama_bisnis', '!=', '')
            ->orderBy('nama_bisnis')
            ->pluck('nama_bisnis')
            ->unique()
            ->values();
    }
}
