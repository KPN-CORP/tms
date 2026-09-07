<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pesan notifikasi email terjadwal untuk sebuah SLA.
 * Dikelola di Admin Setting > SLA > Email Notifications.
 */
class EmailNotificationSchedule extends Model
{
    // Sama seperti model admin lain: paksa koneksi aplikasi, bukan hcis.
    protected $connection = 'mysql';

    protected $fillable = [
        'sla_setting_id', 'title', 'business_units', 'units', 'companies', 'locations',
        'job_levels', 'start_date', 'end_date', 'attach_detail', 'repeat_days', 'message',
    ];

    protected $casts = [
        'business_units' => 'array',
        'units'          => 'array',
        'companies'      => 'array',
        'locations'      => 'array',
        'job_levels'     => 'array',
        'repeat_days'    => 'array',
        'attach_detail'  => 'boolean',
        'start_date'     => 'date',
        'end_date'       => 'date',
    ];

    /** Hari untuk "Repeat On" — kunci disimpan di kolom repeat_days. */
    public const DAYS = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu',
        'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];

    public function slaSetting(): BelongsTo
    {
        return $this->belongsTo(SlaSetting::class);
    }
}
