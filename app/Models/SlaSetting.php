<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * SLA per jenis approval. Label jenis mengikuti CommitteeAssignment::TYPES.
 */
class SlaSetting extends Model
{
    use LogsActivity;

    protected $fillable = ['approval_type', 'days', 'status', 'is_active'];

    protected $casts = [
        'days'      => 'integer',
        'is_active' => 'boolean',
    ];

    /** Opsi status (basis perhitungan SLA) untuk dropdown — value => label. */
    public const STATUS_OPTIONS = [
        'submitted' => 'Submitted',
        'review'    => 'On Review',
    ];

    /** Label status terpilih (mis. "On Review"), atau '-' bila kosong. */
    public function statusLabel(): string
    {
        return self::STATUS_OPTIONS[$this->status] ?? '-';
    }

    /** Jumlah hari SLA aktif untuk sebuah approval_type, atau null bila tak diatur/nonaktif. */
    public static function daysFor(string $approvalType): ?int
    {
        return static::query()
            ->where('approval_type', $approvalType)
            ->where('is_active', true)
            ->value('days');
    }

    public function typeLabel(): string
    {
        return CommitteeAssignment::TYPES[$this->approval_type] ?? $this->approval_type;
    }

    public function activityLabel(): string
    {
        return $this->typeLabel() . ' SLA';
    }
}
