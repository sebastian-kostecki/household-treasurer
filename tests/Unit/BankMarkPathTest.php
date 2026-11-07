<?php

namespace Tests\Unit;

use App\Enums\Bank;
use PHPUnit\Framework\TestCase;

class BankMarkPathTest extends TestCase
{
    public function test_every_mark_path_points_to_an_existing_file(): void
    {
        foreach (Bank::cases() as $bank) {
            $path = $bank->markPath();

            if ($path === null) {
                continue;
            }

            $this->assertFileExists(
                dirname(__DIR__, 2).'/public'.$path,
                "Bank {$bank->name} points to a missing logo file.",
            );
        }
    }

    public function test_banks_without_a_real_logo_have_no_mark_path(): void
    {
        foreach ([Bank::Ing, Bank::Pekao, Bank::Santander, Bank::Other] as $bank) {
            $this->assertNull($bank->markPath(), "Bank {$bank->name} must show the short mark (ADR-0054).");
        }
    }
}
