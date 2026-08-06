<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;


    /** Ladder job level: angka besar = jabatan lebih tinggi (T-1/12/71/86). */
    public const JOB_LEVELS = [
        1 => 'Staff',
        2 => 'Senior Staff',
        3 => 'Supervisor',
        4 => 'Assistant Manager',
        5 => 'Manager',
        6 => 'Senior Manager',
        7 => 'General Manager',
        8 => 'Director',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'business_unit_id',
        'department_id',
        'job_level',
        'summary',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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


    // roles(), hasRole(), hasPermissionTo(), can(), assignRole(), dll
    // disediakan oleh trait Spatie HasRoles.

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function ideas()
    {
        return $this->hasMany(Idea::class);
    }

    /** Label job level, mis. "5 — Manager". */
    public function getJobLevelLabelAttribute(): ?string
    {
        if ($this->job_level === null) {
            return null;
        }

        return $this->job_level . ' — ' . (self::JOB_LEVELS[$this->job_level] ?? 'Unknown');
    }

    /** Label untuk audit trail (LogsActivity). */
    public function activityLabel(): string
    {
        return $this->name;
    }
}
