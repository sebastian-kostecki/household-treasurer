<?php

namespace App\Models;

use Database\Factories\MonthPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $month_plan_id
 * @property int $category_id
 * @property int $approved_amount_minor_units
 * @property int $current_amount_minor_units
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'month_plan_id',
    'category_id',
    'approved_amount_minor_units',
    'current_amount_minor_units',
])]
class MonthPlanItem extends Model
{
    /** @use HasFactory<MonthPlanItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_amount_minor_units' => 'integer',
            'current_amount_minor_units' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MonthPlan, $this>
     */
    public function monthPlan(): BelongsTo
    {
        return $this->belongsTo(MonthPlan::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
