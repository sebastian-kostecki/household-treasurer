<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthPlanSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('month-plan-settings.edit'))->assertRedirect(route('login'));
        $this->patch(route('month-plan-settings.update'), ['default_set_aside_percent' => 10])->assertRedirect(route('login'));
    }

    public function test_the_page_shows_an_empty_percent(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('month-plan-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/MonthPlan')
                ->where('defaultSetAsidePercent', null));
    }

    public function test_the_page_shows_the_stored_percent(): void
    {
        $this->actingAs(User::factory()->withDefaultSetAsidePercent(10)->create())
            ->get(route('month-plan-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/MonthPlan')
                ->where('defaultSetAsidePercent', 10));
    }

    /**
     * @return array<string, array{string, int|null}>
     */
    public static function validPercents(): array
    {
        return [
            'ten' => ['10', 10],
            'zero' => ['0', 0],
            'hundred' => ['100', 100],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('validPercents')]
    public function test_the_percent_can_be_saved(string $input, ?int $expected): void
    {
        $user = User::factory()->withDefaultSetAsidePercent(30)->create();

        $this->actingAs($user)
            ->patch(route('month-plan-settings.update'), ['default_set_aside_percent' => $input])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('month-plan-settings.edit'))
            ->assertInertiaFlash('toast.message', 'Ustawienia planu zapisane.');

        $this->assertSame($expected, $user->refresh()->default_set_aside_percent);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPercents(): array
    {
        return [
            'negative' => ['-1'],
            'above 100' => ['101'],
            'fraction' => ['10.5'],
            'text' => ['abc'],
        ];
    }

    #[DataProvider('invalidPercents')]
    public function test_an_invalid_percent_is_refused_and_the_stored_one_stays(string $input): void
    {
        $user = User::factory()->withDefaultSetAsidePercent(30)->create();

        $this->actingAs($user)
            ->from(route('month-plan-settings.edit'))
            ->patch(route('month-plan-settings.update'), ['default_set_aside_percent' => $input])
            ->assertSessionHasErrors('default_set_aside_percent');

        $this->assertSame(30, $user->refresh()->default_set_aside_percent);
    }
}
