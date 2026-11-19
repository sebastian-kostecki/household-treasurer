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
    private function resolveFor(array $years, string $today, ?int $sessionYear = null, int|string|null $chosenIn = null): ?int
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

        if ($chosenIn !== null) {
            $session->put('budget_year_chosen_in', $chosenIn);
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
     * ADR-0111: the year that was current when chosen gives way to the new current year once the
     * App Day year turns. The phase of 2027 is not read.
     */
    public function test_the_current_year_chosen_in_the_session_gives_way_to_the_new_year_in_january(): void
    {
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2027-01-05', 2026, 2026));
    }

    public function test_the_current_year_chosen_in_the_session_changes_at_polish_midnight(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2026-12-31 22:59:59', 2026, 2026));
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2026-12-31 23:00:00', 2026, 2026));
    }

    public function test_a_past_year_chosen_for_browsing_stays_after_the_year_turn(): void
    {
        $this->assertSame(2023, $this->resolveFor([2023, 2026, 2027], '2027-01-05', 2023, 2026));
    }

    public function test_the_next_year_chosen_in_december_stays_in_january(): void
    {
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2027-01-05', 2027, 2026));
    }

    public function test_the_old_year_chosen_again_in_january_stays(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2027-01-05', 2026, 2027));
    }

    public function test_a_session_year_without_the_choice_year_stays_selected(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2027-01-05', 2026));
    }

    /**
     * ADR-0111: only an App Day year later than the choice year turns it over. A clock moved back
     * after a rehearsal (`app:rehearsal-clock --off`) keeps the year chosen in the later year.
     */
    public function test_a_year_chosen_in_a_later_app_day_year_stays_when_the_clock_goes_back(): void
    {
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2026-11-10', 2027, 2027));
    }

    public function test_a_choice_year_stored_as_a_digit_string_still_counts(): void
    {
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2027-01-05', 2026, '2026'));
    }

    public function test_a_malformed_choice_year_keeps_the_session_year(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2027-01-05', 2026, '2026x'));
    }

    public function test_the_default_year_changes_at_polish_midnight(): void
    {
        $this->assertSame(2026, $this->resolveFor([2026, 2027], '2026-12-31 22:59:59'));
        $this->assertSame(2027, $this->resolveFor([2026, 2027], '2026-12-31 23:00:00'));
    }

    public function test_no_budgets_gives_null(): void
    {
        $this->assertNull($this->resolveFor([], '2026-06-15'));
    }
}
