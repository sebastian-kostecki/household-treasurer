<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $first_run_checklist_dismissed_at
 * @property int|null $default_set_aside_percent
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'first_run_checklist_dismissed_at' => 'datetime',
            'default_set_aside_percent' => 'integer',
        ];
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<CategoryGroup, $this>
     */
    public function categoryGroups(): HasMany
    {
        return $this->hasMany(CategoryGroup::class);
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
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
    public function ownTransfers(): HasMany
    {
        return $this->hasMany(OwnTransfer::class);
    }

    /**
     * @return HasMany<TermDeposit, $this>
     */
    public function termDeposits(): HasMany
    {
        return $this->hasMany(TermDeposit::class);
    }

    /**
     * @return HasMany<CapitalMovement, $this>
     */
    public function capitalMovements(): HasMany
    {
        return $this->hasMany(CapitalMovement::class);
    }
}
