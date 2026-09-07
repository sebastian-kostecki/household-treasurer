<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property Carbon $opening_date
 * @property int $opening_balance_grosze
 * @property int $current_balance_grosze
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'type',
    'opening_date',
    'opening_balance_grosze',
    'current_balance_grosze',
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
            'opening_date' => 'date',
            'opening_balance_grosze' => 'integer',
            'current_balance_grosze' => 'integer',
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

    public function hasFinancialHistory(): bool
    {
        return $this->outgoingOwnTransfers()->exists()
            || $this->incomingOwnTransfers()->exists();
    }

    public function isNegative(): bool
    {
        return $this->current_balance_grosze < 0;
    }
}
