<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'route_name',
        'icon',
        'parent_id',
        'sort_order',
        'is_active'
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_menus'
        );
    }
}