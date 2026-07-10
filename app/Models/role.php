<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_roles'
        );
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions'
        );
    }

    public function widgets()
    {
        return $this->belongsToMany(
            Widget::class,
            'role_widgets'
        );
    }

    public function menus()
    {
        return $this->belongsToMany(
            Menu::class,
            'role_menus'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Restriction (data scope)
    |--------------------------------------------------------------------------
    | Kosong = tanpa pembatasan (akses semua).
    */

    public function businessUnits()
    {
        return $this->belongsToMany(
            BusinessUnit::class,
            'role_business_units'
        );
    }

    public function companies()
    {
        return $this->belongsToMany(
            Company::class,
            'role_companies'
        );
    }

    public function locations()
    {
        return $this->belongsToMany(
            Location::class,
            'role_locations'
        );
    }

    public function employees()
    {
        return $this->belongsToMany(
            User::class,
            'role_employees'
        );
    }
}