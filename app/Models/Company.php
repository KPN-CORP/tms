<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_unit_id',
        'name',
        'code'
    ];

    public function businessUnit()
    {
        return $this->belongsTo(
            BusinessUnit::class
        );
    }

    public function locations()
    {
        return $this->hasMany(
            Location::class
        );
    }
}