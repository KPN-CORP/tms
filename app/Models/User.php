<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $connection = 'kpncorp';
    protected $table = 'users';

    protected static function booted(): void
    {
        $block = fn () => false; // return false membatalkan operasi → tak ada query tulis
        static::saving($block);   // mencakup creating + updating
        static::deleting($block);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function businessUnit()
    {
        return $this->belongsTo(KpnBusinessUnit::class);
    }

    public function department()
    {
        return $this->belongsTo(KpnDepartment::class);
    }

    public function ideas()
    {
        return $this->hasMany(Idea::class);
    }

    /** Label untuk audit trail (LogsActivity). */
    public function activityLabel(): string
    {
        return $this->name;
    }

    public function homeRoute(): string
    {
        return $this->hasAnyRole(['Admin', 'Super Admin']) ? 'dashboard' : 'ideas.index';
    }


    /** Data kepegawaian (hcis employees) untuk user ini. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(KpnEmployee::class, 'employee_id', 'employee_id');
    }


}
