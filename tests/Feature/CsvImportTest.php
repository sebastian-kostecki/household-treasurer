<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
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
                ->where('rows.0.valid', false)
                ->where('rows.0.invalid_reason', __('Invalid or missing date.'))
                ->where('rows.1.valid', false)
                ->where('rows.2.valid', false)
                ->where('rows.3.valid', false),
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
                        'selected' => false,
                        'type' => 'income',
                        'category_id' => $incomeCategory->id,
                    ],
                    [
                        'index' => 1,
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
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_import_requires_duplicate_warning_acknowledgement(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $incomeCategory] = $this->createImportFixture();
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
            ]);

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
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertSessionHasErrors('account_id');
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
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertRedirect(route('operations.import.preview'));
    }
}
