<?php

namespace App\Models;

use Database\Factories\MonthPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $budget_id
 * @property int $month
 * @property Carbon $approved_at
 * @property int $approved_set_aside_minor_units
 * @property int $current_set_aside_minor_units
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['budget_id', 'month', 'approved_at', 'approved_set_aside_minor_units', 'current_set_aside_minor_units'])]
class MonthPlan extends Model
{
    /** @use HasFactory<MonthPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'approved_at' => 'datetime',
            'approved_set_aside_minor_units' => 'integer',
            'current_set_aside_minor_units' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return HasMany<MonthPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MonthPlanItem::class);
    }
}
