<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBudget extends Model
{
    protected $fillable = [
        'project_id', 'item', 'qty', 'uom', 'unit_price',
        'actual_qty', 'actual_price', 'actual_cost', 'remarks',
    ];

    /** Planning total (Qty x Unit Price). */
    public function getPlannedTotalAttribute(): float
    {
        return (float) $this->qty * (float) $this->unit_price;
    }
}
