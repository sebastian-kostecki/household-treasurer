<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthPlanProposal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthPlanProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_current_and_future_months_without_plans_use_average_then_copy(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $november = $proposal->reading(11);
        $this->assertSame('proposal', $november->state);
        $this->assertSame(8_333, $november->amounts[$food->id]);

        $december = $proposal->reading(12);
        $this->assertSame('proposal', $december->state);
        $this->assertSame(8_333, $december->amounts[$food->id]);
    }

    public function test_unapproved_past_month_is_none_and_december_gets_the_remainder(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 12, 5));

        $november = $proposal->reading(11);
        $this->assertSame('none', $november->state);
        $this->assertSame([], $november->amounts);

        $this->assertSame(100_001 - 11 * 8_333, $proposal->reading(12)->amounts[$food->id]);
        $this->assertSame(8_338, $proposal->reading(12)->amounts[$food->id]);
    }

    public function test_december_copies_the_approved_november_plan(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $this->approve($budget, 11, [$food->id => 9_000]);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 12, 5));

        $november = $proposal->reading(11);
        $this->assertSame('approved', $november->state);
        $this->assertNotNull($november->approvedAt);
        $this->assertSame(9_000, $november->amounts[$food->id]);

        $this->assertSame('proposal', $proposal->reading(12)->state);
        $this->assertSame(9_000, $proposal->reading(12)->amounts[$food->id]);
    }

    public function test_approved_reading_uses_the_current_amount(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $plan = $this->approve($budget, 11, [$food->id => 9_000]);
        $plan->items()->update(['current_amount_minor_units' => 9_500]);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11);

        $this->assertSame(9_500, $reading->amounts[$food->id]);
    }

    public function test_proposal_copies_a_positive_set_aside_through_the_chain(): void
    {
        $budget = $this->budget(2026);
        $this->food($budget);
        $this->approve($budget, 10, [], 50_000);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(50_000, $proposal->reading(10)->setAsideMinorUnits);
        $this->assertSame(50_000, $proposal->reading(11)->setAsideMinorUnits);
        $this->assertSame(50_000, $proposal->reading(12)->setAsideMinorUnits);
    }

    public function test_proposal_does_not_copy_a_draw(): void
    {
        $budget = $this->budget(2026);
        $this->food($budget);
        $this->approve($budget, 10, [], -30_000);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(-30_000, $proposal->reading(10)->setAsideMinorUnits);
        $this->assertSame(0, $proposal->reading(11)->setAsideMinorUnits);
        $this->assertSame(0, $proposal->reading(12)->setAsideMinorUnits);
    }

    public function test_proposal_without_a_source_has_no_set_aside(): void
    {
        $budget = $this->budget(2026);
        $this->food($budget);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame('none', $proposal->reading(10)->state);
        $this->assertSame(0, $proposal->reading(10)->setAsideMinorUnits);
        $this->assertSame(0, $proposal->reading(11)->setAsideMinorUnits);
    }

    public function test_january_copies_a_positive_set_aside_from_december_of_the_previous_budget(): void
    {
        $previous = $this->budget(2026);
        $this->food($previous);
        $this->approve($previous, 12, [], 20_000);

        $budget = $this->budget(2027);
        $this->food($budget);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame('proposal', $january->state);
        $this->assertSame(20_000, $january->setAsideMinorUnits);
    }

    public function test_january_does_not_copy_a_draw_from_the_previous_budget(): void
    {
        $previous = $this->budget(2026);
        $this->food($previous);
        $this->approve($previous, 12, [], -10_000);

        $budget = $this->budget(2027);
        $this->food($budget);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame(0, $january->setAsideMinorUnits);
    }

    public function test_approved_reading_uses_the_current_set_aside(): void
    {
        $budget = $this->budget(2026);
        $this->food($budget);
        $plan = $this->approve($budget, 11, [], 50_000);
        $plan->update(['current_set_aside_minor_units' => 70_000]);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11);

        $this->assertSame(70_000, $reading->setAsideMinorUnits);
    }

    public function test_past_month_without_a_plan_is_none(): void
    {
        $budget = $this->budget(2026);
        $this->food($budget);

        $this->assertSame('none', MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(3)->state);
        $this->assertSame('none', MonthPlanProposal::forBudget($budget, $this->day(2027, 2, 1))->reading(12)->state);
    }

    public function test_january_copies_the_approved_december_of_the_previous_budget_by_name_and_type(): void
    {
        $previous = $this->budget(2026);
        $previousFood = $this->food($previous);
        $this->approve($previous, 12, [$previousFood->id => 9_500]);

        $budget = $this->budget(2027);
        $food = $this->food($budget);
        $gym = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Siłownia', 'planned_amount_minor_units' => 120_000]);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame('proposal', $january->state);
        $this->assertSame(9_500, $january->amounts[$food->id]);
        $this->assertSame(10_000, $january->amounts[$gym->id]);
    }

    public function test_january_matches_the_same_list_category_by_id_after_a_rename(): void
    {
        $previous = $this->budget(2026);
        $previousFood = $this->food($previous);
        $this->approve($previous, 12, [$previousFood->id => 9_500]);

        $budget = $this->budget(2027);
        $food = $this->food($budget, ['category_id' => $previousFood->category_id]);
        $previousFood->category->update(['name' => 'Żywność', 'name_key' => 'żywność']);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame(9_500, $january->amounts[$food->id]);
    }

    public function test_january_does_not_match_a_different_list_category_with_a_similar_name(): void
    {
        $previous = $this->budget(2026);
        $other = $this->food($previous, ['name' => 'Jedzenie (2)']);
        $this->approve($previous, 12, [$other->id => 9_500]);

        $budget = $this->budget(2027);
        $food = $this->food($budget);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame(8_333, $january->amounts[$food->id]);
    }

    public function test_january_falls_back_to_zero_for_a_matched_category_absent_from_december(): void
    {
        $previous = $this->budget(2026);
        $this->food($previous);
        $this->approve($previous, 12, []);

        $budget = $this->budget(2027);
        $food = $this->food($budget);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame(0, $january->amounts[$food->id]);
    }

    public function test_january_uses_the_average_when_december_of_the_previous_budget_is_none(): void
    {
        $previous = $this->budget(2026);
        $this->food($previous);

        $budget = $this->budget(2027);
        $food = $this->food($budget);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame(8_333, $january->amounts[$food->id]);
    }

    public function test_chain_through_the_previous_budget_does_not_reach_two_budgets_back(): void
    {
        $twoBack = $this->budget(2025);
        $twoBackFood = $this->food($twoBack);
        $this->approve($twoBack, 12, [$twoBackFood->id => 7_777]);

        $previous = $this->budget(2026);
        $this->food($previous);

        $budget = $this->budget(2027);
        $food = $this->food($budget);

        $january = MonthPlanProposal::forBudget($budget, $this->day(2026, 1, 10))->reading(1);

        $this->assertSame('proposal', $january->state);
        $this->assertSame(8_333, $january->amounts[$food->id]);
    }

    public function test_even_annual_category_is_proposed_as_the_average_with_the_remainder_in_december(): void
    {
        $budget = $this->budget(2026);
        $retreat = BudgetLine::factory()->for($budget)->evenAnnual()->create(['name' => 'Rekolekcje', 'planned_amount_minor_units' => 238_000]);
        $insurance = BudgetLine::factory()->for($budget)->evenAnnual()->create(['name' => 'Ubezpieczenie samochodu', 'planned_amount_minor_units' => 54_100]);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(19_833, $proposal->reading(11)->amounts[$retreat->id]);
        $this->assertSame(238_000 - 11 * 19_833, $proposal->reading(12)->amounts[$retreat->id]);
        $this->assertSame(19_837, $proposal->reading(12)->amounts[$retreat->id]);
        $this->assertSame(4_508, $proposal->reading(11)->amounts[$insurance->id]);
        $this->assertSame(4_512, $proposal->reading(12)->amounts[$insurance->id]);
    }

    public function test_even_annual_category_ignores_an_approved_source_month(): void
    {
        $budget = $this->budget(2026);
        $retreat = BudgetLine::factory()->for($budget)->evenAnnual()->create(['planned_amount_minor_units' => 120_000]);
        $this->approve($budget, 10, [$retreat->id => 77_000]);

        $this->assertSame(10_000, MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11)->amounts[$retreat->id]);
    }

    public function test_fixed_charge_is_proposed_as_the_last_paid_amount(): void
    {
        $budget = $this->budget(2026);
        $phone = $this->fixedCharge($budget, 18_000);
        $this->approve($budget, 10, [$phone->id => 18_000]);
        $this->pay($budget, $phone, '2026-10-14', 17_215);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(17_215, $proposal->reading(11)->amounts[$phone->id]);
        $this->assertSame(17_215, $proposal->reading(12)->amounts[$phone->id]);
    }

    public function test_fixed_charge_without_a_payment_copies_the_plan_of_the_previous_month(): void
    {
        $budget = $this->budget(2026);
        $phone = $this->fixedCharge($budget, 18_000);
        $this->approve($budget, 10, [$phone->id => 17_000]);

        $this->assertSame(17_000, MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11)->amounts[$phone->id]);
    }

    public function test_fixed_charge_with_a_payment_and_a_larger_refund_copies_the_plan(): void
    {
        $budget = $this->budget(2026);
        $phone = $this->fixedCharge($budget, 18_000);
        $this->approve($budget, 10, [$phone->id => 18_000]);
        $this->pay($budget, $phone, '2026-10-14', 10_000);
        $this->pay($budget, $phone, '2026-10-20', -15_000);

        $this->assertSame(18_000, MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11)->amounts[$phone->id]);
    }

    public function test_fixed_charge_with_two_payments_takes_their_net_sum(): void
    {
        $budget = $this->budget(2026);
        $phone = $this->fixedCharge($budget, 18_000);
        $this->approve($budget, 10, [$phone->id => 18_000]);
        $this->pay($budget, $phone, '2026-10-14', 10_000);
        $this->pay($budget, $phone, '2026-10-20', 7_000);
        $this->pay($budget, $phone, '2026-10-21', -500);

        $this->assertSame(16_500, MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11)->amounts[$phone->id]);
    }

    public function test_fixed_charge_of_january_reads_the_payment_of_december_in_the_previous_budget(): void
    {
        $previous = $this->budget(2026);
        $previousPhone = $this->fixedCharge($previous, 18_000);
        $this->approve($previous, 12, [$previousPhone->id => 18_000]);
        $this->pay($previous, $previousPhone, '2026-12-14', 17_215);

        $budget = $this->budget(2027);
        $phone = $this->fixedCharge($budget, 18_000, ['category_id' => $previousPhone->category_id]);

        $this->assertSame(17_215, MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1)->amounts[$phone->id]);
    }

    public function test_fixed_charge_in_a_past_unapproved_source_month_prefers_the_payment_to_the_average(): void
    {
        $budget = $this->budget(2026);
        $phone = $this->fixedCharge($budget, 120_000);
        $paid = $this->fixedCharge($budget, 120_000, ['name' => 'Opłacona']);
        $this->pay($budget, $paid, '2026-10-14', 17_215);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame('none', $proposal->reading(10)->state);
        $this->assertSame(17_215, $proposal->reading(11)->amounts[$paid->id]);
        $this->assertSame(10_000, $proposal->reading(11)->amounts[$phone->id]);
    }

    public function test_rhythm_changed_in_november_applies_to_november_and_december_but_not_to_october(): void
    {
        $budget = $this->budget(2026);
        $line = $this->food($budget, ['planned_amount_minor_units' => 120_000]);
        $this->approve($budget, 10, [$line->id => 55_000]);
        $line->category->rhythms()->delete();
        $line->category->rhythms()->create(['start_year' => 2026, 'start_month' => 1, 'rhythm' => Rhythm::Running]);
        $line->category->rhythms()->create(['start_year' => 2026, 'start_month' => 11, 'rhythm' => Rhythm::EvenAnnual]);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(Rhythm::Running, $proposal->rhythmIn($line, 10));
        $this->assertSame(Rhythm::EvenAnnual, $proposal->rhythmIn($line, 11));
        $this->assertSame(10_000, $proposal->reading(11)->amounts[$line->id]);
        $this->assertSame(10_000, $proposal->reading(12)->amounts[$line->id]);

        $line->category->rhythms()->delete();
        $line->category->rhythms()->create(['start_year' => 2026, 'start_month' => 12, 'rhythm' => Rhythm::EvenAnnual]);
        $line->category->rhythms()->create(['start_year' => 2026, 'start_month' => 1, 'rhythm' => Rhythm::Running]);

        $december = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(55_000, $december->reading(11)->amounts[$line->id]);
    }

    public function test_category_added_after_the_previous_month_was_approved_starts_at_zero(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $this->approve($budget, 11, [$food->id => 9_000]);
        $cinema = BudgetLine::factory()->for($budget)->expense()->create(['planned_amount_minor_units' => 120_000]);

        $december = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 20))->reading(12);

        $this->assertSame(9_000, $december->amounts[$food->id]);
        $this->assertSame(0, $december->amounts[$cinema->id]);
    }

    public function test_category_archived_in_a_month_stays_there_and_disappears_from_the_next(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $gym = $this->food($budget, ['name' => 'Siłownia', 'archived_at' => '2026-11-20 10:00:00']);
        $old = $this->food($budget, ['name' => 'Stara', 'archived_at' => '2026-10-31 10:00:00']);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertArrayHasKey($gym->id, $proposal->reading(11)->amounts);
        $this->assertArrayNotHasKey($old->id, $proposal->reading(11)->amounts);
        $this->assertArrayNotHasKey($gym->id, $proposal->reading(12)->amounts);
        $this->assertArrayHasKey($food->id, $proposal->reading(12)->amounts);
    }

    public function test_budget_loading_uses_a_fixed_number_of_queries(): void
    {
        $previous = $this->budget(2026);
        $this->food($previous);
        $budget = $this->budget(2027);
        $this->food($budget);

        $phone = $this->fixedCharge($budget, 18_000);
        $this->fixedCharge($previous, 18_000, ['category_id' => $phone->category_id]);
        $this->pay($budget, $phone, '2027-02-14', 1_000);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4));

        foreach (range(1, 12) as $month) {
            $proposal->reading($month);
        }

        // Two more than the lines alone (the list Category of each Budget's lines is eager loaded),
        // plus one for the Rhythm entries and one actuals query per Budget, whatever the months read.
        // One more reads the owner of the Budget for the Default Set-aside Percent (ADR-0081; the factory
        // Budget has no loaded user, budgets from $user->budgets() get it by chaperone without a query).
        $this->assertLessThanOrEqual(11, $queries);
    }

    public function test_an_irregular_category_is_proposed_with_the_rate_of_the_annual_plan(): void
    {
        $budget = $this->budget(2026);
        $service = BudgetLine::factory()->for($budget)->irregular()->create(['name' => 'Serwis', 'planned_amount_minor_units' => 180_000]);
        $retreat = BudgetLine::factory()->for($budget)->irregular()->create(['name' => 'Rekolekcje', 'planned_amount_minor_units' => 238_000]);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 6, 10));

        $this->assertSame(15_000, $proposal->reading(6)->amounts[$service->id]);
        $this->assertSame(19_833, $proposal->reading(11)->amounts[$retreat->id]);
        $this->assertSame(19_837, $proposal->reading(12)->amounts[$retreat->id]);
    }

    public function test_default_percent_sets_aside_the_percent_of_planned_income_rounded_down_to_a_zloty(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(10)->create();
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $this->approve($budget, 10, [$salary->id => 843_755]);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(84_300, $proposal->reading(11)->setAsideMinorUnits);
        $this->assertSame(['percent' => 10, 'income_minor_units' => 843_755], $proposal->reading(11)->setAsideDefault);

        $other = $this->budget(2027);
        $otherSalary = $this->income($other);
        $this->approve($other, 10, [$otherSalary->id => 900_000]);

        $this->assertSame(90_000, MonthPlanProposal::forBudget($other, $this->day(2027, 11, 10))->reading(11)->setAsideMinorUnits);
    }

    public function test_default_percent_replaces_copying_the_set_aside_of_an_approved_previous_month(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(10)->create();
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $this->approve($budget, 10, [$salary->id => 900_000], 150_000);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(90_000, $proposal->reading(11)->setAsideMinorUnits);
        $this->assertSame(150_000, $proposal->reading(10)->setAsideMinorUnits);
    }

    public function test_default_percent_zero_sets_aside_nothing_even_with_a_positive_source(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(0)->create();
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $this->approve($budget, 10, [$salary->id => 900_000], 150_000);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11);

        $this->assertSame(0, $reading->setAsideMinorUnits);
        $this->assertSame(['percent' => 0, 'income_minor_units' => 900_000], $reading->setAsideDefault);
    }

    public function test_without_a_default_percent_the_positive_set_aside_is_copied(): void
    {
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $this->approve($budget, 10, [$salary->id => 900_000], 150_000);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10));

        $this->assertSame(150_000, $proposal->reading(11)->setAsideMinorUnits);
        $this->assertNull($proposal->reading(11)->setAsideDefault);
    }

    public function test_without_a_default_percent_a_draw_source_gives_zero(): void
    {
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $this->approve($budget, 10, [$salary->id => 900_000], -50_000);

        $this->assertSame(0, MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11)->setAsideMinorUnits);
    }

    public function test_january_without_a_previous_budget_uses_the_default_percent_of_january_income(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(10)->create();
        $budget = $this->budget(2027);
        $this->income($budget, 12_000_000);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2027, 1, 4))->reading(1);

        $this->assertSame(100_000, $reading->setAsideMinorUnits);
        $this->assertSame(1_000_000, $reading->setAsideDefault['income_minor_units']);
    }

    public function test_an_income_line_archived_before_the_month_is_left_out_of_the_base(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(10)->create();
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $old = $this->income($budget, 12_000_000, ['name' => 'Stara', 'archived_at' => '2026-10-31 10:00:00']);
        $this->approve($budget, 10, [$salary->id => 900_000, $old->id => 500_000]);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11);

        $this->assertSame(90_000, $reading->setAsideMinorUnits);
        $this->assertSame(900_000, $reading->setAsideDefault['income_minor_units']);
    }

    public function test_a_month_without_planned_income_sets_aside_zero(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(10)->create();
        $budget = $this->budget(2026);
        $this->food($budget);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(11);

        $this->assertSame(0, $reading->setAsideMinorUnits);
        $this->assertSame(['percent' => 10, 'income_minor_units' => 0], $reading->setAsideDefault);
    }

    public function test_an_approved_plan_keeps_its_set_aside_after_the_default_percent_is_set(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(20)->create();
        $budget = $this->budget(2026);
        $salary = $this->income($budget);
        $this->approve($budget, 10, [$salary->id => 900_000], 90_000);

        $reading = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 10))->reading(10);

        $this->assertSame('approved', $reading->state);
        $this->assertSame(90_000, $reading->setAsideMinorUnits);
        $this->assertNull($reading->setAsideDefault);
    }

    public function test_january_takes_the_percent_of_income_proposed_from_the_previous_budget(): void
    {
        $this->user = User::factory()->withDefaultSetAsidePercent(10)->create();
        $previous = $this->budget(2026);
        $salary = $this->income($previous);
        $this->approve($previous, 11, [$salary->id => 900_000], 150_000);
        $budget = $this->budget(2027);
        $this->income($budget, 10_800_000, ['category_id' => $salary->category_id]);

        $proposal = MonthPlanProposal::forBudget($budget, $this->day(2026, 11, 20));

        $this->assertSame(90_000, $proposal->reading(1)->setAsideMinorUnits);
    }

    private function day(int $year, int $month, int $day): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, $day);
    }

    private function budget(int $year): Budget
    {
        return Budget::factory()->for($this->user)->execution()->create(['year' => $year]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function food(Budget $budget, array $attributes = []): BudgetLine
    {
        return BudgetLine::factory()->for($budget)->expense()->create($attributes + [
            'name' => 'Jedzenie',
            'planned_amount_minor_units' => 100_001,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function income(Budget $budget, int $annualMinorUnits = 10_800_000, array $attributes = []): BudgetLine
    {
        return BudgetLine::factory()->for($budget)->income()->create($attributes + [
            'name' => 'Pensja',
            'planned_amount_minor_units' => $annualMinorUnits,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fixedCharge(Budget $budget, int $annualMinorUnits, array $attributes = []): BudgetLine
    {
        return BudgetLine::factory()->for($budget)->fixedCharge()->create($attributes + [
            'name' => 'Telefon + Internet',
            'planned_amount_minor_units' => $annualMinorUnits,
        ]);
    }

    private function pay(Budget $budget, BudgetLine $line, string $date, int $amount): void
    {
        FinancialOperation::factory()->for($this->user)->for($budget)->create([
            'type' => FinancialOperationType::Expense,
            'budget_line_id' => $line->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }

    /**
     * @param  array<int, int>  $amounts
     */
    private function approve(Budget $budget, int $month, array $amounts, int $setAside = 0): MonthPlan
    {
        $plan = MonthPlan::factory()->for($budget)->create([
            'month' => $month,
            'approved_set_aside_minor_units' => $setAside,
            'current_set_aside_minor_units' => $setAside,
        ]);

        foreach ($amounts as $categoryId => $amount) {
            MonthPlanItem::factory()->create([
                'month_plan_id' => $plan->id,
                'budget_line_id' => $categoryId,
                'approved_amount_minor_units' => $amount,
                'current_amount_minor_units' => $amount,
            ]);
        }

        return $plan;
    }
}
