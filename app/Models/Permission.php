<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    // Paksa koneksi tm_system (default) — lihat App\Models\Role. Tanpa ini, pivot
    // model_has_permissions bisa terwarisi koneksi hcis dari model User.
    protected $connection = 'mysql';

    // Ditangani sepenuhnya oleh Spatie.
}
