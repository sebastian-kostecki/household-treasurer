<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Support\BudgetViewedMonth;
use App\Support\PeriodReport;
use App\Support\ReportCategoryPanel;
use App\Support\ReportComparisonPeriod;
use App\Support\ReportTrend;
use App\Support\ReportTwelveMonthWindow;
use App\Support\ReportYears;
use App\Support\ReportYearsRange;
use App\Support\SelectedBudgetYear;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request): Response
    {
        $selectedYear = SelectedBudgetYear::resolve($request);
        $budget = $selectedYear === null
            ? null
            : $request->user()->budgets()->where('year', $selectedYear)->first();

        if ($budget === null) {
            return Inertia::render('Reports', [
                'budgetYear' => null,
                'budgetPhase' => null,
                'period' => [
                    'kind' => 'year',
                    'month' => null,
                    'default_month' => 1,
                    'is_in_progress' => false,
                    'window' => null,
                ],
                'report' => null,
                'trend' => null,
                'years' => null,
                'rankingMode' => $this->rankingMode($request),
                'categoryPanel' => null,
            ]);
        }

        $this->authorize('view', $budget);

        $today = CarbonImmutable::today('UTC');

        if ($request->query('view') === 'last-12') {
            return $this->lastTwelveMonths($request, $budget, $today);
        }

        if ($request->query('view') === 'years') {
            return $this->years($request, $budget, $today);
        }

        $isTrend = $request->query('view') === 'trend';
        $month = $isTrend ? null : $this->requestedMonth($request->query('month'));

        $periodReport = $budget->isExecution() && ! $isTrend ? $this->periodReport($budget, $month, $today) : null;

        return Inertia::render('Reports', [
            'budgetYear' => $budget->year,
            'budgetPhase' => $budget->phase->value,
            'period' => [
                'kind' => $this->periodKind($isTrend, $month),
                'month' => $month,
                'default_month' => BudgetViewedMonth::defaultForYear($budget->year, $today),
                'is_in_progress' => ReportComparisonPeriod::isInProgress($budget->year, $month, $today),
                'window' => null,
            ],
            'report' => $periodReport?->toArray(),
            'trend' => $budget->isExecution() && $isTrend ? $this->trend($budget, $today) : null,
            'years' => null,
            'rankingMode' => $this->rankingMode($request),
            'categoryPanel' => $month === null && $periodReport?->toArray()['has_operations'] === true
                ? ReportCategoryPanel::forYear($budget, $periodReport, $request->query('category'), $today)->toArray()
                : null,
        ]);
    }

    /**
     * The Categories across the years of the Budgets in execution (ADR-0097):
     * independent of the phase of the selected Budget, which only keeps the
     * sidebar state.
     */
    private function years(Request $request, Budget $selected, CarbonImmutable $today): Response
    {
        return Inertia::render('Reports', [
            'budgetYear' => $selected->year,
            'budgetPhase' => $selected->phase->value,
            'period' => [
                'kind' => 'years',
                'month' => null,
                'default_month' => BudgetViewedMonth::defaultForYear($selected->year, $today),
                'is_in_progress' => false,
                'window' => null,
            ],
            'report' => null,
            'trend' => null,
            'years' => ReportYears::for(ReportYearsRange::resolve($request->user(), $today))->toArray(),
            'rankingMode' => $this->rankingMode($request),
            'categoryPanel' => null,
        ]);
    }

    /**
     * The last twelve closed months across Budgets (ADR-0081): independent of
     * the phase of the selected Budget, which only keeps the sidebar state.
     */
    private function lastTwelveMonths(Request $request, Budget $selected, CarbonImmutable $today): Response
    {
        $window = ReportTwelveMonthWindow::resolve($request->user(), $today);

        return Inertia::render('Reports', [
            'budgetYear' => $selected->year,
            'budgetPhase' => $selected->phase->value,
            'period' => [
                'kind' => 'last-12',
                'month' => null,
                'default_month' => BudgetViewedMonth::defaultForYear($selected->year, $today),
                'is_in_progress' => false,
                'window' => $window->toArray(),
            ],
            'report' => PeriodReport::forWindow($window)->toArray(),
            'trend' => null,
            'years' => null,
            'rankingMode' => $this->rankingMode($request),
            'categoryPanel' => null,
        ]);
    }

    private function periodKind(bool $isTrend, ?int $month): string
    {
        return match (true) {
            $isTrend => 'trend',
            $month === null => 'year',
            default => 'month',
        };
    }

    /**
     * A valid `?month` (1 to 12) selects a month; anything else selects the year.
     */
    private function requestedMonth(mixed $query): ?int
    {
        if (! is_string($query) || ! ctype_digit($query)) {
            return null;
        }

        $month = (int) $query;

        return $month >= 1 && $month <= 12 ? $month : null;
    }

    /**
     * The Ranking lists Categories unless `?ranking=groups` asks for Groups.
     */
    private function rankingMode(Request $request): string
    {
        return $request->query('ranking') === 'groups' ? 'groups' : 'categories';
    }

    private function periodReport(Budget $budget, ?int $month, CarbonImmutable $today): PeriodReport
    {
        $comparison = ReportComparisonPeriod::resolve($budget, $month, $today);

        return $month === null
            ? PeriodReport::forYear($budget, $comparison)
            : PeriodReport::forMonth($budget, $month, $comparison);
    }

    /**
     * @return array<string, mixed>
     */
    private function trend(Budget $budget, CarbonImmutable $today): array
    {
        $inProgressMonth = $budget->year === $today->year ? $today->month : null;

        return ReportTrend::forYear($budget, $inProgressMonth)->toArray();
    }
}
