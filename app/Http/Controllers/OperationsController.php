<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\OperationsListSection;
use App\Http\Requests\IndexOperationsRequest;
use App\Models\Account;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Support\AccountIdentity;
use App\Support\CategoryAppearance;
use App\Support\CategoryList\CategoryChoiceOptions;
use App\Support\CsvImport\OwnTransferImportSnapshots;
use App\Support\EntryToSpendInputs;
use App\Support\HandEntryMonthRemainingInputs;
use App\Support\OperationsDaySums;
use App\Support\OperationsFilterOptions;
use App\Support\OperationsListQuery;
use App\Support\OperationsSectionTotals;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    private const int OPERATIONS_PER_PAGE = 50;

    public function index(IndexOperationsRequest $request, OwnTransferImportSnapshots $ownTransferImportSnapshots): Response|RedirectResponse
    {
        $user = $request->user();
        $filters = $request->filters();
        $suppressedSections = $filters->suppressedSections();

        $operationsQuery = $filters->apply($user->financialOperations());

        if (in_array(OperationsListSection::FinancialOperations, $suppressedSections, true)) {
            $operationsQuery->whereRaw('0 = 1');
        }

        $operations = $operationsQuery
            ->with(['account:id,name,type,bank,sheet_year', 'budgetLine:id,category_id,archived_at'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id')
            ->paginate(self::OPERATIONS_PER_PAGE, pageName: 'operations_page')
            ->withQueryString()
            ->through(fn (FinancialOperation $operation): array => [
                'id' => $operation->id,
                'operation_date' => $operation->operation_date->toDateString(),
                'description' => $operation->description,
                'counterparty' => $operation->counterparty,
                'raw_statement_description' => $operation->raw_statement_description,
                'type' => $operation->type->value,
                'amount_minor_units' => $operation->amount_minor_units,
                'account_id' => $operation->account_id,
                'category_id' => $operation->budgetLine->category_id,
                'category_label' => $operation->budgetLine->category->name,
                'category_icon' => $operation->budgetLine->category->icon,
                'category_color' => $operation->budgetLine->category->color,
                'category_archived' => $operation->budgetLine->isArchived() || $operation->budgetLine->category->isArchived(),
                'sheet_operation' => $operation->account->isSheetAccount(),
                ...AccountIdentity::prefixedRowFields($operation->account, 'account'),
            ]);

        $operationDaySums = in_array(OperationsListSection::FinancialOperations, $suppressedSections, true)
            ? []
            : OperationsDaySums::forDates(
                $user,
                $filters,
                array_values(array_unique(array_column($operations->items(), 'operation_date'))),
            );
        $sectionTotals = OperationsSectionTotals::forUser($user);

        $ownTransfersQuery = $user->ownTransfers()
            ->with(['sourceAccount:id,name,type,bank', 'destinationAccount:id,name,type,bank'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id');

        if (in_array(OperationsListSection::OwnTransfers, $suppressedSections, true)) {
            $ownTransfersQuery->whereRaw('0 = 1');
        }

        $ownTransfers = $ownTransfersQuery
            ->paginate(self::OPERATIONS_PER_PAGE, pageName: 'own_transfers_page')
            ->withQueryString()
            ->through(fn (OwnTransfer $transfer): array => [
                'id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'amount_minor_units' => $transfer->amount_minor_units,
                'description' => $transfer->description,
                'raw_statement_descriptions' => $ownTransferImportSnapshots->descriptionsForDisplay(
                    $transfer->source_raw_statement_description,
                    $transfer->destination_raw_statement_description,
                ),
                'source_account_id' => $transfer->source_account_id,
                'destination_account_id' => $transfer->destination_account_id,
                ...AccountIdentity::prefixedRowFields($transfer->sourceAccount, 'source_account'),
                ...AccountIdentity::prefixedRowFields($transfer->destinationAccount, 'destination_account'),
            ]);

        $capitalMovementsQuery = $user->capitalMovements()
            ->with(['account:id,name,type,bank', 'termDeposit:id,name,bank'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        if (in_array(OperationsListSection::CapitalMovements, $suppressedSections, true)) {
            $capitalMovementsQuery->whereRaw('0 = 1');
        } elseif ($filters->isActive()) {
            $filters->applyToCapitalMovements($capitalMovementsQuery);
        }

        $capitalMovements = $capitalMovementsQuery
            ->paginate(self::OPERATIONS_PER_PAGE, pageName: 'capital_movements_page')
            ->withQueryString()
            ->through(fn (CapitalMovement $movement): array => [
                'id' => $movement->id,
                'movement_date' => $movement->movement_date->toDateString(),
                'amount_minor_units' => $movement->amount_minor_units,
                'description' => $movement->description,
                'direction' => $movement->direction->value,
                'account_id' => $movement->account_id,
                'term_deposit_id' => $movement->term_deposit_id,
                'term_deposit_name' => $movement->termDeposit->name,
                'term_deposit_bank' => $movement->termDeposit->bank->value,
                'term_deposit_mark_url' => AccountIdentity::markUrl($movement->termDeposit->bank),
                ...AccountIdentity::prefixedRowFields($movement->account, 'account'),
            ]);

        $activePaginator = match ($request->section()) {
            OperationsListSection::FinancialOperations => ['page' => 'operations_page', 'paginator' => $operations],
            OperationsListSection::OwnTransfers => ['page' => 'own_transfers_page', 'paginator' => $ownTransfers],
            OperationsListSection::CapitalMovements => ['page' => 'capital_movements_page', 'paginator' => $capitalMovements],
        };

        if ($activePaginator['paginator']->currentPage() > $activePaginator['paginator']->lastPage()) {
            return to_route('operations', [
                ...array_filter($request->safe()->only(OperationsListQuery::FILTER_KEYS), fn (mixed $value): bool => $value !== null),
                ...array_filter(
                    $request->only(OperationsListQuery::PAGE_KEYS),
                    fn (mixed $value): bool => is_scalar($value),
                ),
                'section' => $request->section()->value,
                $activePaginator['page'] => $activePaginator['paginator']->lastPage(),
            ]);
        }

        $termDeposits = $user->termDeposits()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (TermDeposit $termDeposit): array => [
                'id' => $termDeposit->id,
                'name' => $termDeposit->name,
                'opening_date' => $termDeposit->opening_date->toDateString(),
                'current_balance_minor_units' => $termDeposit->current_balance_minor_units,
            ]);

        $accounts = $user->accounts()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                ...AccountIdentity::payload($account),
                'opening_date' => $account->opening_date->toDateString(),
                'current_balance_minor_units' => $account->current_balance_minor_units,
            ]);

        $incomeCategories = collect(CategoryChoiceOptions::forType($user, CategoryType::Income))
            ->map(fn (array $row): array => [
                'id' => $row['category']->id,
                'name' => $row['category']->name,
                ...CategoryAppearance::fields($row['category']),
                'group' => CategoryAppearance::group($row['category']),
                'budget_year' => $row['budget_year'],
            ])
            ->values();

        $expenseCategories = collect(CategoryChoiceOptions::forType($user, CategoryType::Expense))
            ->map(fn (array $row): array => [
                'id' => $row['category']->id,
                'name' => $row['category']->name,
                ...CategoryAppearance::fields($row['category']),
                'group' => CategoryAppearance::group($row['category']),
                'budget_year' => $row['budget_year'],
                'planned_amount_minor_units' => $row['line']->planned_amount_minor_units ?? 0,
                'execution_minor_units' => $row['line']->execution_minor_units ?? 0,
            ])
            ->values();

        return Inertia::render('Operations', [
            'operations' => $operations,
            'ownTransfers' => $ownTransfers,
            'capitalMovements' => $capitalMovements,
            'termDeposits' => $termDeposits,
            'accounts' => $accounts,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'handEntryMonthRemaining' => HandEntryMonthRemainingInputs::forUser($user),
            'entryToSpend' => EntryToSpendInputs::forUser($user),
            'filters' => $filters->toArray(),
            'filtersActive' => $filters->isActive(),
            'suppressedSections' => array_map(
                fn (OperationsListSection $section): string => $section->value,
                $suppressedSections,
            ),
            'hasAnyRecords' => array_sum($sectionTotals) > 0,
            'section' => $request->section()->value,
            'operationDaySums' => (object) $operationDaySums,
            'sectionTotals' => $sectionTotals,
            'filterYears' => OperationsFilterOptions::years($user),
            'filterCategories' => OperationsFilterOptions::categories($user, $filters->year, $filters->categoryId),
            'filterAccounts' => OperationsFilterOptions::accounts($user),
            'filterTermDeposits' => OperationsFilterOptions::termDeposits($user),
        ]);
    }
}
