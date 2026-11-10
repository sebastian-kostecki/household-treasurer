<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * "Edytuj kategorię" on the Budget page (ADR-0063): name, icon, colour and type
 * go to the list Category and so to every year; the plan and the flags go to
 * the Budget Line of the viewed Budget.
 */
class UpdateCategory
{
    public function __construct(private readonly UpdateListCategory $updateListCategory) {}

    /**
     * @param  array{name: string, icon: string, color: string, type: string, planned_amount_minor_units: int, is_paced?: bool, month_item_kind?: string}  $attributes
     */
    public function handle(BudgetLine $line, array $attributes): void
    {
        DB::transaction(function () use ($line, $attributes): void {
            $this->updateListCategory->handle($line->category, $attributes);

            $lineAttributes = ['planned_amount_minor_units' => $attributes['planned_amount_minor_units']];

            if ($attributes['type'] === CategoryType::Expense->value && ! $line->isArchived()) {
                foreach (['is_paced', 'month_item_kind'] as $flag) {
                    if (array_key_exists($flag, $attributes)) {
                        $lineAttributes[$flag] = $attributes[$flag];
                    }
                }
            }

            $line->update($lineAttributes);
        });
    }
}
