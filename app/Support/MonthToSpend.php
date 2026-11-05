<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The shared reading of one month of a budget in execution: plan state, category
 * actuals, the rows' categories and the "to spend" number built from them.
 *
 * Set-aside enters the number here, so every surface that shows "to spend"
 * reads it from this class.
 */
final class MonthToSpend
{
    /**
     * @param  Collection<int, Category>  $categories  rows' categories in budget order
     */
    private function __construct(
        public readonly MonthPlanProposal $proposal,
        public readonly MonthPlanReading $reading,
        public readonly MonthCategoryActuals $actuals,
        public readonly Collection $categories,
    ) {}

    /**
     * Called only for budgets in execution.
     */
    public static function forMonth(Budget $budget, int $month, CarbonImmutable $today): self
    {
        $proposal = MonthPlanProposal::forBudget($budget, $today);
        $reading = $proposal->reading($month);
        $actuals = MonthCategoryActuals::forMonth($budget, $month);

        $rowIds = array_values(array_unique([
            ...array_keys($reading->amounts),
            ...$actuals->categoryIds(),
        ]));

        $categories = $budget->categories()
            ->whereIn('id', $rowIds)
            ->ordered()
            ->get();

        return new self($proposal, $reading, $actuals, $categories);
    }

    /**
     * Null when the month has no plan to read from.
     */
    public function toSpendMinorUnits(): ?int
    {
        return $this->toSpendBreakdown()['to_spend_minor_units'] ?? null;
    }

    /**
     * The income and expense behind the number. Null when the month has no plan.
     *
     * @return array{income_minor_units: int, expense_minor_units: int, to_spend_minor_units: int}|null
     */
    public function toSpendBreakdown(): ?array
    {
        if ($this->reading->state === 'none') {
            return null;
        }

        $items = [];

        foreach ($this->categories as $category) {
            $amounts = $this->rowAmounts($category);

            $items[] = [
                'type' => $category->type,
                'kind' => $category->month_item_kind,
                'plan' => $amounts['plan_minor_units'],
                'actual' => $amounts['actual_minor_units'],
                'has_positive_amount' => $amounts['has_positive_amount'],
            ];
        }

        return ToSpendCalculator::breakdown($items, $this->reading->setAsideMinorUnits);
    }

    /**
     * The amounts of one category row, shared by the number and the rows shown
     * beside it so the two cannot drift apart.
     *
     * @return array{plan_minor_units: int, actual_minor_units: int, has_positive_amount: bool}
     */
    public function rowAmounts(Category $category): array
    {
        return [
            'plan_minor_units' => $this->reading->amounts[$category->id] ?? 0,
            'actual_minor_units' => $this->actuals->actual($category->id),
            'has_positive_amount' => $this->actuals->hasPositiveAmount($category->id),
        ];
    }

    /**
     * @return array{state: 'proposal'|'approved'|'none', to_spend_minor_units: int|null}
     */
    public function summary(): array
    {
        return [
            'state' => $this->reading->state,
            'to_spend_minor_units' => $this->toSpendMinorUnits(),
        ];
    }
}
