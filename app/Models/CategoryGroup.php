<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Support\CategoryList\CategoryNameKey;
use Database\Factories\CategoryGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A named group of Categories of one type, across all years (ADR-0063). It has
 * no plan and no settings; it stands in the one order of its type, shared with
 * the Categories (ADR-0069). Its type is fixed when it is created.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $name_key
 * @property CategoryType $type
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'name_key',
    'type',
    'sort_order',
])]
class CategoryGroup extends Model
{
    /** @use HasFactory<CategoryGroupFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (CategoryGroup $group): void {
            if ($group->isDirty('name') && ! $group->isDirty('name_key')) {
                $group->name_key = CategoryNameKey::of($group->name);
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
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
