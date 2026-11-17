<?php

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AccountBalanceOnDate;
use App\Support\CsvImport\CsvImportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-31 12:00:00');
});

/**
 * An Account opened on the given date whose stored balance starts at its opening balance.
 */
function importIntegrityAccount(User $user, string $name, int $openingBalance, string $openingDate = '2026-01-01'): Account
{
    return Account::factory()->for($user)->create([
        'name' => $name,
        'opening_date' => $openingDate,
        'opening_balance_minor_units' => $openingBalance,
        'current_balance_minor_units' => $openingBalance,
    ]);
}

function importIntegrityExecutionBudget(User $user, int $year = 2026): Budget
{
    return Budget::factory()->for($user)->execution()->create(['year' => $year]);
}

/**
 * A list Category of the given type that has no Budget Line yet, so it joins the Budget
 * at the first save of an operation (ADR-0067).
 */
function importIntegrityListCategory(User $user, CategoryType $type): Category
{
    return Category::factory()->for($user)->create(['type' => $type]);
}

function importIntegrityPreview(User $user, Account $account, string $csv): void
{
    test()->actingAs($user)
        ->post(route('operations.import.preview.store'), [
            'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
            'account_id' => $account->id,
            'import_format' => 'other',
            'has_header' => true,
            'date_column' => 0,
            'description_column' => 1,
            'amount_layout' => 'signed',
            'amount_column' => 2,
        ])
        ->assertRedirect(route('operations.import.preview'));
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function importIntegrityRow(int $index, string $type, array $extra = []): array
{
    return [
        'index' => $index,
        'selected' => true,
        'type' => $type,
        ...$extra,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function importIntegrityCommit(User $user, array $rows, bool $acknowledgeDuplicates = false): TestResponse
{
    return test()->actingAs($user)
        ->from(route('operations.import.preview'))
        ->post(route('operations.import.store'), [
            'duplicate_warning_acknowledged' => $acknowledgeDuplicates,
            'rows' => $rows,
        ]);
}

/**
 * Everything a failed Import must leave untouched: row counts, every stored Account and
 * Term Deposit balance, and the Execution of every Budget Line.
 *
 * @return array<string, mixed>
 */
function importIntegritySnapshot(): array
{
    return [
        'financial_operations' => DB::table('financial_operations')->count(),
        'own_transfers' => DB::table('own_transfers')->count(),
        'capital_movements' => DB::table('capital_movements')->count(),
        'budget_lines' => DB::table('budget_lines')->count(),
        'account_balances' => DB::table('accounts')->orderBy('id')->pluck('current_balance_minor_units', 'id')->all(),
        'term_deposit_balances' => DB::table('term_deposits')->orderBy('id')->pluck('current_balance_minor_units', 'id')->all(),
        'line_executions' => DB::table('budget_lines')->orderBy('id')->pluck('execution_minor_units', 'id')->all(),
    ];
}

it('rolls the whole Import back when a later row of a New Year statement fails', function (): void {
    $user = User::factory()->create();
    $account = importIntegrityAccount($user, 'Checking', 100000, '2025-12-01');
    $budget = importIntegrityExecutionBudget($user, 2026);
    $category = importIntegrityListCategory($user, CategoryType::Expense);

    importIntegrityPreview($user, $account, <<<'CSV'
date,description,amount
2026-01-05,Groceries,-30.00
2025-12-30,Rent,-50.00
CSV);

    expect(session(CsvImportSession::SESSION_KEY.'.rows.0.valid'))->toBeTrue()
        ->and(session(CsvImportSession::SESSION_KEY.'.rows.1.valid'))->toBeTrue();

    expect($budget->budgetLines()->where('category_id', $category->id)->exists())->toBeFalse();
    $before = importIntegritySnapshot();

    importIntegrityCommit($user, [
        importIntegrityRow(0, 'expense', ['category_id' => $category->id]),
        importIntegrityRow(1, 'expense', ['category_id' => $category->id]),
    ])->assertSessionHasErrors('rows');

    expect(importIntegritySnapshot())->toBe($before)
        ->and($before['financial_operations'])->toBe(0)
        ->and($budget->budgetLines()->where('category_id', $category->id)->exists())->toBeFalse()
        ->and(session()->has(CsvImportSession::SESSION_KEY))->toBeTrue()
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000)
        ->and(AccountBalanceOnDate::for($account, now()))->toBe(100000);

    importIntegrityCommit($user, [
        importIntegrityRow(0, 'expense', ['category_id' => $category->id]),
    ])->assertRedirect(route('operations'));

    $joinedLine = $budget->budgetLines()->where('category_id', $category->id)->sole();

    expect(FinancialOperation::query()->count())->toBe(1)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 - 3000)
        ->and(AccountBalanceOnDate::for($account, now()))->toBe(100000 - 3000)
        ->and($joinedLine->execution_minor_units)->toBe(3000);
});

it('restores a split pair when a later row of the Import fails', function (): void {
    $user = User::factory()->create();
    $checking = importIntegrityAccount($user, 'Checking', 100000);
    $savings = importIntegrityAccount($user, 'Savings', 50000);
    $budget = importIntegrityExecutionBudget($user, 2026);
    $expenseLine = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 500000,
        'execution_minor_units' => 0,
    ]);
    $incomeLine = BudgetLine::factory()->for($budget)->income()->create([
        'planned_amount_minor_units' => 500000,
        'execution_minor_units' => 0,
    ]);

    importIntegrityPreview($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);
    importIntegrityCommit($user, [
        importIntegrityRow(0, 'expense', ['category_id' => $expenseLine->category_id]),
    ])->assertRedirect(route('operations'));

    $firstLeg = FinancialOperation::query()->sole();
    $firstLegDigest = $firstLeg->import_identity_digest;

    expect($firstLegDigest)->not->toBeNull()
        ->and($checking->refresh()->current_balance_minor_units)->toBe(90000)
        ->and($expenseLine->refresh()->execution_minor_units)->toBe(10000);

    importIntegrityPreview($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
2026-03-17,Coffee,-5.00
CSV);

    expect(session(CsvImportSession::SESSION_KEY.'.rows.0.import_pair_candidate'))->toBeTrue()
        ->and(session(CsvImportSession::SESSION_KEY.'.rows.1.valid'))->toBeTrue();

    $before = importIntegritySnapshot();

    importIntegrityCommit($user, [
        importIntegrityRow(0, 'own_transfer', [
            'other_account_id' => $checking->id,
            'pair_first_leg_kind' => 'financial_operation',
            'pair_first_leg_id' => $firstLeg->id,
        ]),
        importIntegrityRow(1, 'expense', ['category_id' => $incomeLine->category_id]),
    ])->assertSessionHasErrors('rows');

    $restoredFirstLeg = FinancialOperation::query()->find($firstLeg->id);

    expect(importIntegritySnapshot())->toBe($before)
        ->and($before['own_transfers'])->toBe(0)
        ->and($restoredFirstLeg)->not->toBeNull()
        ->and($restoredFirstLeg->import_identity_digest)->toBe($firstLegDigest)
        ->and($checking->refresh()->current_balance_minor_units)->toBe(90000)
        ->and($savings->refresh()->current_balance_minor_units)->toBe(50000)
        ->and($expenseLine->refresh()->execution_minor_units)->toBe(10000)
        ->and(session()->has(CsvImportSession::SESSION_KEY))->toBeTrue();
});

