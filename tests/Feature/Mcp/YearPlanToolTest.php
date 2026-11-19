<?php

use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\YearPlanTool;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

test('it answers the annual plan against execution with group and type totals', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();

    TreasurerServer::actingAs($household['user'])->tool(YearPlanTool::class)
        ->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'year' => 2026,
            'budget_phase' => 'execution',
            'income.categories.0.name' => 'Pensja',
            'income.categories.0.initial_plan_minor_units' => 9_000_000,
            'income.categories.0.current_plan_minor_units' => 9_600_000,
            'income.categories.0.current_plan_display' => '96000,00 zł',
            'income.categories.0.execution_minor_units' => 0,
            'income.categories.0.remaining_minor_units' => 9_600_000,
            'income.categories.0.percent_complete' => 0,
            'income.categories.0.is_over_limit' => false,
            'expense.categories.0.name' => 'Czynsz',
            'expense.categories.0.execution_minor_units' => 195_000,
            'expense.categories.1.name' => 'Jedzenie',
            'expense.categories.1.current_plan_minor_units' => 1_800_000,
            'expense.categories.1.execution_minor_units' => 1_850_000,
            'expense.categories.1.remaining_minor_units' => -50_000,
            'expense.categories.1.remaining_display' => '-500,00 zł',
            'expense.categories.1.percent_complete' => 103,
            'expense.categories.1.is_over_limit' => true,
            'expense.categories.2.name' => 'Paliwo',
            'expense.categories.2.remaining_minor_units' => 428_000,
            'expense.categories.2.percent_complete' => 11,
            'expense.categories.2.is_over_limit' => false,
            'expense.categories.3.name' => 'Prezent',
            'expense.categories.3.percent_complete' => 30,
            'expense.groups.0.name' => 'Dom',
            'expense.groups.0.totals.current_plan_minor_units' => 2_280_000,
            'expense.groups.0.totals.execution_minor_units' => 1_902_000,
            'expense.groups.0.totals.remaining_minor_units' => 378_000,
            'expense.groups.0.totals.percent_complete' => 83,
            'expense.groups.0.has_over_limit_category' => true,
            'totals.income.initial_plan_minor_units' => 9_000_000,
            'totals.income.current_plan_minor_units' => 9_600_000,
            'totals.expense.initial_plan_minor_units' => 4_780_000,
            'totals.expense.current_plan_minor_units' => 4_780_000,
            'totals.expense.execution_minor_units' => 2_127_000,
            'totals.expense.remaining_minor_units' => 2_653_000,
            'totals.expense.percent_complete' => 44,
            'totals.planned_balance_minor_units' => 4_820_000,
            'totals.planned_balance_display' => '48200,00 zł',
        ])
        ->assertStructuredContentCount(4, 'expense.categories');
});

test('another year is read by its argument and a foreign budget never appears', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    TreasurerFixture::stranger();
    $draft = Budget::factory()->for($household['user'])->create(['year' => 2027]);
    BudgetLine::factory()->for($draft)->expense()->create(['name' => 'Wakacje', 'planned_amount_minor_units' => 120_000]);

    TreasurerServer::actingAs($household['user'])->tool(YearPlanTool::class, ['year' => 2027])
        ->assertOk()
        ->assertStructuredContentPaths([
            'year' => 2027,
            'budget_phase' => 'draft',
            'expense.categories.0.name' => 'Wakacje',
            'expense.categories.0.initial_plan_minor_units' => 120_000,
            'expense.categories.0.current_plan_minor_units' => 120_000,
            'expense.categories.0.execution_minor_units' => 0,
            'totals.expense.current_plan_minor_units' => 120_000,
        ])
        ->assertDontSee(['Obca', '777700']);

    TreasurerServer::actingAs($household['user'])->tool(YearPlanTool::class)
        ->assertDontSee(['Obca', '777700', 'Wakacje']);
});

test('the App Day year turns at Polish midnight on 31 December', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    Budget::factory()->for($user)->create(['year' => 2027]);

    $this->travelTo('2026-12-31 22:59:59');
    TreasurerServer::actingAs($user)->tool(YearPlanTool::class)
        ->assertStructuredContentPaths(['app_day' => '2026-12-31', 'year' => 2026, 'budget_phase' => 'execution']);

    $this->travelTo('2026-12-31 23:00:00');
    TreasurerServer::actingAs($user)->tool(YearPlanTool::class)
        ->assertStructuredContentPaths(['app_day' => '2027-01-01', 'year' => 2027, 'budget_phase' => 'draft']);
});

test('a year without a Budget has no phase', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();

    TreasurerServer::actingAs($user)->tool(YearPlanTool::class)
        ->assertOk()
        ->assertStructuredContentPaths(['year' => 2026, 'budget_phase' => null]);
});

test('it refuses a call without a user', function () {
    TreasurerServer::tool(YearPlanTool::class)->assertHasErrors(['No user is signed in']);
});
