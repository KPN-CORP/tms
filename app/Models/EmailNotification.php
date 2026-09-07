<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu event notifikasi email: kapan dikirim (is_active), ke siapa (recipients),
 * dan isinya (subject + body). Dikelola di Admin Setting > SLA > Email Notifications.
 */
class EmailNotification extends Model
{
    // Sama seperti model admin lain: paksa koneksi aplikasi, bukan hcis.
    protected $connection = 'mysql';

    protected $fillable = ['event_key', 'category', 'name', 'description', 'recipients', 'subject', 'body', 'is_active'];

    protected $casts = [
        'recipients' => 'array',
        'is_active'  => 'boolean',
    ];

    /** Pilihan penerima. Kunci disimpan di kolom recipients (JSON). */
    public const RECIPIENTS = [
        'submitter'        => 'Submitter',
        'current_approver' => 'Current Approver',
        'committee'        => 'Committee',
        'project_leader'   => 'Project Leader',
        'project_sponsor'  => 'Project Sponsor',
        'admin'            => 'Admin',
    ];

    /** Placeholder yang boleh dipakai di Subject & Body. */
    public const PLACEHOLDERS = [
        '{recipient_name}' => 'Nama penerima email',
        '{actor_name}'     => 'Nama pelaku aksi (pengaju/approver)',
        '{record_id}'      => 'ID Idea / Project',
        '{record_name}'    => 'Judul Idea / nama Project',
        '{status}'         => 'Status terkini',
        '{due_date}'       => 'Batas waktu dari SLA Setting',
        '{link}'           => 'Tautan langsung ke halaman terkait',
    ];

    /** Label penerima untuk ditampilkan di tabel. */
    public function recipientLabels(): array
    {
        return array_values(array_map(
            fn ($k) => self::RECIPIENTS[$k] ?? $k,
            $this->recipients ?? []
        ));
    }
}