it('keeps the stored balance equal to the derived balance after a mixed Import', function (): void {
    $user = User::factory()->create();
    $checking = importIntegrityAccount($user, 'Checking', 100000);
    $savings = importIntegrityAccount($user, 'Savings', 0);
    $termDeposit = TermDeposit::factory()->for($user)->create([
        'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 200000,
        'current_balance_minor_units' => 200000,
    ]);
    $budget = importIntegrityExecutionBudget($user, 2026);
    $incomeLine = BudgetLine::factory()->for($budget)->income()->create([
        'planned_amount_minor_units' => 500000,
        'execution_minor_units' => 0,
    ]);
    $expenseLine = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 500000,
        'execution_minor_units' => 0,
    ]);

    importIntegrityPreview($user, $checking, <<<'CSV'
date,description,amount
2026-03-10,Salary,2500.00
2026-03-11,Groceries,-45.50
2026-03-12,Shop return,-12.00
2026-03-13,To savings,-100.00
2026-03-14,Deposit maturity,150.00
CSV);

    importIntegrityCommit($user, [
        importIntegrityRow(0, 'income', ['category_id' => $incomeLine->category_id]),
        importIntegrityRow(1, 'expense', ['category_id' => $expenseLine->category_id]),
        importIntegrityRow(2, 'refund', ['category_id' => $expenseLine->category_id]),
        importIntegrityRow(3, 'own_transfer', ['other_account_id' => $savings->id]),
        importIntegrityRow(4, 'capital_movement', [
            'term_deposit_id' => $termDeposit->id,
            'interest_amount_minor_units' => 2500,
            'interest_category_id' => $incomeLine->category_id,
        ]),
    ], true)->assertRedirect(route('operations'));

    // Checking: 1000,00 + 2500,00 - 45,50 + 12,00 - 100,00 + 150,00 (125,00 capital + 25,00 interest).
    $expectedChecking = 100000 + 250000 - 4550 + 1200 - 10000 + 15000;

    expect($expectedChecking)->toBe(351650)
        ->and(DB::table('financial_operations')->count())->toBe(4)
        ->and(DB::table('own_transfers')->count())->toBe(1)
        ->and(DB::table('capital_movements')->count())->toBe(1)
        ->and($checking->refresh()->current_balance_minor_units)->toBe($expectedChecking)
        ->and($checking->current_balance_minor_units)->toBe(AccountBalanceOnDate::for($checking, now()))
        ->and($savings->refresh()->current_balance_minor_units)->toBe(10000)
        ->and($savings->current_balance_minor_units)->toBe(AccountBalanceOnDate::for($savings, now()))
        ->and($termDeposit->refresh()->current_balance_minor_units)->toBe(200000 - 12500);
});

