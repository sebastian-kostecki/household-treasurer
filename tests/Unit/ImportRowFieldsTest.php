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
    public function mbank_omits_trailing_nrb_from_live_description(): void
    {
        $raw = 'C&A MODE GMBH PRZELEW 28114020620000123471001001';
        $fields = ImportRowFields::fromMbank($raw);

        $this->assertSame('C&A MODE GMBH PRZELEW', $fields['description']);
        $this->assertSame($raw, $fields['raw_statement_description']);
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
    public function bnp_omits_nrb_only_counterparty(): void
    {
        $fields = ImportRowFields::fromBnpParibas('48160000000000000000000000', 'Account fee');

        $this->assertNull($fields['counterparty']);
        $this->assertSame('Account fee', $fields['description']);
        $this->assertSame("48160000000000000000000000\nAccount fee", $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_omits_card_numbers_from_opis_and_collapses_blank_lines(): void
    {
        $opis = "48160014461822964860000001\n516842------4681 SEBASTIAN KOSTECKI Lublin OWOCOWA BAJKA POL 24,03 PLN 2026-09-14";
        $fields = ImportRowFields::fromBnpParibas(null, $opis);

        $this->assertSame(
            'SEBASTIAN KOSTECKI Lublin OWOCOWA BAJKA POL 24,03 PLN 2026-09-14',
            $fields['description'],
        );
        $this->assertSame($opis, $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_falls_back_to_counterparty_when_opis_is_only_an_nrb(): void
    {
        $fields = ImportRowFields::fromBnpParibas('Jan Nowak', '48160000000000000000000000');

        $this->assertSame('Jan Nowak', $fields['counterparty']);
        $this->assertSame('Jan Nowak', $fields['description']);
        $this->assertSame("Jan Nowak\n48160000000000000000000000", $fields['raw_statement_description']);
    }

    #[Test]
    public function bnp_leaves_description_empty_when_only_payment_instrument_numbers_remain(): void
    {
        $fields = ImportRowFields::fromBnpParibas('48160000000000000000000000', '48160011111111111111111111');

        $this->assertNull($fields['counterparty']);
        $this->assertNull($fields['description']);
        $this->assertSame(
            "48160000000000000000000000\n48160011111111111111111111",
            $fields['raw_statement_description'],
        );
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

    #[Test]
    public function other_omits_iban_from_live_fields(): void
    {
        $fields = ImportRowFields::fromOther(
            'Payment',
            'ACME PL61109010140000071219812874',
        );

        $this->assertSame('ACME', $fields['counterparty']);
        $this->assertSame('Payment', $fields['description']);
        $this->assertSame(
            "Payment\nACME PL61109010140000071219812874",
            $fields['raw_statement_description'],
        );
    }

    #[Test]
    public function truncate_runs_after_omit(): void
    {
        $padding = str_repeat('x', 240);
        $raw = $padding.'48160000000000000000000000';
        $fields = ImportRowFields::fromMbank($raw);

        $this->assertSame($padding, $fields['description']);
        $this->assertSame($raw, $fields['raw_statement_description']);
        $this->assertSame(240, mb_strlen((string) $fields['description']));
    }
}
