<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'view_path',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_widgets'
        );
    }
}