<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->name ?? ('Permission #' . $this->getKey());
    }

    /**
     * Nama untuk TAMPILAN saja, mis. "idea.approve" → "Approve Idea",
     * "project-category.manage" → "Manage Project Category".
     * TIDAK memengaruhi flow — value/validasi/Spatie tetap memakai `name` asli.
     */
    public function displayLabel(): string
    {
        $humanize = fn ($s) => ucwords(str_replace('-', ' ', (string) $s));
        $parts = explode('.', (string) $this->name, 2);

        return count($parts) === 2
            ? $humanize($parts[1]) . ' ' . $humanize($parts[0])   // aksi + kategori
            : $humanize($this->name);
    }

    // Paksa koneksi tm_system (default) — lihat App\Models\Role. Tanpa ini, pivot
    // model_has_permissions bisa terwarisi koneksi hcis dari model User.
    protected $connection = 'mysql';

    // Ditangani sepenuhnya oleh Spatie.
}
