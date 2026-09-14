<?php

namespace App\Models;

use App\Enums\FinancialOperationType;
use Database\Factories\CashExpectationFactory;
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
 * @property Carbon $expectation_date
 * @property int $amount_minor_units
 * @property string $description
 * @property string|null $counterparty
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'type',
    'account_id',
    'category_id',
    'expectation_date',
    'amount_minor_units',
    'description',
    'counterparty',
])]
class CashExpectation extends Model
{
    /** @use HasFactory<CashExpectationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FinancialOperationType::class,
            'expectation_date' => 'date',
            'amount_minor_units' => 'integer',
        ];
    }

    public function isExpenseRefund(): bool
    {
        return $this->type === FinancialOperationType::Expense
            && $this->amount_minor_units < 0;
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
}
