<?php

namespace App\Models;

use Database\Factories\BalanceReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The User compared an Account with its bank on a day (ADR-0091). Whether it agrees is read
 * through `App\Support\AccountBalanceOnDate`, never stored.
 *
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property Carbon $reconciled_on
 * @property int $bank_balance_minor_units
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'account_id',
    'reconciled_on',
    'bank_balance_minor_units',
])]
class BalanceReconciliation extends Model
{
    /** @use HasFactory<BalanceReconciliationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reconciled_on' => 'date',
            'bank_balance_minor_units' => 'integer',
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
}
