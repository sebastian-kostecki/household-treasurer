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
 * Set-aside (S-02) will enter the number here, so every surface that shows
 * "to spend" reads it from this class.
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
        if ($this->reading->state === 'none') {
            return null;
        }

        $items = [];

        foreach ($this->categories as $category) {
            $items[] = [
                'type' => $category->type,
                'kind' => $category->month_item_kind,
                'plan' => $this->reading->amounts[$category->id] ?? 0,
                'actual' => $this->actuals->actual($category->id),
                'has_positive_amount' => $this->actuals->hasPositiveAmount($category->id),
            ];
        }

        return ToSpendCalculator::toSpendMinorUnits($items);
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
