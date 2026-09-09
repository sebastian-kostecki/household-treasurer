<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\CsvImport\ImportIdentityDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_csv_import(): void
    {
        $this->get(route('operations.import.create'))->assertRedirect(route('login'));
        $this->get(route('operations.import.preview'))->assertRedirect(route('login'));
        $this->post(route('operations.import.preview.store'))->assertRedirect(route('login'));
        $this->post(route('operations.import.store'))->assertRedirect(route('login'));
    }

    public function test_user_can_preview_csv_with_signed_amount_column(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-16,Groceries,-45.50
CSV;

        $this->actingAs($user)
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

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('OperationsImportPreview')
                ->where('account.id', $account->id)
                ->has('rows', 2)
                ->where('rows.0.valid', true)
                ->where('rows.0.amount_grosze', 250000)
                ->where('rows.1.valid', true)
                ->where('rows.1.amount_grosze', -4550),
            );
    }

    public function test_user_can_preview_csv_with_split_inflow_and_outflow_columns(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date;description;inflow;outflow
15.03.2026;Salary;2500,00;
16.03.2026;Groceries;;45,50
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'split',
                'inflow_column' => 2,
                'outflow_column' => 3,
            ])
            ->assertRedirect(route('operations.import.preview'));

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.amount_grosze', 250000)
                ->where('rows.0.valid', true)
                ->where('rows.1.amount_grosze', -4550)
                ->where('rows.1.valid', true),
            );
    }

    public function test_junk_rows_without_parseable_date_and_amount_are_omitted_from_preview(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
mBank S.A. Bankowość Detaliczna,,
#Lista operacji,,
2026-03-15,Salary,2500.00
CSV;

        $this->actingAs($user)
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

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1)
                ->where('rows.0.description', 'Salary')
                ->where('rows.0.valid', true),
            );
    }

    public function test_amounts_with_pln_and_zl_suffixes_parse_to_grosze(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date;description;amount
2026-03-15;Expense;-355,91 PLN
2026-03-16;Income;1 000,00 zł
CSV;

        $this->actingAs($user)
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

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.amount_grosze', -35591)
                ->where('rows.0.valid', true)
                ->where('rows.1.amount_grosze', 100000)
                ->where('rows.1.valid', true),
            );
    }

    public function test_operation_shaped_row_with_foreign_currency_is_invalid(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Foreign purchase,10.00 EUR
CSV;

        $this->actingAs($user)
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

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1)
                ->where('rows.0.valid', false)
                ->where('rows.0.invalid_reason', __('Only PLN amounts are supported.')),
            );
    }

    public function test_invalid_rows_are_marked_with_reason(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture(accountAttributes: [
            'opening_date' => '2026-03-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
bad-date,Broken row,10.00
2026-07-01,Future row,10.00
2026-02-01,Before opening,10.00
2026-03-15,Zero amount,0.00
CSV;

        $this->actingAs($user)
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

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 3)
                ->where('rows.0.valid', false)
                ->where('rows.0.invalid_reason', __('The operation date cannot be in the future.'))
                ->where('rows.1.valid', false)
                ->where('rows.2.valid', false),
            );
    }

    public function test_user_can_import_only_selected_valid_rows_when_file_contains_invalid_rows(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
bad-date,Broken row,10.00
2026-03-15,Salary,2500.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ]);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 1);
        $this->assertDatabaseHas('financial_operations', [
            'description' => 'Salary',
            'amount_grosze' => 250000,
        ]);
    }

    public function test_user_can_import_selected_valid_rows_atomically(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-16,Groceries,45.50
2026-03-17,Refund,-12.00
CSV;

        $this->actingAs($user)
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

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                    [
                        'index' => 2,
                        'selected' => true,
                        'type' => 'refund',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 3);

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income->value,
            'amount_grosze' => 250000,
            'description' => 'Salary',
        ]);

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Expense->value,
            'amount_grosze' => 4550,
            'description' => 'Groceries',
        ]);

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Expense->value,
            'amount_grosze' => -1200,
            'description' => 'Refund',
        ]);

        $account->refresh();
        $this->assertSame(100000 + 250000 - 4550 + 1200, $account->current_balance_grosze);
    }

    public function test_import_rolls_back_all_rows_when_one_selected_row_fails_validation(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-16,Groceries,45.50
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ]);

        $this->actingAs($user)
            ->from(route('operations.import.preview'))
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_import_rejects_csv_with_more_than_five_thousand_rows(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();

        $lines = ['date,description,amount'];
        for ($i = 0; $i <= 5000; $i++) {
            $lines[] = sprintf('2026-03-15,Row %d,1.00', $i);
        }

        $csv = implode("\n", $lines);

        $this->actingAs($user)
            ->from(route('operations.import.create'))
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
            ->assertSessionHasErrors('file');
    }

    public function test_import_does_not_require_duplicate_acknowledgement_for_new_rows(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 1);
    }

    public function test_import_requires_duplicate_acknowledgement_when_reimporting_selected_duplicate(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => false,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('duplicate_warning_acknowledged');
    }

    public function test_import_preview_page_shows_duplicate_warning(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ]);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.valid', true)
                ->has('rows', 1),
            );
    }

    public function test_import_preview_includes_other_accounts_for_own_transfer_classification(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer,-100.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ]);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('otherAccounts', 1)
                ->where('otherAccounts.0.id', $otherAccount->id)
                ->where('otherAccounts.0.name', 'Savings'),
            );
    }

    public function test_user_can_import_csv_row_as_own_transfer_with_outflow_from_import_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,To savings,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('own_transfers', 1);
        $this->assertDatabaseHas('own_transfers', [
            'user_id' => $user->id,
            'source_account_id' => $account->id,
            'destination_account_id' => $otherAccount->id,
            'amount_grosze' => 10000,
            'description' => 'To savings',
        ]);

        $account->refresh();
        $otherAccount->refresh();
        $this->assertSame(100000 - 10000, $account->current_balance_grosze);
        $this->assertSame(10000, $otherAccount->current_balance_grosze);
    }

    public function test_user_can_import_csv_row_as_own_transfer_with_inflow_to_import_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 50000,
            'current_balance_grosze' => 50000,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,From savings,100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('own_transfers', [
            'source_account_id' => $otherAccount->id,
            'destination_account_id' => $account->id,
            'amount_grosze' => 10000,
        ]);

        $account->refresh();
        $otherAccount->refresh();
        $this->assertSame(100000 + 10000, $account->current_balance_grosze);
        $this->assertSame(50000 - 10000, $otherAccount->current_balance_grosze);
    }

    public function test_own_transfer_import_requires_other_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->from(route('operations.import.preview'))
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_own_transfer_import_rejects_other_account_same_as_import_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->from(route('operations.import.preview'))
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $account->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_user_can_import_mixed_financial_operations_and_own_transfers_atomically(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-16,To savings,-100.00
2026-03-17,Groceries,-45.50
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                    [
                        'index' => 2,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 2);
        $this->assertDatabaseCount('own_transfers', 1);
    }

    public function test_own_transfer_import_rolls_back_all_rows_when_one_row_fails_validation(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-02-15,Early transfer,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->from(route('operations.import.preview'))
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_own_transfer_import_rejects_archived_other_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->from(route('operations.import.preview'))
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_own_transfer_import_rejects_another_users_other_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'opening_date' => '2026-01-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->from(route('operations.import.preview'))
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_user_cannot_import_using_another_users_account(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherUser = User::factory()->create();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->actingAs($otherUser)
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
            ->assertSessionHasErrors('account_id');
    }

    public function test_preview_requires_explicit_import_format(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertSessionHasErrors('import_format');
    }

    public function test_mbank_import_produces_statement_operations_from_fixture(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);
        $csv = file_get_contents(base_path('tests/Fixtures/mbank-basic.csv'));

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('mbank.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'mbank',
            ])
            ->assertRedirect(route('operations.import.preview'));

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 7)
                ->where('rows.0.operation_date', '2025-01-09')
                ->where('rows.0.amount_grosze', -35591)
                ->where('rows.0.description', fn (?string $description) => str_contains((string) $description, 'C&A MODE'))
                ->where('rows.0.valid', true)
                ->where('rows.1.operation_date', '2025-01-09')
                ->where('rows.1.amount_grosze', 100000)
                ->where('rows.1.valid', true)
                ->where('rows.6.operation_date', '2025-01-02')
                ->where('rows.6.amount_grosze', -4104)
                ->where('rows.6.valid', true),
            );
    }

    public function test_mbank_import_rejects_csv_without_data_operacji_header(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
2025-01-09,Expense,-10.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('mbank.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'mbank',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_mbank_import_rejects_xlsx_files(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->create('statement.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'account_id' => $account->id,
                'import_format' => 'mbank',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_bnp_paribas_import_produces_statement_operations_from_fixture(): void
    {
        $this->travelTo('2025-01-11');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => $this->bnpParibasFixtureUpload(),
                'account_id' => $account->id,
                'import_format' => 'bnp_paribas',
            ])
            ->assertRedirect(route('operations.import.preview'));

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 7)
                ->where('rows.0.operation_date', '2025-01-11')
                ->where('rows.0.amount_grosze', -3739)
                ->where('rows.0.description', fn (?string $description) => str_contains((string) $description, 'JMP S.A. BIEDRO'))
                ->where('rows.0.counterparty', null)
                ->where('rows.0.raw_statement_description', fn (?string $value) => str_contains((string) $value, 'JMP S.A. BIEDRO'))
                ->where('rows.0.valid', true)
                ->where('rows.1.operation_date', '2025-01-11')
                ->where('rows.1.amount_grosze', -2999)
                ->where('rows.1.valid', true)
                ->where('rows.4.operation_date', '2025-01-09')
                ->where('rows.4.amount_grosze', -93900)
                ->where('rows.4.counterparty', 'Przedszkole Niepubliczne Odkrywcy Talentów')
                ->where('rows.4.description', 'Jan Nowak')
                ->where('rows.5.operation_date', '2025-01-09')
                ->where('rows.5.amount_grosze', 1000000)
                ->where('rows.5.counterparty', 'Employer')
                ->where('rows.5.description', 'WYPŁATA GRUDZIEŃ 2024')
                ->where('rows.5.valid', true)
                ->where('rows.6.operation_date', '2025-01-09')
                ->where('rows.6.amount_grosze', 500000)
                ->where('rows.6.valid', true),
            );
    }

    public function test_bnp_paribas_import_maps_split_sender_and_recipient_columns(): void
    {
        $this->travelTo('2026-09-08');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => $this->bnpParibasSplitPartiesUpload(),
                'account_id' => $account->id,
                'import_format' => 'bnp_paribas',
            ])
            ->assertRedirect(route('operations.import.preview'));

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 4)
                ->where('rows.0.operation_date', '2026-09-07')
                ->where('rows.0.amount_grosze', -3735)
                ->where('rows.0.counterparty', 'Grocery Store')
                ->where('rows.0.description', 'BLIK payment')
                ->where('rows.0.raw_statement_description', "Grocery Store\nBLIK payment")
                ->where('rows.0.valid', true)
                ->where('rows.1.operation_date', '2026-09-07')
                ->where('rows.1.amount_grosze', 500000)
                ->where('rows.1.counterparty', 'Employer Sp. z o.o.')
                ->where('rows.1.description', 'Salary September')
                ->where('rows.1.valid', true)
                ->where('rows.2.operation_date', '2026-09-07')
                ->where('rows.2.amount_grosze', -3836)
                ->where('rows.2.counterparty', null)
                ->where('rows.2.description', 'STOKROTKA NR 06 LUBLIN')
                ->where('rows.2.valid', true)
                ->where('rows.3.operation_date', '2026-09-06')
                ->where('rows.3.amount_grosze', -199)
                ->where('rows.3.counterparty', '48160000000000000000000000')
                ->where('rows.3.description', 'Account fee')
                ->where('rows.3.valid', true),
            );
    }

    public function test_bnp_paribas_import_rejects_csv_files(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
2025-01-09,Expense,-10.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'bnp_paribas',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_other_import_rejects_xlsx_files(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->create('statement.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_bnp_paribas_import_rejects_xlsx_without_expected_header(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->create('statement.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'account_id' => $account->id,
                'import_format' => 'bnp_paribas',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_other_import_still_requires_column_mapping(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
            ])
            ->assertSessionHasErrors(['has_header', 'date_column', 'description_column', 'amount_layout']);
    }

    public function test_mbank_preview_exposes_raw_statement_description_and_empty_counterparty(): void
    {
        $this->travelTo('2025-01-10');

        [$user, $account] = $this->createImportFixture([
            'opening_date' => '2025-01-01',
        ]);
        $csv = file_get_contents(base_path('tests/Fixtures/mbank-basic.csv'));

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('mbank.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'mbank',
            ])
            ->assertRedirect(route('operations.import.preview'));

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.counterparty', null)
                ->where('rows.0.raw_statement_description', fn (?string $value) => str_contains((string) $value, 'C&A MODE'))
                ->where('rows.0.description', fn (?string $value) => str_contains((string) $value, 'C&A MODE')),
            );
    }

    public function test_other_format_preview_maps_optional_counterparty_column(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,counterparty,amount
2026-03-15,Salary,Acme Corp,2500.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'counterparty_column' => 2,
                'amount_layout' => 'signed',
                'amount_column' => 3,
            ])
            ->assertRedirect(route('operations.import.preview'));

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.counterparty', 'Acme Corp')
                ->where('rows.0.description', 'Salary')
                ->where('rows.0.raw_statement_description', "Salary\nAcme Corp"),
            );
    }

    public function test_import_commit_persists_counterparty_and_raw_statement_description(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,counterparty,amount
2026-03-15,Salary,Acme Corp,2500.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'counterparty_column' => 2,
                'amount_layout' => 'signed',
                'amount_column' => 3,
            ]);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'description' => 'Salary',
            'counterparty' => 'Acme Corp',
            'raw_statement_description' => "Salary\nAcme Corp",
        ]);
    }

    public function test_own_transfer_import_persists_raw_statement_description_without_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ]);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('own_transfers', [
            'description' => 'Transfer to savings',
            'source_raw_statement_description' => 'Transfer to savings',
        ]);
    }

    public function test_editing_imported_operation_does_not_change_raw_statement_description(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $operation = FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($incomeCategory->budget)
            ->for($incomeCategory)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Imported description',
                'counterparty' => 'Original counterparty',
                'raw_statement_description' => 'Original snapshot',
            ]);

        $this->actingAs($user)
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Edited description',
                'counterparty' => 'Edited counterparty',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $operation->refresh();

        $this->assertSame('Edited description', $operation->description);
        $this->assertSame('Edited counterparty', $operation->counterparty);
        $this->assertSame('Original snapshot', $operation->raw_statement_description);
    }

    public function test_reimport_flags_duplicate_rows_and_allows_committing_other_rows(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-16,Groceries,-45.50
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true)
                ->where('rows.0.valid', true)
                ->where('rows.1.import_duplicate', true)
                ->where('rows.1.valid', true),
            );

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => false,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => false,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('financial_operations', 2);
    }

    public function test_reimport_can_commit_only_non_duplicate_rows(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $firstCsv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;
        $secondCsv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-16,Groceries,-45.50
CSV;

        $this->previewCsv($user, $account, $firstCsv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $account, $secondCsv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => false,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 2);
    }

    public function test_reimporting_selected_duplicate_creates_second_record_with_acknowledgement(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 2);
    }

    public function test_import_commit_stores_frozen_import_identity(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $operation = FinancialOperation::query()->firstOrFail();

        $this->assertSame($account->id, $operation->import_identity_account_id);
        $this->assertSame('2026-03-15', $operation->import_identity_date->toDateString());
        $this->assertSame(250000, $operation->import_identity_amount_grosze);
        $this->assertNotNull($operation->import_identity_digest);
    }

    public function test_hand_entered_records_are_not_import_duplicates(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();

        FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($incomeCategory->budget)
            ->for($incomeCategory)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Salary',
                'raw_statement_description' => null,
            ]);

        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', false),
            );
    }

    public function test_pre_identity_records_are_not_import_duplicates(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();

        FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($incomeCategory->budget)
            ->for($incomeCategory)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Salary',
                'raw_statement_description' => 'Salary',
                'import_identity_digest' => null,
            ]);

        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', false),
            );
    }

    public function test_editing_live_fields_does_not_change_import_identity_match(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2026-03-20',
                'amount_grosze' => 300000,
                'description' => 'Edited salary',
                'counterparty' => 'Edited counterparty',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
            ]);

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true),
            );
    }

    public function test_other_users_committed_rows_do_not_flag_preview(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create([
            'opening_date' => '2026-01-01',
        ]);
        $otherBudget = Budget::factory()->for($otherUser)->execution()->create(['year' => 2026]);
        $otherCategory = Category::factory()->for($otherBudget)->income()->create();

        FinancialOperation::factory()
            ->for($otherUser)
            ->for($otherAccount)
            ->for($otherBudget)
            ->for($otherCategory)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Salary',
                'raw_statement_description' => 'Salary',
                'import_identity_account_id' => $otherAccount->id,
                'import_identity_date' => '2026-03-15',
                'import_identity_amount_grosze' => 250000,
                'import_identity_digest' => app(ImportIdentityDigest::class)->compute(
                    $otherUser->id,
                    $otherAccount->id,
                    '2026-03-15',
                    250000,
                    'Salary',
                )['digest'],
            ]);

        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', false),
            );
    }

    public function test_invalid_rows_win_over_import_duplicate_overlay(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $csv = <<<'CSV'
date,description,amount
2026-07-01,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.valid', false)
                ->where('rows.0.import_duplicate', false),
            );
    }

    public function test_own_transfer_import_stores_identity_on_imported_account_slot_only(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,To savings,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ]);

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->assertSame($account->id, $transfer->source_import_identity_account_id);
        $this->assertSame('2026-03-15', $transfer->source_import_identity_date->toDateString());
        $this->assertSame(-10000, $transfer->source_import_identity_amount_grosze);
        $this->assertNotNull($transfer->source_import_identity_digest);
        $this->assertNull($transfer->destination_import_identity_digest);
    }

    public function test_deleting_imported_record_drops_identity_and_allows_fresh_import(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('incomes.destroy', $operation));

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', false),
            );
    }

    public function test_every_preview_row_sharing_committed_digest_is_flagged(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $duplicateCsv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $duplicateCsv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true)
                ->where('rows.1.import_duplicate', true),
            );
    }

    public function test_own_transfer_reimport_is_flagged_as_duplicate(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createImportFixture();
        $otherAccount = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,To savings,-100.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $otherAccount->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true),
            );
    }

    public function test_user_cannot_set_raw_statement_description_on_manual_create(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();

        $this->actingAs($user)
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Manual income',
                'counterparty' => 'Acme Corp',
                'raw_statement_description' => 'Should not persist',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
            ])
            ->assertSessionHasErrors('raw_statement_description');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_user_cannot_set_import_identity_fields_on_manual_create(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();

        $this->actingAs($user)
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 250000,
                'description' => 'Manual income',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'import_identity_digest' => 'forged-digest',
            ])
            ->assertSessionHasErrors('import_identity_digest');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_cross_account_import_flags_pair_candidate_for_financial_operation(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, $incomeCategory, $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', false)
                ->where('rows.0.import_pair_candidate', true)
                ->has('rows.0.pair_candidates', 1)
                ->where('rows.0.pair_candidates.0.kind', 'financial_operation')
                ->where('rows.0.pair_candidates.0.account_id', $checking->id),
            );
    }

    public function test_same_account_exact_row_is_duplicate_not_pair_candidate(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory, $expenseCategory] = $this->createImportFixture();
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Salary,2500.00
CSV;

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $account, $csv);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true)
                ->where('rows.0.import_pair_candidate', false),
            );
    }

    public function test_pair_candidate_outside_date_window_is_not_flagged(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-20,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_pair_candidate', false),
            );
    }

    public function test_pair_candidate_converts_financial_operation_into_own_transfer(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $firstLeg = FinancialOperation::query()->firstOrFail();
        $expenseCategory->refresh();
        $this->assertSame(10000, $expenseCategory->execution_grosze);
        $this->assertSame(90000, $checking->refresh()->current_balance_grosze);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'financial_operation',
                        'pair_first_leg_id' => $firstLeg->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertDatabaseCount('own_transfers', 1);

        $transfer = OwnTransfer::query()->firstOrFail();
        $this->assertSame($checking->id, $transfer->source_account_id);
        $this->assertSame($savings->id, $transfer->destination_account_id);
        $this->assertSame(10000, $transfer->amount_grosze);
        $this->assertSame('Transfer to savings', $transfer->description);
        $this->assertSame('2026-03-15', $transfer->transfer_date->toDateString());
        $this->assertNull($transfer->counterparty ?? null);
        $this->assertNotNull($transfer->source_import_identity_digest);
        $this->assertNotNull($transfer->destination_import_identity_digest);
        $this->assertSame(90000, $checking->refresh()->current_balance_grosze);
        $this->assertSame(10000, $savings->refresh()->current_balance_grosze);
        $this->assertSame(0, $expenseCategory->refresh()->execution_grosze);
    }

    public function test_pair_candidate_can_be_ignored_and_booked_as_income(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, $incomeCategory, $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 2);
        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_preview_with_only_unchecked_pair_candidates_refuses_submit(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture();
        $savings = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => false,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');
    }

    public function test_cross_account_import_flags_pair_candidate_for_own_transfer(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', false)
                ->where('rows.0.import_pair_candidate', true)
                ->has('rows.0.pair_candidates', 1)
                ->where('rows.0.pair_candidates.0.kind', 'own_transfer')
                ->where('rows.0.pair_candidates.0.account_id', $checking->id),
            );
    }

    public function test_pair_candidate_attaches_second_identity_to_own_transfer_without_moving_balances(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 0,
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $transfer = OwnTransfer::query()->firstOrFail();
        $this->assertSame(90000, $checking->refresh()->current_balance_grosze);
        $this->assertSame(10000, $savings->refresh()->current_balance_grosze);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'own_transfer',
                        'pair_first_leg_id' => $transfer->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('own_transfers', 1);

        $transfer->refresh();
        $this->assertNotNull($transfer->source_import_identity_digest);
        $this->assertNotNull($transfer->destination_import_identity_digest);
        $this->assertSame('Transfer to savings', $transfer->source_raw_statement_description);
        $this->assertSame('Transfer from checking', $transfer->destination_raw_statement_description);
        $this->assertSame(90000, $checking->refresh()->current_balance_grosze);
        $this->assertSame(10000, $savings->refresh()->current_balance_grosze);
    }

    public function test_own_transfer_with_two_identities_is_never_pair_candidate(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'own_transfer',
                        'pair_first_leg_id' => $transfer->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true)
                ->where('rows.0.import_pair_candidate', false),
            );
    }

    public function test_reimport_of_either_leg_after_pair_is_import_duplicate(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'own_transfer',
                        'pair_first_leg_id' => $transfer->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_duplicate', true),
            );
    }

    public function test_delete_paired_own_transfer_drops_both_identities_and_reverts_balances(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'own_transfer',
                        'pair_first_leg_id' => $transfer->id,
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->delete(route('own-transfers.destroy', $transfer->refresh()));

        $this->assertDatabaseCount('own_transfers', 0);
        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertSame(100000, $checking->refresh()->current_balance_grosze);
        $this->assertSame(0, $savings->refresh()->current_balance_grosze);
    }

    public function test_commit_rejects_income_when_own_transfer_in_candidate_set(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, $incomeCategory] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');
    }

    public function test_commit_rejects_financial_operation_pair_when_own_transfer_in_candidate_set(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Duplicate expense view,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $firstLeg = FinancialOperation::query()->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'financial_operation',
                        'pair_first_leg_id' => $firstLeg->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');
    }

    public function test_pairing_does_not_retarget_own_transfer(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);
        $cash = Account::factory()->for($user)->cash()->create([
            'name' => 'Cash',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $cash->id,
                        'pair_first_leg_kind' => 'own_transfer',
                        'pair_first_leg_id' => $transfer->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');
    }

    public function test_user_can_leave_own_transfer_pair_unchecked_and_attach_later(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer to savings,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $savings->id,
                    ],
                ],
            ]);

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => false,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer from checking,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'own_transfer',
                        'pair_first_leg_id' => $transfer->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $transfer->refresh();
        $this->assertNotNull($transfer->destination_import_identity_digest);
    }

    public function test_preview_returns_multiple_pair_candidates_for_one_row(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-14,Transfer out A,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-16,Transfer out B,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-15,Transfer in,100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_pair_candidate', true)
                ->has('rows.0.pair_candidates', 2)
                ->where('rows.0.pair_candidates.0.account_id', $checking->id)
                ->where('rows.0.pair_candidates.1.account_id', $checking->id),
            );
    }

    public function test_commit_pairs_chosen_first_leg_when_multiple_candidates_exist(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-14,Transfer out A,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-16,Transfer out B,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $firstLeg = FinancialOperation::query()->orderBy('id')->firstOrFail();
        $secondLeg = FinancialOperation::query()->orderByDesc('id')->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-15,Transfer in,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'financial_operation',
                        'pair_first_leg_id' => $secondLeg->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 1);
        $this->assertDatabaseHas('financial_operations', [
            'id' => $firstLeg->id,
        ]);
        $this->assertDatabaseCount('own_transfers', 1);

        $transfer = OwnTransfer::query()->firstOrFail();
        $this->assertSame('Transfer out B', $transfer->description);
        $this->assertSame('2026-03-16', $transfer->transfer_date->toDateString());
    }

    public function test_commit_rejects_two_selected_rows_pairing_same_first_leg(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer out,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $firstLeg = FinancialOperation::query()->firstOrFail();

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-15,Transfer in A,100.00
2026-03-16,Transfer in B,100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'financial_operation',
                        'pair_first_leg_id' => $firstLeg->id,
                    ],
                    [
                        'index' => 1,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'financial_operation',
                        'pair_first_leg_id' => $firstLeg->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('financial_operations', 1);
        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_archived_first_leg_account_appears_in_other_accounts_and_can_pair(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $checking, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Checking',
            'current_balance_grosze' => 100000,
        ]);
        $savings = Account::factory()->for($user)->create([
            'name' => 'Savings',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $checking, <<<'CSV'
date,description,amount
2026-03-15,Transfer out,-100.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $firstLeg = FinancialOperation::query()->firstOrFail();
        $checking->update(['archived_at' => now()]);

        $this->previewCsv($user, $savings, <<<'CSV'
date,description,amount
2026-03-16,Transfer in,100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_pair_candidate', true)
                ->has('otherAccounts', 1)
                ->where('otherAccounts.0.id', $checking->id),
            );

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'own_transfer',
                        'other_account_id' => $checking->id,
                        'pair_first_leg_kind' => 'financial_operation',
                        'pair_first_leg_id' => $firstLeg->id,
                    ],
                ],
            ])
            ->assertRedirect(route('operations'));

        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertDatabaseCount('own_transfers', 1);
    }

    public function test_cash_account_first_leg_can_be_pair_candidate(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $bank, , $expenseCategory] = $this->createImportFixture([
            'name' => 'Bank',
        ]);
        $cash = Account::factory()->for($user)->cash()->create([
            'name' => 'Cash',
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
        ]);

        $this->previewCsv($user, $bank, <<<'CSV'
date,description,amount
2026-03-15,ATM withdrawal,-200.00
CSV);

        $this->actingAs($user)
            ->post(route('operations.import.store'), [
                'rows' => [
                    [
                        'index' => 0,
                        'selected' => true,
                        'type' => 'expense',
                        'category_id' => $expenseCategory->id,
                    ],
                ],
            ]);

        $this->previewCsv($user, $cash, <<<'CSV'
date,description,amount
2026-03-15,Cash deposit,200.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.import_pair_candidate', true),
            );
    }

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @return array{0: User, 1: Account, 2: Category, 3: Category}
     */
    private function createImportFixture(array $accountAttributes = []): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(array_merge([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ], $accountAttributes));
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 500000,
            'execution_grosze' => 0,
        ]);
        $expenseCategory = Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 500000,
            'execution_grosze' => 0,
        ]);

        return [$user, $account, $incomeCategory, $expenseCategory];
    }

    private function previewCsv(User $user, Account $account, string $csv): void
    {
        $this->actingAs($user)
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

    private function bnpParibasFixtureUpload(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/bnp-paribas-basic.xlsx'),
            'bnp-paribas-basic.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            UPLOAD_ERR_OK,
            true,
        );
    }

    private function bnpParibasSplitPartiesUpload(): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ExcelExportFile');
        $sheet->fromArray([
            [
                'Data transakcji',
                'Data zaksięgowania',
                'Data odrzucenia',
                'Kwota',
                'Waluta',
                'Nadawca',
                'Odbiorca',
                'Opis',
                'Produkt',
                'Typ transakcji',
                'Kwota zlecenia',
                'Waluta zlecenia',
                'Status',
                'Saldo po transakcji',
            ],
            [46272, 46272, null, -37.35, 'PLN', 'Account Holder', 'Grocery Store', 'BLIK payment', 'Konto Osobiste', 'Transakcja BLIK', -37.35, 'PLN', 'Zrealizowana', 308.58],
            [46272, 46272, null, 5000, 'PLN', 'Employer Sp. z o.o.', 'Account Holder', 'Salary September', 'Konto Osobiste', 'Przelew przychodzący', 5000, 'PLN', 'Zrealizowana', 5308.58],
            [46272, null, null, -38.36, 'PLN', null, null, 'STOKROTKA NR 06 LUBLIN', 'Konto Osobiste', 'Blokada środków', -38.36, 'PLN', 'W trakcie realizacji', null],
            [46271, 46272, null, -1.99, 'PLN', '48160000000000000000000000', null, 'Account fee', 'Konto Osobiste', 'Prowizje i opłaty', -1.99, 'PLN', 'Zrealizowana', 872.27],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'bnp-split-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'paribas-osobiste.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            UPLOAD_ERR_OK,
            true,
        );
    }
}
