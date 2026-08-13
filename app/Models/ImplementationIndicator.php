<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ImplementationIndicator extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->indicator ?? ('Indicator #' . $this->getKey());
    }

    public const TYPES = ['Higher Better', 'Lower Better'];

    protected $fillable = [
        'project_id', 'indicator', 'description', 'baseline', 'achievement',
        'uom', 'weightage', 'type', 'improvement', 'sort_order',
    ];

    protected $casts = [
        'baseline'    => 'decimal:2',
        'achievement' => 'decimal:2',
        'weightage'   => 'decimal:2',
        'improvement' => 'decimal:2',
    ];

    /**
     * % Improvement (T-43): dari baseline ke achievement, sesuai type.
     * Higher Better -> naik = positif; Lower Better -> turun = positif.
     */
    public static function calcImprovement(?float $baseline, ?float $achievement, ?string $type): ?float
    {
        if ($baseline === null || $achievement === null || ! $type || (float) $baseline == 0.0) {
            return null;
        }

        $delta = $type === 'Higher Better'
            ? ($achievement - $baseline)
            : ($baseline - $achievement);

        return round($delta / abs($baseline) * 100, 2);
    }
}
