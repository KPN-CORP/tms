<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class KpnUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'kpncorp';
    protected $table = 'users';
    

    protected $hidden = [
        'password',
        'remember_token',
        'token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    /** Data kepegawaian (hcis employees) untuk user ini. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(KpnEmployee::class, 'employee_id', 'employee_id');
    }
}
