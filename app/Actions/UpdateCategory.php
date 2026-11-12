<?php

namespace App\Actions;

use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * "Edytuj kategorię" on the Budget page (ADR-0063): name, icon, colour and type
 * go to the list Category and so to every year; the plan goes to
 * the Budget Line of the viewed Budget.
 */
class UpdateCategory
{
    public function __construct(private readonly UpdateListCategory $updateListCategory) {}

    /**
     * @param  array{name: string, icon: string, color: string, type: string, planned_amount_minor_units: int, ...}  $attributes
     */
    public function handle(BudgetLine $line, array $attributes): void
    {
        DB::transaction(function () use ($line, $attributes): void {
            $this->updateListCategory->handle($line->category, $attributes);

            $line->update(['planned_amount_minor_units' => $attributes['planned_amount_minor_units']]);
        });
    }
}