it('does not write anything when the same commit request is resent after success', function (): void {
    $user = User::factory()->create();
    $account = importIntegrityAccount($user, 'Checking', 100000);
    importIntegrityExecutionBudget($user, 2026);
    $category = importIntegrityListCategory($user, CategoryType::Expense);
    $rows = [importIntegrityRow(0, 'expense', ['category_id' => $category->id])];

    importIntegrityPreview($user, $account, <<<'CSV'
date,description,amount
2026-03-15,Coffee,-12.00
CSV);

    importIntegrityCommit($user, $rows)->assertRedirect(route('operations'));

    $afterFirst = importIntegritySnapshot();

    expect($afterFirst['financial_operations'])->toBe(1)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 - 1200);

    importIntegrityCommit($user, $rows)->assertRedirect(route('operations.import.create'));

    expect(importIntegritySnapshot())->toBe($afterFirst)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 - 1200);
});

it('writes nothing when a reimported duplicate is committed without acknowledgement', function (): void {
    $user = User::factory()->create();
    $account = importIntegrityAccount($user, 'Checking', 100000);
    importIntegrityExecutionBudget($user, 2026);
    $category = importIntegrityListCategory($user, CategoryType::Expense);
    $rows = [importIntegrityRow(0, 'expense', ['category_id' => $category->id])];
    $csv = <<<'CSV'
date,description,amount
2026-03-15,Coffee,-12.00
CSV;

    importIntegrityPreview($user, $account, $csv);
    importIntegrityCommit($user, $rows)->assertRedirect(route('operations'));

    importIntegrityPreview($user, $account, $csv);

    expect(session(CsvImportSession::SESSION_KEY.'.rows.0.import_duplicate'))->toBeTrue();

    $before = importIntegritySnapshot();

    importIntegrityCommit($user, $rows)->assertSessionHasErrors('duplicate_warning_acknowledged');

    expect(importIntegritySnapshot())->toBe($before)
        ->and($before['financial_operations'])->toBe(1)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 - 1200)
        ->and(session()->has(CsvImportSession::SESSION_KEY))->toBeTrue();
});

it('imports two identical rows of one file and flags both as duplicates on the next Import', function (): void {
    $user = User::factory()->create();
    $account = importIntegrityAccount($user, 'Checking', 100000);
    importIntegrityExecutionBudget($user, 2026);
    $category = importIntegrityListCategory($user, CategoryType::Expense);
    $rows = [
        importIntegrityRow(0, 'expense', ['category_id' => $category->id]),
        importIntegrityRow(1, 'expense', ['category_id' => $category->id]),
    ];
    $csv = <<<'CSV'
date,description,amount
2026-03-15,Coffee,-12.00
2026-03-15,Coffee,-12.00
CSV;

    importIntegrityPreview($user, $account, $csv);
    importIntegrityCommit($user, $rows)->assertRedirect(route('operations'));

    $digests = FinancialOperation::query()->pluck('import_identity_digest');

    expect($digests)->toHaveCount(2)
        ->and($digests->unique())->toHaveCount(1)
        ->and($digests->first())->not->toBeNull()
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 - 2400);

    importIntegrityPreview($user, $account, $csv);

    expect(session(CsvImportSession::SESSION_KEY.'.rows.0.import_duplicate'))->toBeTrue()
        ->and(session(CsvImportSession::SESSION_KEY.'.rows.1.import_duplicate'))->toBeTrue();

    $before = importIntegritySnapshot();

    importIntegrityCommit($user, $rows)->assertSessionHasErrors('duplicate_warning_acknowledged');

    expect(importIntegritySnapshot())->toBe($before)
        ->and($before['financial_operations'])->toBe(2)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 - 2400);
});

