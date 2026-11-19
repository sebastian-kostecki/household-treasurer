<?php

use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\MonthPlanTool;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

test('it answers the plan against execution of the current month with the group sums', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();

    TreasurerServer::actingAs($household['user'])->tool(MonthPlanTool::class)
        ->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'year' => 2026,
            'month' => 6,
            'timing' => 'current',
            'state' => 'proposal',
            'totals.to_spend_minor_units' => 373_000,
            'totals.income_minor_units' => 800_000,
            'income.categories.0.name' => 'Pensja',
            'income.categories.0.plan_minor_units' => 800_000,
            'income.categories.0.actual_minor_units' => 0,
            'income.categories.0.rhythm' => null,
            'expense.categories.0.name' => 'Czynsz',
            'expense.categories.0.rhythm' => 'fixed_charge',
            'expense.categories.0.plan_minor_units' => 200_000,
            'expense.categories.0.plan_display' => '2000,00 zł',
            'expense.categories.0.actual_minor_units' => 195_000,
            'expense.categories.1.name' => 'Jedzenie',
            'expense.categories.1.plan_minor_units' => 150_000,
            'expense.categories.1.actual_minor_units' => 60_000,
            'expense.categories.1.is_over_plan' => false,
            'expense.categories.1.group_id' => $household['group']->id,
            'expense.categories.2.name' => 'Paliwo',
            'expense.categories.2.plan_minor_units' => 40_000,
            'expense.categories.2.actual_minor_units' => 52_000,
            'expense.categories.2.is_over_plan' => true,
            'expense.categories.3.name' => 'Prezent',
            'expense.categories.3.rhythm' => 'even_annual',
            'expense.categories.3.plan_minor_units' => 8_333,
            'expense.categories.3.actual_minor_units' => 30_000,
        ])
        ->assertStructuredContentCount(4, 'expense.categories')
        ->assertStructuredContentCount(0, 'income.groups')
        ->assertStructuredContentCount(1, 'expense.groups')
        ->assertStructuredContentPaths([
            'expense.groups.0.id' => $household['group']->id,
            'expense.groups.0.name' => 'Dom',
            'expense.groups.0.category_count' => 2,
            'expense.groups.0.plan_minor_units' => 190_000,
            'expense.groups.0.plan_display' => '1900,00 zł',
            'expense.groups.0.actual_minor_units' => 112_000,
            'expense.groups.0.actual_display' => '1120,00 zł',
            'expense.groups.0.over_plan' => true,
        ]);
});

test('a month of another year and month is read by its arguments', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();

    TreasurerServer::actingAs($household['user'])->tool(MonthPlanTool::class, ['year' => 2026, 'month' => 7])
        ->assertOk()
        ->assertStructuredContentPaths(['month' => 7, 'timing' => 'future'])
        ->assertStructuredContentPath('expense.categories.0.actual_minor_units', 0);

    TreasurerServer::actingAs($household['user'])->tool(MonthPlanTool::class, ['month' => 13])
        ->assertHasErrors();
});

test('another user\'s categories, groups and operations never appear', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    TreasurerFixture::stranger();

    TreasurerServer::actingAs($household['user'])->tool(MonthPlanTool::class)
        ->assertOk()
        ->assertDontSee(['Obca', '777700', '111100']);
});

test('the App Day month turns at Polish midnight on 31 December', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    Budget::factory()->for($user)->execution()->create(['year' => 2027]);

    $this->travelTo('2026-12-31 22:59:59');
    TreasurerServer::actingAs($user)->tool(MonthPlanTool::class)
        ->assertStructuredContentPaths(['app_day' => '2026-12-31', 'year' => 2026, 'month' => 12]);

    $this->travelTo('2026-12-31 23:00:00');
    TreasurerServer::actingAs($user)->tool(MonthPlanTool::class)
        ->assertStructuredContentPaths(['app_day' => '2027-01-01', 'year' => 2027, 'month' => 1]);
});

test('a year without a Budget in execution is answered with its phase and no month plan', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    Budget::factory()->for($user)->create(['year' => 2027]);

    TreasurerServer::actingAs($user)->tool(MonthPlanTool::class, ['year' => 2027])
        ->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'year' => 2027,
            'month' => 6,
            'budget_phase' => 'draft',
            'state' => 'none',
        ])
        ->assertStructuredContentMissingPath('income')
        ->assertStructuredContentMissingPath('expense')
        ->assertSee('no month plan');

    TreasurerServer::actingAs($user)->tool(MonthPlanTool::class, ['year' => 2026])
        ->assertOk()
        ->assertStructuredContentPaths(['app_day' => '2026-06-15', 'year' => 2026, 'budget_phase' => null, 'state' => 'none'])
        ->assertStructuredContentMissingPath('income');
});

test('it refuses a call without a user', function () {
    TreasurerServer::tool(MonthPlanTool::class)->assertHasErrors(['No user is signed in']);
});
