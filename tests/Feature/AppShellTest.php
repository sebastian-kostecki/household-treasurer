<?php

namespace Tests\Feature;

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
