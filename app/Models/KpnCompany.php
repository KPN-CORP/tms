<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Company dari hcis (kpncorp) — tabel companies.
 * company_name berupa daftar BU dipisah koma (mis. "KPN Corporation,Downstream"),
 * jadi dicocokkan dengan LIKE. Tampilkan `contribution_level`. Read-only.
 */
class KpnCompany extends Model
{
    protected $connection = 'kpncorp';
    protected $table = 'companies';

    /** contribution_level untuk perusahaan yang company_name-nya memuat BU terpilih. */
    public static function contributionsFor(string $businessUnit): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('company_name', 'like', '%' . $businessUnit . '%')
            ->whereNotNull('contribution_level')
            ->where('contribution_level', '!=', '')
            ->orderBy('contribution_level')
            ->pluck('contribution_level')
            ->unique()
            ->values();
    }
}
