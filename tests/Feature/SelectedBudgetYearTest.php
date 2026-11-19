<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use App\Support\SelectedBudgetYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class SelectedBudgetYearTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<int>  $years
     */
    private function resolveFor(array $years, string $today, ?int $sessionYear = null): ?int
    {
        $this->travelTo($today);
        $user = User::factory()->create();

        foreach ($years as $year) {
            Budget::factory()->for($user)->create(['year' => $year]);
        }

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $session = new Store('test', new ArraySessionHandler(120));

        if ($sessionYear !== null) {
            $session->put('budget_year', $sessionYear);
        }

        $request->setLaravelSession($session);

        return SelectedBudgetYear::resolve($request);
    }

    public function test_current_year_wins_over_an_older_one(): void
    {
        $this->assertSame(2026, $this->resolveFor([2025, 2026], '2026-06-15'));
    }

    public function test_current_year_wins_over_a_budget_in_preparation(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2026-12-20'));
    }

    public function test_newest_year_not_later_than_today_is_used_when_current_has_no_budget(): void
    {
        $this->assertSame(2026, $this->resolveFor([2025, 2026], '2027-01-05'));
    }

    public function test_oldest_year_is_used_when_all_are_in_the_future(): void
    {
        $this->assertSame(2027, $this->resolveFor([2027, 2028], '2026-06-15'));
    }

    public function test_session_year_takes_precedence(): void
    {
        $this->assertSame(2025, $this->resolveFor([2025, 2026], '2026-06-15', 2025));
    }

    public function test_session_year_without_a_budget_is_skipped(): void
    {
        $this->assertSame(2026, $this->resolveFor([2025, 2026], '2026-06-15', 2024));
    }

    public function test_january_without_a_session_year_selects_the_new_year_whatever_its_phase(): void
    {
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2027-01-05'));
    }

    /**
     * A known gap (rehearsal.md, luka 3, ADR-0075): the session year wins, so a session that
     * still holds 2026 keeps showing 2026 in January. The phase of 2027 is not read.
     */
    public function test_session_year_of_the_old_year_stays_selected_in_january_known_gap(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2027-01-05', 2026));
    }

    public function test_no_budgets_gives_null(): void
    {
        $this->assertNull($this->resolveFor([], '2026-06-15'));
    }
}
