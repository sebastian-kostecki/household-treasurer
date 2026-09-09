<?php

namespace App\Http\Controllers;

use App\Actions\ImportFinancialOperations;
use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\ImportFormat;
use App\Http\Requests\PreviewCsvImportRequest;
use App\Http\Requests\StoreCsvImportRequest;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Support\AccountIdentity;
use App\Support\CategoryAppearance;
use App\Support\CsvImport\BnpParibasXlsxParser;
use App\Support\CsvImport\CsvImportParser;
use App\Support\CsvImport\CsvImportPreviewValidator;
use App\Support\CsvImport\CsvImportSession;
use App\Support\CsvImport\ImportDuplicateDetector;
use App\Support\CsvImport\ImportPairCandidateDetector;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CsvImportController extends Controller
{
    use AuthorizesRequests;

    public function create(Request $request): Response
    {
        $this->authorize('create', FinancialOperation::class);

        return Inertia::render('OperationsImport', [
            'accounts' => $this->accounts($request),
        ]);
    }

    public function preview(
        PreviewCsvImportRequest $request,
        CsvImportParser $parser,
        BnpParibasXlsxParser $bnpParibasXlsxParser,
        CsvImportPreviewValidator $previewValidator,
        ImportDuplicateDetector $importDuplicateDetector,
        ImportPairCandidateDetector $importPairCandidateDetector,
    ): RedirectResponse {
        $this->authorize('create', FinancialOperation::class);

        $account = Account::query()
            ->whereKey($request->integer('account_id'))
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->firstOrFail();

        try {
            if ($request->importFormat() === ImportFormat::BnpParibas) {
                $path = $request->file('file')?->getRealPath();

                if (! is_string($path)) {
                    throw new InvalidArgumentException(__('The uploaded file could not be read.'));
                }

                $parsedRows = $bnpParibasXlsxParser->parse($path);
            } else {
                $import = $request->parsedImport();
                $parsedRows = $parser->parse($import['contents'], $import['mapping']);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $operationRows = [];
        foreach ($parsedRows as $row) {
            if (! $this->isOperationShapedRow($row)) {
                continue;
            }

            $row['index'] = count($operationRows);
            $operationRows[] = $row;
        }

        $rows = array_map(function (array $row) use ($previewValidator, $account): array {
            $validated = $previewValidator->validate($row, $account);

            return [
                'index' => $row['index'],
                'operation_date' => $row['operation_date'],
                'description' => $row['description'],
                'counterparty' => $row['counterparty'] ?? null,
                'raw_statement_description' => $row['raw_statement_description'] ?? null,
                'amount_minor_units' => $row['amount_minor_units'],
                'valid' => $validated['valid'],
                'invalid_reason' => $validated['invalid_reason'],
            ];
        }, $operationRows);

        $rows = $importDuplicateDetector->annotateRows($request->user(), $account->id, $rows);
        $rows = $importPairCandidateDetector->annotateRows($request->user(), $account->id, $rows);

        CsvImportSession::put($request->user(), [
            'account_id' => $account->id,
            'rows' => $rows,
        ]);

        return to_route('operations.import.preview');
    }

    public function showPreview(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', FinancialOperation::class);

        $import = CsvImportSession::get($request->user());

        if ($import === null) {
            return to_route('operations.import.create');
        }

        $account = Account::query()
            ->whereKey($import['account_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return Inertia::render('OperationsImportPreview', [
            'account' => [
                ...AccountIdentity::payload($account),
                'current_balance_minor_units' => $account->current_balance_minor_units,
            ],
            'rows' => $import['rows'],
            'incomeCategories' => $this->incomeCategories($request),
            'expenseCategories' => $this->expenseCategories($request),
            'otherAccounts' => $this->otherAccounts($request, $account, $import['rows']),
        ]);
    }

    public function store(
        StoreCsvImportRequest $request,
        ImportFinancialOperations $importFinancialOperations,
        ImportDuplicateDetector $importDuplicateDetector,
    ): RedirectResponse {
        $this->authorize('create', FinancialOperation::class);

        $import = CsvImportSession::get($request->user());

        if ($import === null) {
            return to_route('operations.import.create');
        }

        $rowsByIndex = collect($import['rows'])->keyBy('index');
        $selectedRows = [];
        $usedPairFirstLegIds = [];

        foreach ($request->rowSelections() as $selection) {
            if (! $selection['selected']) {
                continue;
            }

            $parsedRow = $rowsByIndex->get($selection['index']);

            if (! is_array($parsedRow) || ($parsedRow['valid'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'rows' => __('One or more selected rows are invalid.'),
                ]);
            }

            if ($selection['type'] === 'own_transfer') {
                if ($selection['other_account_id'] === null) {
                    throw ValidationException::withMessages([
                        'rows' => __('Each selected own transfer row must have another account.'),
                    ]);
                }

                if ($selection['other_account_id'] === (int) $import['account_id']) {
                    throw ValidationException::withMessages([
                        'rows' => __('The other account must be different from the import account.'),
                    ]);
                }

                $pairFirstLegKind = $selection['pair_first_leg_kind'];
                $pairFirstLegId = $selection['pair_first_leg_id'];
                $pairCandidates = is_array($parsedRow['pair_candidates'] ?? null)
                    ? $parsedRow['pair_candidates']
                    : [];

                if ($pairFirstLegKind !== null || $pairFirstLegId !== null) {
                    if (
                        ! in_array($pairFirstLegKind, ['financial_operation', 'own_transfer'], true)
                        || $pairFirstLegId === null
                    ) {
                        throw ValidationException::withMessages([
                            'rows' => __('The selected import pair candidate is invalid.'),
                        ]);
                    }

                    if (
                        $pairFirstLegKind === 'financial_operation'
                        && $this->pairCandidatesContainOwnTransfer($pairCandidates)
                    ) {
                        throw ValidationException::withMessages([
                            'rows' => __('This row must pair with the existing own transfer.'),
                        ]);
                    }

                    if (in_array($pairFirstLegId, $usedPairFirstLegIds, true)) {
                        throw ValidationException::withMessages([
                            'rows' => __('Two selected rows cannot pair with the same first leg.'),
                        ]);
                    }

                    $matchingCandidate = collect($pairCandidates)->first(
                        fn (array $candidate): bool => ($candidate['kind'] ?? null) === $pairFirstLegKind
                            && ($candidate['id'] ?? null) === $pairFirstLegId
                            && ($candidate['account_id'] ?? null) === $selection['other_account_id'],
                    );

                    if ($matchingCandidate === null) {
                        throw ValidationException::withMessages([
                            'rows' => __('The selected import pair candidate no longer matches this row.'),
                        ]);
                    }

                    $usedPairFirstLegIds[] = $pairFirstLegId;
                }

                $selectedRows[] = [
                    'index' => $selection['index'],
                    'operation_date' => (string) $parsedRow['operation_date'],
                    'description' => (string) $parsedRow['description'],
                    'counterparty' => $parsedRow['counterparty'] ?? null,
                    'raw_statement_description' => $parsedRow['raw_statement_description'] ?? null,
                    'amount_minor_units' => (int) $parsedRow['amount_minor_units'],
                    'type' => 'own_transfer',
                    'other_account_id' => $selection['other_account_id'],
                    'pair_first_leg_kind' => $pairFirstLegKind,
                    'pair_first_leg_id' => $pairFirstLegId,
                ];

                continue;
            }

            $pairCandidates = is_array($parsedRow['pair_candidates'] ?? null)
                ? $parsedRow['pair_candidates']
                : [];

            if ($this->pairCandidatesContainOwnTransfer($pairCandidates)) {
                throw ValidationException::withMessages([
                    'rows' => __('This row must be committed as an own transfer pair.'),
                ]);
            }

            if ($selection['pair_first_leg_kind'] !== null || $selection['pair_first_leg_id'] !== null) {
                throw ValidationException::withMessages([
                    'rows' => __('Import pair candidates can only be committed as own transfers.'),
                ]);
            }

            if ($selection['category_id'] === null) {
                throw ValidationException::withMessages([
                    'rows' => __('Each selected row must have a category.'),
                ]);
            }

            $selectedRows[] = [
                'index' => $selection['index'],
                'operation_date' => (string) $parsedRow['operation_date'],
                'description' => (string) $parsedRow['description'],
                'counterparty' => $parsedRow['counterparty'] ?? null,
                'raw_statement_description' => $parsedRow['raw_statement_description'] ?? null,
                'amount_minor_units' => (int) $parsedRow['amount_minor_units'],
                'type' => $selection['type'],
                'category_id' => $selection['category_id'],
            ];
        }

        $selectedImportDuplicates = $importDuplicateDetector->selectedImportDuplicates(
            $request->user(),
            (int) $import['account_id'],
            array_map(
                fn (array $row): array => [
                    'operation_date' => $row['operation_date'],
                    'amount_minor_units' => $row['amount_minor_units'],
                    'raw_statement_description' => $row['raw_statement_description'] ?? null,
                ],
                $selectedRows,
            ),
        );

        if ($selectedImportDuplicates !== [] && ! $request->boolean('duplicate_warning_acknowledged')) {
            throw ValidationException::withMessages([
                'duplicate_warning_acknowledged' => __('Acknowledge the import duplicate warning before committing selected duplicates.'),
            ]);
        }

        $imported = $importFinancialOperations->handle(
            $request->user(),
            (int) $import['account_id'],
            $selectedRows,
        );

        CsvImportSession::forget();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->importSuccessMessage($imported),
        ]);

        return to_route('operations');
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     * @return array<int, array{id: int, name: string, current_balance_minor_units: int}>
     */
    private function otherAccounts(Request $request, Account $importAccount, array $previewRows): array
    {
        $pairCandidateAccountIds = collect($previewRows)
            ->flatMap(fn (array $row): array => collect($row['pair_candidates'] ?? [])
                ->pluck('account_id')
                ->all())
            ->unique()
            ->values();

        $accounts = $request->user()
            ->accounts()
            ->whereKeyNot($importAccount->id)
            ->where(function ($query) use ($pairCandidateAccountIds): void {
                $query->whereNull('archived_at');

                if ($pairCandidateAccountIds->isNotEmpty()) {
                    $query->orWhereIn('id', $pairCandidateAccountIds);
                }
            })
            ->orderBy('name')
            ->get();

        return $accounts
            ->map(fn (Account $account): array => [
                ...AccountIdentity::payload($account),
                'current_balance_minor_units' => $account->current_balance_minor_units,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $pairCandidates
     */
    private function pairCandidatesContainOwnTransfer(array $pairCandidates): bool
    {
        return collect($pairCandidates)->contains(
            fn (array $candidate): bool => ($candidate['kind'] ?? null) === 'own_transfer',
        );
    }

    /**
     * @param  array{financial_operations: int, own_transfers: int}  $imported
     */
    private function importSuccessMessage(array $imported): string
    {
        $messages = [];

        if ($imported['financial_operations'] > 0) {
            $messages[] = trans_choice(
                ':count financial operation imported.|:count financial operations imported.',
                $imported['financial_operations'],
                ['count' => $imported['financial_operations']],
            );
        }

        if ($imported['own_transfers'] > 0) {
            $messages[] = trans_choice(
                ':count own transfer imported.|:count own transfers imported.',
                $imported['own_transfers'],
                ['count' => $imported['own_transfers']],
            );
        }

        return implode(' ', $messages);
    }

    /**
     * @param  array{
     *     operation_date: ?string,
     *     amount_minor_units: ?int,
     *     amount_has_foreign_currency?: bool,
     * }  $row
     */
    private function isOperationShapedRow(array $row): bool
    {
        if ($row['operation_date'] === null) {
            return false;
        }

        return $row['amount_minor_units'] !== null || ($row['amount_has_foreign_currency'] ?? false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(Request $request): array
    {
        return $request->user()
            ->accounts()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                ...AccountIdentity::payload($account),
                'opening_date' => $account->opening_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, budget_year: int}>
     */
    private function incomeCategories(Request $request): array
    {
        return $request->user()
            ->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['categories' => fn ($query) => $query
                ->where('type', CategoryType::Income)
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
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, budget_year: int}>
     */
    private function expenseCategories(Request $request): array
    {
        return $request->user()
            ->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['categories' => fn ($query) => $query
                ->where('type', CategoryType::Expense)
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
            ->values()
            ->all();
    }
}
