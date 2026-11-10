<?php

namespace App\Models;

use App\Enums\Rhythm;
use Database\Factories\CategoryRhythmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of the Rhythm history of a list Category: the Rhythm and the
 * month it starts in (ADR-0066, ADR-0072).
 *
 * @property int $id
 * @property int $category_id
 * @property int $start_year
 * @property int $start_month
 * @property Rhythm $rhythm
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'category_id',
    'start_year',
    'start_month',
    'rhythm',
])]
class CategoryRhythm extends Model
{
    /** @use HasFactory<CategoryRhythmFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'start_month' => 'integer',
            'rhythm' => Rhythm::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
