<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\BudgetLineResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetLineResolverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget2026;

    private Budget $budget2027;

    private BudgetLine $food2026;

    private BudgetLine $food2027;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->budget2026 = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
        $this->budget2027 = Budget::factory()->for($this->user)->create(['year' => 2027]);
        $this->food2026 = BudgetLine::factory()->for($this->budget2026)->expense()->create(['name' => 'Jedzenie']);
        $this->food2027 = BudgetLine::factory()->for($this->budget2027)->expense()->create(['name' => 'Jedzenie']);
    }

    public function test_one_list_category_resolves_to_the_line_of_each_year(): void
    {
        $this->assertSame($this->food2026->category_id, $this->food2027->category_id);

        $resolver = new BudgetLineResolver;

        $this->assertTrue($resolver->forYear($this->user, $this->food2026->category_id, 2026)->is($this->food2026));
        $this->assertTrue($resolver->forYear($this->user, $this->food2026->category_id, 2027)->is($this->food2027));
    }

    public function test_for_year_is_null_without_a_line_in_that_year(): void
    {
        $only2026 = BudgetLine::factory()->for($this->budget2026)->expense()->create(['name' => 'Tylko 2026']);

        $this->assertNull((new BudgetLineResolver)->forYear($this->user, $only2026->category_id, 2027));
    }

    public function test_for_year_never_finds_the_line_of_another_user(): void
    {
        $stranger = User::factory()->create();

        $this->assertNull((new BudgetLineResolver)->forYear($stranger, $this->food2026->category_id, 2026));
    }

    public function test_for_budget_finds_the_line_of_the_category_in_that_budget_only(): void
    {
        $resolver = new BudgetLineResolver;
        $only2026 = BudgetLine::factory()->for($this->budget2026)->expense()->create(['name' => 'Tylko 2026']);

        $this->assertTrue($resolver->forBudget($this->budget2027, $this->food2026->category_id)->is($this->food2027));
        $this->assertNull($resolver->forBudget($this->budget2027, $only2026->category_id));
    }

    public function test_amounts_by_line_re_keys_list_category_ids_to_line_ids(): void
    {
        $rent = BudgetLine::factory()->for($this->budget2027)->expense()->create(['name' => 'Czynsz']);

        $amounts = (new BudgetLineResolver)->amountsByLine($this->budget2027, [
            $this->food2027->category_id => 100,
            $rent->category_id => 250,
        ]);

        $this->assertSame([$this->food2027->id => 100, $rent->id => 250], $amounts);
    }

    public function test_amounts_by_line_is_null_when_any_category_has_no_line_in_the_budget(): void
    {
        $only2026 = BudgetLine::factory()->for($this->budget2026)->expense()->create(['name' => 'Tylko 2026']);

        $this->assertNull((new BudgetLineResolver)->amountsByLine($this->budget2027, [
            $this->food2027->category_id => 100,
            $only2026->category_id => 1,
        ]));
    }
}
