<?php

namespace App\Models;

use App\Enums\FinancialOperationType;
use Database\Factories\FinancialOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property FinancialOperationType $type
 * @property int $account_id
 * @property int $category_id
 * @property int $budget_id
 * @property Carbon $operation_date
 * @property int $amount_grosze
 * @property string $description
 * @property string|null $counterparty
 * @property string|null $raw_statement_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'type',
    'account_id',
    'category_id',
    'budget_id',
    'operation_date',
    'amount_grosze',
    'description',
    'counterparty',
    'raw_statement_description',
])]
class FinancialOperation extends Model
{
    /** @use HasFactory<FinancialOperationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FinancialOperationType::class,
            'operation_date' => 'date',
            'amount_grosze' => 'integer',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
