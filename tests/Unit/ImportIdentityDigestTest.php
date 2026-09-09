<?php

namespace Tests\Unit;

use App\Support\CsvImport\ImportIdentityDigest;
use Tests\TestCase;

class ImportIdentityDigestTest extends TestCase
{
    public function test_digest_is_deterministic_for_same_inputs(): void
    {
        $digest = new ImportIdentityDigest;

        $first = $digest->compute(1, 10, '2026-03-15', -4550, "Salary\nAcme Corp");
        $second = $digest->compute(1, 10, '2026-03-15', -4550, "Salary\nAcme Corp");

        $this->assertSame($first['digest'], $second['digest']);
        $this->assertSame(10, $first['account_id']);
        $this->assertSame('2026-03-15', $first['date']);
        $this->assertSame(-4550, $first['amount_minor_units']);
    }

    public function test_digest_changes_when_user_or_account_differs(): void
    {
        $digest = new ImportIdentityDigest;

        $base = $digest->compute(1, 10, '2026-03-15', -4550, 'Salary');
        $otherUser = $digest->compute(2, 10, '2026-03-15', -4550, 'Salary');
        $otherAccount = $digest->compute(1, 11, '2026-03-15', -4550, 'Salary');

        $this->assertNotSame($base['digest'], $otherUser['digest']);
        $this->assertNotSame($base['digest'], $otherAccount['digest']);
    }
}
