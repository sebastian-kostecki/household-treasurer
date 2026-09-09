<?php

namespace App\Models;

use App\Enums\BudgetPhase;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $year
 * @property BudgetPhase $phase
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'year', 'phase'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'phase' => BudgetPhase::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<FinancialOperation, $this>
     */
    public function financialOperations(): HasMany
    {
        return $this->hasMany(FinancialOperation::class);
    }

    public function isDraft(): bool
    {
        return $this->phase === BudgetPhase::Draft;
    }

    public function isExecution(): bool
    {
        return $this->phase === BudgetPhase::Execution;
    }
}
