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

    // Paksa koneksi tm_system (default) — lihat App\Models\Role. Tanpa ini, pivot
    // model_has_permissions bisa terwarisi koneksi hcis dari model User.
    protected $connection = 'mysql';

    // Ditangani sepenuhnya oleh Spatie.
}
