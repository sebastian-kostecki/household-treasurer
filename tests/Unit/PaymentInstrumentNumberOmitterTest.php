<?php

namespace Tests\Unit;

use App\Support\CsvImport\DefaultPaymentInstrumentNumberOmitter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentInstrumentNumberOmitterTest extends TestCase
{
    private DefaultPaymentInstrumentNumberOmitter $omitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->omitter = new DefaultPaymentInstrumentNumberOmitter;
    }

    #[Test]
    public function keeps_hyphenated_reference_that_is_not_a_masked_pan(): void
    {
        $this->assertSame(
            '2102625950-0081491110',
            $this->omitter->omit('2102625950-0081491110'),
        );
    }

    #[Test]
    public function keeps_sixteen_digit_run_that_fails_luhn(): void
    {
        $this->assertSame(
            '1234567890123456',
            $this->omitter->omit('1234567890123456'),
        );
    }

    #[Test]
    public function omits_underscored_masked_pan(): void
    {
        $this->assertSame(
            'SEBASTIAN KOSTECKI',
            $this->omitter->omit('516842______4681 SEBASTIAN KOSTECKI'),
        );
    }

    #[Test]
    public function omits_spaced_polish_nrb_with_pl_prefix(): void
    {
        $this->assertSame(
            'Payment',
            $this->omitter->omit('Payment PL61 1090 1014 0000 0712 1981 2874'),
        );
    }
}
