<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CashExpectation;
use App\Models\Category;
use App\Support\AccountIdentity;
use App\Support\CategoryAppearance;
use App\Support\SelectedBudgetYear;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CashExpectation::class);

        $user = $request->user();
        $budgetYear = SelectedBudgetYear::resolve($request);
        $today = Carbon::today()->toDateString();

        $cashExpectations = $user->cashExpectations()
            ->with(['account:id,name,type,bank', 'category:id,name,icon,color'])
            ->when(
                $budgetYear !== null,
                fn ($query) => $query->whereYear('expectation_date', $budgetYear),
                fn ($query) => $query->whereRaw('0 = 1'),
            )
            ->orderBy('expectation_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CashExpectation $cashExpectation): array => $this->cashExpectationPayload($cashExpectation, $today));

        $accounts = $user->accounts()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                ...AccountIdentity::payload($account),
                'opening_date' => $account->opening_date->toDateString(),
            ]);

        $incomeCategories = $user->budgets()
            ->with(['categories' => fn ($query) => $query
                ->where('type', CategoryType::Income)
                ->where('category_variant', 'agenda')
                ->whereNull('archived_at')
                ->ordered(),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn (Budget $budget) => $budget->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                ...CategoryAppearance::fields($category),
                'budget_year' => $budget->year,
            ]))
            ->values();

        $expenseCategories = $user->budgets()
            ->with(['categories' => fn ($query) => $query
                ->where('type', CategoryType::Expense)
                ->where('category_variant', 'agenda')
                ->whereNull('archived_at')
                ->ordered(),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn (Budget $budget) => $budget->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                ...CategoryAppearance::fields($category),
                'budget_year' => $budget->year,
            ]))
            ->values();

        return Inertia::render('Agenda', [
            'cashExpectations' => $cashExpectations,
            'accounts' => $accounts,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     expectation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty: string|null,
     *     is_expense_refund: bool,
     *     is_overdue: bool,
     *     is_today: bool,
     *     account_id: int,
     *     category_id: int,
     *     category_name: string,
     *     category_icon: string,
     *     category_color: string,
     *     account_name: string,
     *     account_type: string,
     *     account_bank: string|null,
     *     account_mark_url: string|null,
     * }
     */
    private function cashExpectationPayload(CashExpectation $cashExpectation, string $today): array
    {
        $expectationDate = $cashExpectation->expectation_date->toDateString();

        return [
            'id' => $cashExpectation->id,
            'type' => $cashExpectation->type->value,
            'expectation_date' => $expectationDate,
            'amount_minor_units' => $cashExpectation->amount_minor_units,
            'description' => $cashExpectation->description,
            'counterparty' => $cashExpectation->counterparty,
            'is_expense_refund' => $cashExpectation->isExpenseRefund(),
            'is_overdue' => $expectationDate < $today,
            'is_today' => $expectationDate === $today,
            'account_id' => $cashExpectation->account_id,
            'category_id' => $cashExpectation->category_id,
            'category_name' => $cashExpectation->category->name,
            'category_icon' => $cashExpectation->category->icon,
            'category_color' => $cashExpectation->category->color,
            ...AccountIdentity::prefixedRowFields($cashExpectation->account, 'account'),
        ];
    }
}
