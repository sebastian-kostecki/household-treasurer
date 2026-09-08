<?php

namespace App\Http\Controllers;

use App\Actions\ImportFinancialOperations;
use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\ImportFormat;
use App\Http\Requests\PreviewCsvImportRequest;
use App\Http\Requests\StoreCsvImportRequest;
use App\Models\Account;
use App\Models\FinancialOperation;
use App\Support\CsvImport\BnpParibasXlsxParser;
use App\Support\CsvImport\CsvImportParser;
use App\Support\CsvImport\CsvImportPreviewValidator;
use App\Support\CsvImport\CsvImportSession;
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
                'amount_grosze' => $row['amount_grosze'],
                'valid' => $validated['valid'],
                'invalid_reason' => $validated['invalid_reason'],
            ];
        }, $operationRows);

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
                'id' => $account->id,
                'name' => $account->name,
            ],
            'rows' => $import['rows'],
            'incomeCategories' => $this->incomeCategories($request),
            'expenseCategories' => $this->expenseCategories($request),
            'otherAccounts' => $this->otherAccounts($request, $account),
        ]);
    }

    public function store(
        StoreCsvImportRequest $request,
        ImportFinancialOperations $importFinancialOperations,
    ): RedirectResponse {
        $this->authorize('create', FinancialOperation::class);

        $import = CsvImportSession::get($request->user());

        if ($import === null) {
            return to_route('operations.import.create');
        }

        $rowsByIndex = collect($import['rows'])->keyBy('index');
        $selectedRows = [];

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

                $selectedRows[] = [
                    'index' => $selection['index'],
                    'operation_date' => (string) $parsedRow['operation_date'],
                    'description' => (string) $parsedRow['description'],
                    'amount_grosze' => (int) $parsedRow['amount_grosze'],
                    'type' => 'own_transfer',
                    'other_account_id' => $selection['other_account_id'],
                ];

                continue;
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
                'amount_grosze' => (int) $parsedRow['amount_grosze'],
                'type' => $selection['type'],
                'category_id' => $selection['category_id'],
            ];
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
     * @return array<int, array{id: int, name: string}>
     */
    private function otherAccounts(Request $request, Account $importAccount): array
    {
        return $request->user()
            ->accounts()
            ->whereNull('archived_at')
            ->whereKeyNot($importAccount->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
            ])
            ->values()
            ->all();
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
     *     amount_grosze: ?int,
     *     amount_has_foreign_currency?: bool,
     * }  $row
     */
    private function isOperationShapedRow(array $row): bool
    {
        if ($row['operation_date'] === null) {
            return false;
        }

        return $row['amount_grosze'] !== null || ($row['amount_has_foreign_currency'] ?? false);
    }

    /**
     * @return array<int, array{id: int, name: string, opening_date: string}>
     */
    private function accounts(Request $request): array
    {
        return $request->user()
            ->accounts()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
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
                ->orderBy('name'),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn ($budget) => $budget->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
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
                ->orderBy('name'),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn ($budget) => $budget->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'budget_year' => $budget->year,
            ]))
            ->values()
            ->all();
    }
}
