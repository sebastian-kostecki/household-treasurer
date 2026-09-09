<?php

namespace App\Models;

use App\Enums\CapitalMovementDirection;
use Database\Factories\CapitalMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int $term_deposit_id
 * @property Carbon $movement_date
 * @property int $amount_minor_units
 * @property CapitalMovementDirection $direction
 * @property string $description
 * @property string|null $raw_statement_description
 * @property int|null $import_identity_account_id
 * @property Carbon|null $import_identity_date
 * @property int|null $import_identity_amount_minor_units
 * @property string|null $import_identity_digest
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'account_id',
    'term_deposit_id',
    'movement_date',
    'amount_minor_units',
    'direction',
    'description',
    'raw_statement_description',
    'import_identity_account_id',
    'import_identity_date',
    'import_identity_amount_minor_units',
    'import_identity_digest',
])]
class CapitalMovement extends Model
{
    /** @use HasFactory<CapitalMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'amount_minor_units' => 'integer',
            'direction' => CapitalMovementDirection::class,
            'import_identity_date' => 'date',
            'import_identity_amount_minor_units' => 'integer',
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
     * @return BelongsTo<TermDeposit, $this>
     */
    public function termDeposit(): BelongsTo
    {
        return $this->belongsTo(TermDeposit::class);
    }
}
