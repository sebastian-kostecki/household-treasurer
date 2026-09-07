<?php

namespace App\Http\Controllers;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\FinancialOperation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialOperationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $operations = $user->financialOperations()
            ->with(['account:id,name', 'category:id,name'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FinancialOperation $operation): array => [
                'id' => $operation->id,
                'operation_date' => $operation->operation_date->toDateString(),
                'description' => $operation->description,
                'type' => $operation->type->value,
                'amount_grosze' => $operation->amount_grosze,
                'account_name' => $operation->account->name,
                'category_name' => $operation->category->name,
            ]);

        $accounts = $user->accounts()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'opening_date'])
            ->map(fn ($account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'opening_date' => $account->opening_date->toDateString(),
            ]);

        $incomeCategories = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['categories' => fn ($query) => $query
                ->where('type', CategoryType::Income)
                ->whereNull('archived_at')
                ->orderBy('name'),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn ($budget) => $budget->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'budget_year' => $budget->year,
            ]))
            ->values();

        return Inertia::render('Operations', [
            'operations' => $operations,
            'accounts' => $accounts,
            'incomeCategories' => $incomeCategories,
        ]);
    }
}
