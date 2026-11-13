<?php

namespace App\Actions;

use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Support\BudgetLineResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Moves an operation of a Sheet Account to another Category of its loaded
 * Budget (ADR-0089). The amount leaves the old Budget Line and enters the new
 * one in Execution, Current Plan and Initial Plan together, so a loaded line
 * keeps Plan = Execution; amount, date and Account of the operation and the
 * balance of the Sheet Account stay as loaded.
 */
class ChangeSheetOperationCategory
{
    public function __construct(
        private readonly BudgetLineResolver $resolver,
        private readonly JoinBudgetAtFirstUse $joinBudgetAtFirstUse,
    ) {}

    public function handle(FinancialOperation $operation, int $categoryId): FinancialOperation
    {
        return DB::transaction(function () use ($operation, $categoryId): FinancialOperation {
            $operation = FinancialOperation::query()
                ->with('budget')
                ->whereKey($operation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $target = $this->targetLine($operation, $categoryId);

            if ($target->id === $operation->budget_line_id) {
                return $operation;
            }

            $lines = BudgetLine::query()
                ->whereKey([$operation->budget_line_id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->shift($lines[$operation->budget_line_id], -$operation->amount_minor_units);
            $this->shift($lines[$target->id], $operation->amount_minor_units);

            $operation->update(['budget_line_id' => $target->id]);

            return $operation->refresh();
        });
    }

    /**
     * The line of the Category in the operation's Budget, joining it at the
     * first use; an archived line or Category takes no operation.
     */
    private function targetLine(FinancialOperation $operation, int $categoryId): BudgetLine
    {
        $line = $this->resolver->forBudget($operation->budget, $categoryId);

        if ($line === null) {
            $category = Category::query()->where('user_id', $operation->user_id)->findOrFail($categoryId);
            $line = $this->joinBudgetAtFirstUse->handle($operation->budget, $category);
        }

        if ($line->id !== $operation->budget_line_id && ($line->isArchived() || $line->category->isArchived())) {
            throw (new ModelNotFoundException)->setModel(BudgetLine::class, [$categoryId]);
        }

        return $line;
    }

    private function shift(BudgetLine $line, int $amountMinorUnits): void
    {
        $line->update([
            'execution_minor_units' => $line->execution_minor_units + $amountMinorUnits,
            'planned_amount_minor_units' => $line->planned_amount_minor_units + $amountMinorUnits,
            'initial_plan_minor_units' => $line->initial_plan_minor_units + $amountMinorUnits,
        ]);
    }
}
