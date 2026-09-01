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

    public const TYPES = ['Higher Better', 'Lower Better', 'Exact Value'];

    /** Pilihan Unit of Measure (dropdown UoM), dikelompokkan per kategori. */
    public const UOM_GROUPS = [
        'Time'             => ['Day (d)', 'Month', 'Year (yr)'],
        'Quantity'         => ['Unit', 'Number'],
        'Area'             => ['Hectare (ha)', 'Square Meter (m²)'],
        'Weight/Volume'    => ['Kilogram (kg)', 'Metric ton (t)', 'Liter (L)'],
        'Production Rate'  => ['Unit per Hour (unit/h)', 'Item per Minute (item/min)', 'Ton per Hour (t/h)'],
        'Currency'         => ['Rupiah (Rp)', 'Dollar ($)', 'Euro (€)', 'Pound Sterling (£)', 'Yen (¥)', 'Rupee (₹)'],
        'Speed'            => ['Kilometer per Hour (km/h)', 'Meter per Second (m/s)', 'Mile per Hour (mph)'],
        'Temperature'      => ['Degree Celsius (°C)', 'Degree Fahrenheit (°F)', 'Kelvin (K)'],
        'Energy'           => ['Kilowatt-hour (kWh)', 'Megajoule (MJ)', 'Kilojoule (kJ)', 'Joule (J)', 'Kilocalorie (kcal)', 'Calorie (cal)'],
        'Power'            => ['Kilowatt (kW)', 'Watt (W)', 'Horsepower (hp)'],
        'Frequency'        => ['Gigahertz (GHz)', 'Megahertz (MHz)', 'Kilohertz (kHz)', 'Hertz (Hz)'],
        'Fuel Consumption' => ['Kilometer per Liter (km/L)', 'Liter per 100 Kilometer (L/100km)'],
        'Storage Capacity' => ['Terabyte (TB)', 'Megabyte (MB)', 'Gigabyte (GB)', 'Kilobyte (KB)'],
        'Other'            => ['Percent (%)', 'Dollar per Year ($/year)', 'Dollar per Month ($/month)', 'Dollar per Unit ($/unit)', 'Rupiah per Year (Rp/year)', 'Rupiah per Month (Rp/month)', 'Rupiah per Unit ($/unit)', 'Event', 'Person'],
    ];

    /** Daftar UoM datar (untuk validasi Rule::in). */
    public static function uoms(): array
    {
        return array_merge(...array_values(self::UOM_GROUPS));
    }

    protected $fillable = [
        'project_id', 'indicator', 'description', 'baseline', 'achievement_value', 'achievement',
        'uom', 'weightage', 'type', 'improvement', 'sort_order',
    ];

    protected $casts = [
        'baseline'          => 'decimal:2',
        'achievement_value' => 'decimal:2', // TARGET
        'achievement'       => 'decimal:2', // aktual
        'weightage'         => 'decimal:2',
        'improvement'       => 'decimal:2',
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

        // Exact Value: yang dinilai adalah KEDEKATAN dengan baseline, bukan arah
        // naik/turun. Tepat sasaran = 100%, dan menurun seiring besarnya simpangan
        // (bisa negatif bila simpangannya melebihi baseline itu sendiri).
        if ($type === 'Exact Value') {
            return round(100 - abs($achievement - $baseline) / abs($baseline) * 100, 2);
        }

        $delta = $type === 'Higher Better'
            ? ($achievement - $baseline)
            : ($baseline - $achievement);

        return round($delta / abs($baseline) * 100, 2);
    }
}