it('rejects a commit that repeats a preview row index', function (): void {
    $user = User::factory()->create();
    $account = importIntegrityAccount($user, 'Checking', 100000);
    importIntegrityExecutionBudget($user, 2026);
    $category = importIntegrityListCategory($user, CategoryType::Expense);

    importIntegrityPreview($user, $account, <<<'CSV'
date,description,amount
2026-03-15,Coffee,-12.00
CSV);

    $before = importIntegritySnapshot();

    importIntegrityCommit($user, [
        importIntegrityRow(0, 'expense', ['category_id' => $category->id]),
        importIntegrityRow(0, 'expense', ['category_id' => $category->id]),
    ])->assertSessionHasErrors('rows.1.index');

    expect(importIntegritySnapshot())->toBe($before)
        ->and($before['financial_operations'])->toBe(0)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000)
        ->and(session()->has(CsvImportSession::SESSION_KEY))->toBeTrue();
});

it('flags exactly the overlapping rows of a later real mBank statement as duplicates', function (): void {
    $this->travelTo('2025-01-10 12:00:00');

    $user = User::factory()->create();
    $account = importIntegrityAccount($user, 'eKonto', 100000, '2025-01-01');
    $budget = importIntegrityExecutionBudget($user, 2025);
    $incomeLine = BudgetLine::factory()->for($budget)->income()->create([
        'planned_amount_minor_units' => 500000,
        'execution_minor_units' => 0,
    ]);
    $expenseLine = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 500000,
        'execution_minor_units' => 0,
    ]);

    $previewMbank = function (string $fixture) use ($user, $account): void {
        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('mbank.csv', file_get_contents(base_path($fixture))),
                'account_id' => $account->id,
                'import_format' => 'mbank',
            ])
            ->assertRedirect(route('operations.import.preview'));
    };

    // mbank-basic.csv rows in file order: -355,91, +1000,00, -100,00, +400,00, -134,83, -30,99, -41,04.
    $basicTypes = ['expense', 'income', 'expense', 'income', 'expense', 'expense', 'expense'];
    $basicRows = [];

    foreach ($basicTypes as $index => $type) {
        $basicRows[] = importIntegrityRow($index, $type, [
            'category_id' => $type === 'income' ? $incomeLine->category_id : $expenseLine->category_id,
        ]);
    }

    $previewMbank('tests/Fixtures/mbank-basic.csv');
    importIntegrityCommit($user, $basicRows)->assertRedirect(route('operations'));

    expect(DB::table('financial_operations')->count())->toBe(7)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 + 73723);

    // mbank-overlap.csv: 2 new rows (-52,30, -18,75) followed by the last 3 rows of mbank-basic.csv.
    $previewMbank('tests/Fixtures/import/mbank-overlap.csv');

    $duplicateFlags = [];

    foreach (range(0, 4) as $index) {
        $duplicateFlags[] = session(CsvImportSession::SESSION_KEY.'.rows.'.$index.'.import_duplicate');
    }

    expect(session(CsvImportSession::SESSION_KEY.'.rows'))->toHaveCount(5)
        ->and($duplicateFlags)->toBe([false, false, true, true, true]);

    $overlapRow = fn (int $index, bool $selected): array => $selected
        ? importIntegrityRow($index, 'expense', ['category_id' => $expenseLine->category_id])
        : ['index' => $index, 'selected' => false];

    $afterBasic = importIntegritySnapshot();

    importIntegrityCommit($user, [
        $overlapRow(0, true),
        $overlapRow(1, true),
        $overlapRow(2, true),
        $overlapRow(3, false),
        $overlapRow(4, false),
    ])->assertSessionHasErrors('duplicate_warning_acknowledged');

    expect(importIntegritySnapshot())->toBe($afterBasic);

    importIntegrityCommit($user, [
        $overlapRow(0, true),
        $overlapRow(1, true),
        $overlapRow(2, false),
        $overlapRow(3, false),
        $overlapRow(4, false),
    ])->assertRedirect(route('operations'));

    expect(DB::table('financial_operations')->count())->toBe(9)
        ->and($account->refresh()->current_balance_minor_units)->toBe(100000 + 73723 - 5230 - 1875)
        ->and($account->current_balance_minor_units)->toBe(AccountBalanceOnDate::for($account, now()));
});
