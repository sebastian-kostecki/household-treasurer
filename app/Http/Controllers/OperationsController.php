<?php

namespace App\Http\Controllers;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    private const int OPERATIONS_PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $operations = $user->financialOperations()
            ->with(['account:id,name', 'category:id,name'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id')
            ->paginate(self::OPERATIONS_PER_PAGE, pageName: 'operations_page')
            ->through(fn (FinancialOperation $operation): array => [
                'id' => $operation->id,
                'operation_date' => $operation->operation_date->toDateString(),
                'description' => $operation->description,
                'type' => $operation->type->value,
                'amount_grosze' => $operation->amount_grosze,
                'account_id' => $operation->account_id,
                'category_id' => $operation->category_id,
                'account_name' => $operation->account->name,
                'category_name' => $operation->category->name,
            ]);

        $ownTransfers = $user->ownTransfers()
            ->with(['sourceAccount:id,name', 'destinationAccount:id,name'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(self::OPERATIONS_PER_PAGE, pageName: 'own_transfers_page')
            ->through(fn (OwnTransfer $transfer): array => [
                'id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'amount_grosze' => $transfer->amount_grosze,
                'description' => $transfer->description,
                'source_account_id' => $transfer->source_account_id,
                'destination_account_id' => $transfer->destination_account_id,
                'source_account_name' => $transfer->sourceAccount->name,
                'destination_account_name' => $transfer->destinationAccount->name,
            ]);

        $accounts = $user->accounts()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'opening_date' => $account->opening_date->toDateString(),
                'current_balance_grosze' => $account->current_balance_grosze,
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

        $expenseCategories = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['categories' => fn ($query) => $query
                ->where('type', CategoryType::Expense)
                ->whereNull('archived_at')
                ->orderBy('name'),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn ($budget) => $budget->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'budget_year' => $budget->year,
                'planned_amount_grosze' => $category->planned_amount_grosze,
                'execution_grosze' => $category->execution_grosze,
            ]))
            ->values();

        return Inertia::render('Operations', [
            'operations' => $operations,
            'ownTransfers' => $ownTransfers,
            'accounts' => $accounts,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
        ]);
    }
}
