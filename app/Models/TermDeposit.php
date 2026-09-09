<?php

namespace App\Models;

use Database\Factories\TermDepositFactory;
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
 * @property Carbon $opening_date
 * @property int $opening_balance_minor_units
 * @property int $current_balance_minor_units
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'opening_date',
    'opening_balance_minor_units',
    'current_balance_minor_units',
    'archived_at',
])]
class TermDeposit extends Model
{
    /** @use HasFactory<TermDepositFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'opening_balance_minor_units' => 'integer',
            'current_balance_minor_units' => 'integer',
            'archived_at' => 'datetime',
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
     * @return HasMany<CapitalMovement, $this>
     */
    public function capitalMovements(): HasMany
    {
        return $this->hasMany(CapitalMovement::class);
    }

    public function hasCapitalMovements(): bool
    {
        return $this->capitalMovements()->exists();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isNegative(): bool
    {
        return $this->current_balance_minor_units < 0;
    }
}
