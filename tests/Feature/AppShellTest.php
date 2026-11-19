<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function authenticatedShellRoutesProvider(): array
    {
        return [
            'dashboard' => ['dashboard', 'Dashboard'],
            'budget' => ['budget', 'Budget'],
            'categories' => ['categories.index', 'Categories'],
            'accounts' => ['accounts.index', 'Accounts'],
            'term_deposits' => ['term-deposits.index', 'TermDeposits'],
            'operations' => ['operations', 'Operations'],
        ];
    }

    public function test_guests_are_redirected_from_authenticated_shell_routes(): void
    {
        foreach (self::authenticatedShellRoutesProvider() as [$routeName]) {
            $this->get(route($routeName))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('authenticatedShellRoutesProvider')]
    public function test_authenticated_users_can_visit_shell_pages(string $routeName, string $component): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->missing('sidebarOpen')
                ->where('translations.shell.open_menu', 'Otwórz menu')
                ->where('translations.shell.shortcuts_hint', 'Skróty: 1–:last widoki, N nowa operacja, / szukaj, Esc zamknij.')
                ->where('translations.shell.add_budget_for_year', 'Dodaj budżet na rok :year')
                ->where('translations.shell.search_and_go', 'Szukaj i przejdź')
                ->where('translations.shell.year_current', 'bieżący')
                ->where('translations.shell.year_draft', 'szkic')
                ->where('translations.shell.years_recent', 'Ostatnie')
                ->where('translations.shell.years_older', 'Starsze')
                ->where('translations.shell.add_year_source', 'szkic z planem roku :year')
                ->where('translations.palette.group_actions', 'Widoki i akcje')
                ->where('translations.palette.group_categories', 'Kategorie: operacje')
                ->where('translations.palette.empty', 'Brak wyników')
                ->where('translations.palette.error', 'Nie udało się wczytać kategorii.')
                ->where('translations.palette.no_budget_year', 'Brak budżetu — dodaj rok, aby szukać kategorii.')
                ->has('translations.palette.add_operation')
                ->has('translations.palette.import_csv')
                ->has('translations.palette.add_budget_year')
                ->has('translations.palette.settings')
                ->has('translations.palette.placeholder')
                ->has('translations.palette.input_label')
                ->has('translations.palette.income_note')
                ->has('translations.palette.loading')
                ->where('shell.budgetYear', null)
                ->where('shell.budgetYears', [])
                ->where('shell.firstRun.hasAccount', false)
                ->where('shell.firstRun.hasBudget', false)
                ->where('shell.firstRun.hasCategories', false)
                ->where('shell.firstRun.executionStarted', false)
                ->where('shell.firstRun.showChecklist', true),
            );
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function polishMidnightProvider(): array
    {
        return [
            'winter, last second of 2026' => ['2026-12-31 22:59:59', '2026-12-31', 2026],
            'winter, polish midnight' => ['2026-12-31 23:00:00', '2027-01-01', 2027],
            'summer, last second of 30 June' => ['2027-06-30 21:59:59', '2027-06-30', 2027],
            'summer, polish midnight' => ['2027-06-30 22:00:00', '2027-07-01', 2027],
        ];
    }

    #[DataProvider('polishMidnightProvider')]
    public function test_shell_today_is_the_app_day_the_dashboard_and_the_budget_year_follow(string $instant, string $today, int $year): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Budget::factory()->for($user)->execution()->create(['year' => 2027]);

        $this->travelTo($instant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.today', $today)
                ->where('shell.budgetYear', $year)
                ->where('budget_year', $year),
            );
    }

    public function test_guest_pages_get_shell_today(): void
    {
        $this->travelTo('2026-12-31 23:30:00');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('shell.today', '2027-01-01'),
            );
    }

    public function test_login_page_includes_polish_translations_when_app_locale_is_english(): void
    {
        config(['app.locale' => 'en']);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('translations.auth.login.title', 'Zaloguj się')
                ->where('translations.auth.login.submit', 'Zaloguj się'),
            );
    }

    public function test_login_page_includes_polish_translations(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('translations.auth.login.title', 'Zaloguj się')
                ->where('translations.auth.login.submit', 'Zaloguj się'),
            );
    }

    public function test_settings_profile_page_includes_polish_translations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('translations.settings.profile', 'Profil'),
            );
    }
}
