<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Support\CategoryList\CategoryNameKey;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An income or expense bucket of the User, one for all years (ADR-0063). The
 * year's plan and Execution are on its Budget Lines.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $name_key
 * @property string $icon
 * @property string $color
 * @property string|null $description
 * @property int $sort_order
 * @property CategoryType $type
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'name_key',
    'icon',
    'color',
    'description',
    'sort_order',
    'type',
    'archived_at',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if ($category->isDirty('name') && ! $category->isDirty('name_key')) {
                $category->name_key = CategoryNameKey::of($category->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
