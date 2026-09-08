<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_page_exposes_bank_and_mark_url_for_account_pickers(): void
    {
        $user = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'mBank konto',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
        ]);
        Account::factory()->for($user)->cash()->create([
            'name' => 'Gotówka',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('accounts', 2)
                ->where('accounts.0.name', 'Gotówka')
                ->where('accounts.0.type', AccountType::Cash->value)
                ->where('accounts.0.bank', null)
                ->where('accounts.0.mark_url', null)
                ->where('accounts.1.name', 'mBank konto')
                ->where('accounts.1.type', AccountType::Bank->value)
                ->where('accounts.1.bank', Bank::Mbank->value)
                ->where('accounts.1.mark_url', url('/marks/mbank.jpg')),
            );
    }

    public function test_import_create_exposes_bank_and_mark_url_for_account_pickers(): void
    {
        $user = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'BNP konto',
            'type' => AccountType::Bank,
            'bank' => Bank::BnpParibas,
        ]);

        $this->actingAs($user)
            ->get(route('operations.import.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('OperationsImport')
                ->has('accounts', 1)
                ->where('accounts.0.name', 'BNP konto')
                ->where('accounts.0.type', AccountType::Bank->value)
                ->where('accounts.0.bank', Bank::BnpParibas->value)
                ->where('accounts.0.mark_url', url('/marks/bnp-paribas.jpg')),
            );
    }

    public function test_import_preview_exposes_bank_and_mark_url_for_account_pickers(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $importAccount = Account::factory()->for($user)->create([
            'name' => 'Import konto',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
            'opening_date' => '2026-01-01',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Inne bank',
            'type' => AccountType::Bank,
            'bank' => Bank::Other,
            'opening_date' => '2026-01-01',
        ]);
        $csv = <<<'CSV'
date,description,amount
2026-03-15,Transfer,-100.00
CSV;

        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $importAccount->id,
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
                ->component('OperationsImportPreview')
                ->where('account.id', $importAccount->id)
                ->where('account.name', 'Import konto')
                ->where('account.type', AccountType::Bank->value)
                ->where('account.bank', Bank::Mbank->value)
                ->where('account.mark_url', url('/marks/mbank.jpg'))
                ->has('otherAccounts', 1)
                ->where('otherAccounts.0.name', 'Inne bank')
                ->where('otherAccounts.0.type', AccountType::Bank->value)
                ->where('otherAccounts.0.bank', Bank::Other->value)
                ->where('otherAccounts.0.mark_url', null),
            );
    }
}
