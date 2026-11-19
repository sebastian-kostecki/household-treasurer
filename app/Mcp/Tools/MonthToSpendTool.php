<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Support\AppDay;
use App\Support\BudgetForYear;
use App\Support\CurrentMonthReading;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('To Spend ("do wydania") of the current month, with its income and expense breakdown and the state of the month plan (proposal or approved). Without a Budget in execution for the current year it says the year and the Budget phase and gives no To Spend.')]
#[IsReadOnly]
#[IsIdempotent]
class MonthToSpendTool extends Tool
{
    use AnswersForUser;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);
        $today = AppDay::today();

        $reading = CurrentMonthReading::forUser($user, $today);

        if ($reading === null) {
            return $this->answer($today, [
                'year' => $today->year,
                'month' => $today->month,
                'budget_phase' => BudgetForYear::find($user, $today->year)?->phase->value,
                'state' => 'none',
                'to_spend_minor_units' => null,
            ]);
        }

        $breakdown = $reading->monthToSpend->toSpendBreakdown();

        return $this->answer($today, [
            'year' => $reading->budget->year,
            'month' => $reading->month,
            'budget_phase' => $reading->budget->phase->value,
            'state' => $reading->summary()['state'],
            'to_spend_minor_units' => $breakdown['to_spend_minor_units'] ?? null,
            'income_minor_units' => $breakdown['income_minor_units'] ?? null,
            'expense_minor_units' => $breakdown['expense_minor_units'] ?? null,
            'set_aside_minor_units' => $reading->monthToSpend->reading->setAsideMinorUnits,
        ]);
    }
}
