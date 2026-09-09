<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $budget_id
 * @property string $name
 * @property string $icon
 * @property string $color
 * @property int $sort_order
 * @property CategoryType $type
 * @property int $planned_amount_minor_units
 * @property int $initial_plan_minor_units
 * @property int $execution_minor_units
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'budget_id',
    'name',
    'icon',
    'color',
    'sort_order',
    'type',
    'planned_amount_minor_units',
    'initial_plan_minor_units',
    'execution_minor_units',
    'archived_at',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'sort_order' => 'integer',
            'planned_amount_minor_units' => 'integer',
            'initial_plan_minor_units' => 'integer',
            'execution_minor_units' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return HasMany<FinancialOperation, $this>
     */
    public function financialOperations(): HasMany
    {
        return $this->hasMany(FinancialOperation::class);
    }
}
