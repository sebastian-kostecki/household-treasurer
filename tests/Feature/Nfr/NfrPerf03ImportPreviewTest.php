<?php

namespace Tests\Feature\Nfr;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * NFR-PERF-03 acceptance test: Import preview with category suggestions.
 *
 * Run in Sail on PostgreSQL:
 * ./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml
 */
#[Group('nfr')]
class NfrPerf03ImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const int HISTORY_COUNT = 10_000;

    private const int SHOPS_COUNT = 200;

    private const int CATEGORIES_COUNT = 20;

    private const int CSV_ROWS_COUNT = 300;

    private const float MAX_SECONDS = 2.0;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped(
                'NFR-PERF-03 runs on PostgreSQL only. Use: ./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml',
            );
        }

        $this->withoutVite();
    }

    public function test_nfr_perf_03_import_preview_with_large_history_completes_within_two_seconds(): void
    {
        $this->assertPreviewWithinLimit(dominantShop: false);
    }

    public function test_nfr_perf_03_import_preview_with_one_dominant_shop_completes_within_two_seconds(): void
    {
        $this->assertPreviewWithinLimit(dominantShop: true);
    }

    /**
     * With $dominantShop, a third of the history and of the CSV rows name one
     * shop, so every such row has thousands of neighbour candidates.
     */
    private function assertPreviewWithinLimit(bool $dominantShop): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2025-01-01',
        ]);

        $categoryNames = array_map(
            fn (int $index): string => "Kategoria {$index}",
            range(0, self::CATEGORIES_COUNT - 1),
        );

        $categoryIdsByYear = [];
        $budgetIdsByYear = [];

        foreach ([2025, 2026] as $year) {
            $budget = Budget::factory()->for($user)->create([
                'year' => $year,
                'phase' => BudgetPhase::Execution,
            ]);
            $budgetIdsByYear[$year] = $budget->id;

            foreach ($categoryNames as $index => $name) {
                $categoryIdsByYear[$year][$index] = BudgetLine::factory()->for($budget)->expense()->create([
                    'name' => $name,
                ])->id;
            }
        }

        $this->insertHistory($user, $account, $budgetIdsByYear, $categoryIdsByYear, $dominantShop);

        $csv = $this->buildCsv($dominantShop);

        $startedAt = microtime(true);

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

        $elapsedSeconds = microtime(true) - $startedAt;

        $this->assertLessThanOrEqual(
            self::MAX_SECONDS,
            $elapsedSeconds,
            "Import preview took {$elapsedSeconds}s; expected <= ".self::MAX_SECONDS.'s.',
        );

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->has('rows', self::CSV_ROWS_COUNT);

                $suggested = collect($page->toArray()['props']['rows'])
                    ->filter(fn (array $row): bool => $row['category_suggestion']['expense'] !== null)
                    ->count();

                $this->assertGreaterThanOrEqual(1, $suggested, 'No preview row received a category suggestion.');
            });
    }

    /**
     * @param  array<int, int>  $budgetIdsByYear
     * @param  array<int, array<int, int>>  $categoryIdsByYear
     */
    private function insertHistory(User $user, Account $account, array $budgetIdsByYear, array $categoryIdsByYear, bool $dominantShop): void
    {
        $now = now();
        $rows = [];

        for ($index = 0; $index < self::HISTORY_COUNT; $index++) {
            $shop = $dominantShop && $index % 3 === 0 ? 0 : $index % self::SHOPS_COUNT;
            $year = $index % 2 === 0 ? 2025 : 2026;
            $month = 1 + ($index % 12);
            $day = 1 + ($index % 28);
            $date = sprintf('%d-%02d-%02d', $year, $month, $day);

            $rows[] = [
                'user_id' => $user->id,
                'type' => FinancialOperationType::Expense->value,
                'account_id' => $account->id,
                'budget_line_id' => $categoryIdsByYear[$year][$shop % self::CATEGORIES_COUNT],
                'budget_id' => $budgetIdsByYear[$year],
                'operation_date' => $date,
                'amount_minor_units' => 1000 + ($index % 100),
                'description' => $this->shopName($shop),
                'raw_statement_description' => 'PLATNOSC KARTA '.$this->shopName($shop).' JAN KOWALSKI WARSZAWA POL PLN',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('financial_operations')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('financial_operations')->insert($rows);
        }
    }

    private function buildCsv(bool $dominantShop): string
    {
        $lines = ['date,description,amount'];

        for ($index = 0; $index < self::CSV_ROWS_COUNT; $index++) {
            $day = 1 + ($index % 28);
            // Every fifth row names a shop absent from the history.
            $name = match (true) {
                $index % 5 === 0 => 'NOWYSKLEP'.chr(65 + ($index % 26)),
                $dominantShop && $index % 3 === 0 => $this->shopName(0),
                default => $this->shopName(($index * 7) % self::SHOPS_COUNT),
            };
            $amount = number_format(-(10 + ($index % 90)) - 0.5, 2, '.', '');

            $lines[] = sprintf('2026-03-%02d,PLATNOSC KARTA %s JAN KOWALSKI WARSZAWA POL PLN %d,%s', $day, $name, $index, $amount);
        }

        return implode("\n", $lines);
    }

    /**
     * Letters-only unique shop name (digits are dropped by the tokenizer).
     */
    private function shopName(int $shop): string
    {
        return 'SKLEP'.chr(65 + intdiv($shop, 26)).chr(65 + ($shop % 26));
    }
}
