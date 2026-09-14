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
 * @property int $amount_minor_units
 * @property string $description
 * @property string|null $counterparty
 * @property string|null $raw_statement_description
 * @property int|null $import_identity_account_id
 * @property Carbon|null $import_identity_date
 * @property int|null $import_identity_amount_minor_units
 * @property string|null $import_identity_digest
 * @property Carbon|null $settlement_attached_at
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
    'amount_minor_units',
    'description',
    'counterparty',
    'raw_statement_description',
    'import_identity_account_id',
    'import_identity_date',
    'import_identity_amount_minor_units',
    'import_identity_digest',
    'settlement_attached_at',
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
            'amount_minor_units' => 'integer',
            'import_identity_date' => 'date',
            'import_identity_amount_minor_units' => 'integer',
            'settlement_attached_at' => 'datetime',
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
