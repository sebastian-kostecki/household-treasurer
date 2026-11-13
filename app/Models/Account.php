<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Bank;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property AccountType $type
 * @property Bank|null $bank
 * @property Carbon $opening_date
 * @property int $opening_balance_minor_units
 * @property int $current_balance_minor_units
 * @property int|null $sheet_year
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'type',
    'bank',
    'opening_date',
    'opening_balance_minor_units',
    'current_balance_minor_units',
    'archived_at',
    'sheet_year',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'bank' => Bank::class,
            'opening_date' => 'date',
            'opening_balance_minor_units' => 'integer',
            'current_balance_minor_units' => 'integer',
            'archived_at' => 'datetime',
            'sheet_year' => 'integer',
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
     * @return HasMany<FinancialOperation, $this>
     */
    public function financialOperations(): HasMany
    {
        return $this->hasMany(FinancialOperation::class);
    }

    /**
     * @return HasMany<OwnTransfer, $this>
     */
    public function outgoingOwnTransfers(): HasMany
    {
        return $this->hasMany(OwnTransfer::class, 'source_account_id');
    }

    /**
     * @return HasMany<OwnTransfer, $this>
     */
    public function incomingOwnTransfers(): HasMany
    {
        return $this->hasMany(OwnTransfer::class, 'destination_account_id');
    }

    /**
     * @return HasMany<CapitalMovement, $this>
     */
    public function capitalMovements(): HasMany
    {
        return $this->hasMany(CapitalMovement::class);
    }

    /**
     * @return HasMany<BalanceReconciliation, $this>
     */
    public function balanceReconciliations(): HasMany
    {
        return $this->hasMany(BalanceReconciliation::class);
    }

    public function hasFinancialHistory(): bool
    {
        return $this->financialOperations()->exists()
            || $this->outgoingOwnTransfers()->exists()
            || $this->incomingOwnTransfers()->exists()
            || $this->capitalMovements()->exists();
    }

    public function earliestActivityDate(): ?Carbon
    {
        $dates = array_filter([
            $this->financialOperations()->min('operation_date'),
            $this->outgoingOwnTransfers()->min('transfer_date'),
            $this->incomingOwnTransfers()->min('transfer_date'),
            $this->capitalMovements()->min('movement_date'),
        ]);

        if ($dates === []) {
            return null;
        }

        return Carbon::parse(min($dates))->startOfDay();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * The archived Account of a year loaded from a sheet (ADR-0085).
     */
    public function isSheetAccount(): bool
    {
        return $this->sheet_year !== null;
    }

    /**
     * Accounts that are not Sheet Accounts.
     *
     * @param  Builder<Account>  $query
     * @return Builder<Account>
     */
    public function scopeWithoutSheetAccounts(Builder $query): Builder
    {
        return $query->whereNull('sheet_year');
    }

    public function isNegative(): bool
    {
        return $this->current_balance_minor_units < 0;
    }
}
