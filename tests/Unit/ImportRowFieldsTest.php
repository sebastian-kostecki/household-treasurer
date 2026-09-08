<?php

namespace Tests\Unit;

use App\Support\CsvImport\ImportRowFields;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportRowFieldsTest extends TestCase
{
    #[Test]
    public function mbank_maps_operation_description_to_description_and_snapshot(): void
    {
        $fields = ImportRowFields::fromMbank('Payment at C&A MODE');

        $this->assertNull($fields['counterparty']);
        $this->assertSame('Payment at C&A MODE', $fields['description']);
        $this->assertSame('Payment at C&A MODE', $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_maps_sender_and_opis_separately(): void
    {
        $fields = ImportRowFields::fromBnpParibas('JMP S.A. BIEDRO', 'Purchase');

        $this->assertSame('JMP S.A. BIEDRO', $fields['counterparty']);
        $this->assertSame('Purchase', $fields['description']);
        $this->assertSame("JMP S.A. BIEDRO\nPurchase", $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_uses_sender_for_description_and_counterparty_when_opis_is_empty(): void
    {
        $fields = ImportRowFields::fromBnpParibas('Employer', null);

        $this->assertSame('Employer', $fields['counterparty']);
        $this->assertSame('Employer', $fields['description']);
        $this->assertSame('Employer', $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_allows_empty_counterparty_when_opis_is_present(): void
    {
        $fields = ImportRowFields::fromBnpParibas(null, 'Przedszkole Niepubliczne');

        $this->assertNull($fields['counterparty']);
        $this->assertSame('Przedszkole Niepubliczne', $fields['description']);
        $this->assertSame('Przedszkole Niepubliczne', $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_returns_empty_description_when_sender_and_opis_are_empty(): void
    {
        $fields = ImportRowFields::fromBnpParibas(null, '');

        $this->assertNull($fields['counterparty']);
        $this->assertNull($fields['description']);
        $this->assertNull($fields['raw_statement_description']);
    }

    #[Test]
    public function other_maps_optional_counterparty_and_builds_snapshot(): void
    {
        $fields = ImportRowFields::fromOther('Salary', 'Acme Corp');

        $this->assertSame('Acme Corp', $fields['counterparty']);
        $this->assertSame('Salary', $fields['description']);
        $this->assertSame("Salary\nAcme Corp", $fields['raw_statement_description']);
    }

    #[Test]
    public function other_works_without_counterparty_column(): void
    {
        $fields = ImportRowFields::fromOther('Salary');

        $this->assertNull($fields['counterparty']);
        $this->assertSame('Salary', $fields['description']);
        $this->assertSame('Salary', $fields['raw_statement_description']);
    }
}
