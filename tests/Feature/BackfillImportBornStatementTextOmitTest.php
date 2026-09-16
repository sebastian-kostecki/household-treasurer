<?php

namespace Tests\Feature;

use App\Actions\BackfillImportBornStatementTextOmit;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackfillImportBornStatementTextOmitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function backfill_rewrites_import_born_financial_operation_live_fields(): void
    {
        $raw = "48160000000000000000000000\nAccount fee";
        $operation = FinancialOperation::factory()->expense()->create([
            'description' => $raw,
            'counterparty' => '48160000000000000000000000',
            'raw_statement_description' => $raw,
            'import_identity_digest' => 'existing-digest',
        ]);

        app(BackfillImportBornStatementTextOmit::class)->handle();

        $operation->refresh();

        $this->assertSame('Account fee', $operation->description);
        $this->assertNull($operation->counterparty);
        $this->assertSame($raw, $operation->raw_statement_description);
        $this->assertSame('existing-digest', $operation->import_identity_digest);
    }

    #[Test]
    public function backfill_rewrites_import_born_own_transfer_and_capital_movement_descriptions(): void
    {
        $transfer = OwnTransfer::factory()->create([
            'description' => '48160000000000000000000000 Transfer',
            'source_raw_statement_description' => '48160000000000000000000000 Transfer',
        ]);

        $movement = CapitalMovement::factory()->create([
            'description' => '48160000000000000000000000 Deposit',
            'raw_statement_description' => '48160000000000000000000000 Deposit',
        ]);

        app(BackfillImportBornStatementTextOmit::class)->handle();

        $transfer->refresh();
        $movement->refresh();

        $this->assertSame('Transfer', $transfer->description);
        $this->assertSame('48160000000000000000000000 Transfer', $transfer->source_raw_statement_description);
        $this->assertSame('Deposit', $movement->description);
        $this->assertSame('48160000000000000000000000 Deposit', $movement->raw_statement_description);
    }

    #[Test]
    public function backfill_skips_hand_entered_records_and_empty_results(): void
    {
        $manual = FinancialOperation::factory()->expense()->create([
            'description' => '48160000000000000000000000',
            'counterparty' => null,
            'raw_statement_description' => null,
        ]);

        $onlyNumbers = FinancialOperation::factory()->expense()->create([
            'description' => '48160000000000000000000000',
            'counterparty' => '48160011111111111111111111',
            'raw_statement_description' => "48160000000000000000000000\n48160011111111111111111111",
        ]);

        app(BackfillImportBornStatementTextOmit::class)->handle();

        $manual->refresh();
        $onlyNumbers->refresh();

        $this->assertSame('48160000000000000000000000', $manual->description);
        $this->assertSame('48160000000000000000000000', $onlyNumbers->description);
        $this->assertSame('48160011111111111111111111', $onlyNumbers->counterparty);
    }

    #[Test]
    public function second_backfill_run_is_a_no_op_on_already_clean_text(): void
    {
        $operation = FinancialOperation::factory()->expense()->create([
            'description' => 'Account fee',
            'counterparty' => null,
            'raw_statement_description' => "48160000000000000000000000\nAccount fee",
        ]);

        app(BackfillImportBornStatementTextOmit::class)->handle();
        $operation->refresh();
        $updatedAt = $operation->updated_at;

        app(BackfillImportBornStatementTextOmit::class)->handle();
        $operation->refresh();

        $this->assertSame('Account fee', $operation->description);
        $this->assertTrue($operation->updated_at->equalTo($updatedAt));
    }
}
