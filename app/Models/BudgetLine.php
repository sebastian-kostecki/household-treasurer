<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Category in one Budget (ADR-0063): the year's Initial Plan, Current Plan
 * and Execution. The description (name, icon, colour, sort order, type) is on
 * the list `category` and is always loaded with the line.
 *
 * @property int $id
 * @property int $budget_id
 * @property int $category_id
 * @property Category $category
 * @property int $planned_amount_minor_units
 * @property int $initial_plan_minor_units
 * @property int $execution_minor_units
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'budget_id',
    'category_id',
    'planned_amount_minor_units',
    'initial_plan_minor_units',
    'execution_minor_units',
    'archived_at',
])]
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory;

    /**
     * The description of a line is read from its Category, so the Category
     * always comes with it (one query per collection, no N+1).
     *
     * @var list<string>
     */
    protected $with = ['category'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function hasType(CategoryType $type): bool
    {
        return $this->category->type === $type;
    }

    /**
     * @param  Builder<BudgetLine>  $query
     * @return Builder<BudgetLine>
     */
    public function scopeOfType(Builder $query, CategoryType $type): Builder
    {
        return $query->whereHas('category', fn (Builder $category) => $category->where('type', $type->value));
    }

    /**
     * Lines open for choice: neither the line nor its list Category is
     * archived (ADR-0063, „Global archive”: the list archive decides choice).
     *
     * @param  Builder<BudgetLine>  $query
     * @return Builder<BudgetLine>
     */
    public function scopeOpenForChoice(Builder $query): Builder
    {
        return $query
            ->whereNull('budget_lines.archived_at')
            ->whereHas('category', fn (Builder $category) => $category->whereNull('archived_at'));
    }

    /**
     * Order of ADR-0063: the list order of the Category, then its name
     * key, then the line id; archived lines last, as before.
     *
     * @param  Builder<BudgetLine>  $query
     * @return Builder<BudgetLine>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        $categoryColumn = fn (string $column) => Category::query()
            ->select($column)
            ->whereColumn('categories.id', 'budget_lines.category_id');

        return $query
            ->orderByRaw('CASE WHEN budget_lines.archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy($categoryColumn('sort_order'))
            ->orderBy($categoryColumn('name_key'))
            ->orderBy('budget_lines.id');
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<FinancialOperation, $this>
     */
    public function financialOperations(): HasMany
    {
        return $this->hasMany(FinancialOperation::class, 'budget_line_id');
    }
}
