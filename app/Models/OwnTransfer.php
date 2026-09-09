<?php

namespace App\Models;

use Database\Factories\OwnTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $source_account_id
 * @property int $destination_account_id
 * @property Carbon $transfer_date
 * @property int $amount_minor_units
 * @property string $description
 * @property string|null $source_raw_statement_description
 * @property string|null $destination_raw_statement_description
 * @property int|null $source_import_identity_account_id
 * @property Carbon|null $source_import_identity_date
 * @property int|null $source_import_identity_amount_minor_units
 * @property string|null $source_import_identity_digest
 * @property int|null $destination_import_identity_account_id
 * @property Carbon|null $destination_import_identity_date
 * @property int|null $destination_import_identity_amount_minor_units
 * @property string|null $destination_import_identity_digest
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'source_account_id',
    'destination_account_id',
    'transfer_date',
    'amount_minor_units',
    'description',
    'source_raw_statement_description',
    'destination_raw_statement_description',
    'source_import_identity_account_id',
    'source_import_identity_date',
    'source_import_identity_amount_minor_units',
    'source_import_identity_digest',
    'destination_import_identity_account_id',
    'destination_import_identity_date',
    'destination_import_identity_amount_minor_units',
    'destination_import_identity_digest',
])]
class OwnTransfer extends Model
{
    /** @use HasFactory<OwnTransferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'amount_minor_units' => 'integer',
            'source_import_identity_date' => 'date',
            'source_import_identity_amount_minor_units' => 'integer',
            'destination_import_identity_date' => 'date',
            'destination_import_identity_amount_minor_units' => 'integer',
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
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }
}
