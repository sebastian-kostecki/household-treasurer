# S-06: „Do wydania” zamiast `Pozostało w miesiącu` — Plan Brief

> Full plan: `context/changes/to-spend-replaces-remaining/plan.md`
> Research: `context/changes/to-spend-replaces-remaining/research.md`

## What & Why

Użytkownik ma odczytać liczbę „do wydania” z planu miesiąca (S-01) na Dashboardzie i na stronie Budżet. Dziś jest ona tylko na stronie „Plan miesiąca”. Miesiąc bez planu ma być wyraźnie oznaczony (FR-011, FR-013). Liczba ma być wszędzie ta sama, bo pochodzi z jednego odczytu na serwerze.

## Starting Point

Dashboard pokazuje kafelek Month Remaining puli równomiernych, ale tylko przy widocznej kategorii równomiernej. Strona Budżet wysyła Month Remaining w propsach, ale go nie wyświetla: pasek miesiąca ma tylko Dochody / Wydatki / `Wynik`. Liczba „do wydania” i stan planu powstają w prywatnej metodzie `MonthPlanController::monthPlanPayload`.

## Desired End State

- **Dashboard:** dla budżetu w wykonaniu pierwszy kafelek to „listopad — do wydania”, z odznaką „Niezatwierdzony” przy propozycji i linkiem „Plan miesiąca”.
- **Strona Budżet:** karta „Miesiąc” ma wiersz „Do wydania” z odznaką stanu i linkiem do planu tego samego miesiąca.
- **Miniony miesiąc bez planu:** napis „Ten miesiąc nie ma planu” i `Wynik` jak dziś.
- **Zgodność:** te same 3730,00 zł na trzech widokach, sprawdzone testami.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Źródło liczby | Nowa klasa `App\Support\MonthToSpend` wydzielona z `monthPlanPayload`; korzystają z niej trzy controllery | Jedna liczba w trzech widokach i jedno miejsce na Set-aside z S-02 | Research / Plan |
| Kopia TS | Brak; liczba tylko z serwera w propsach | `AGENTS.md` dopuszcza kopię TS tylko dla podglądu przed zapisem | Research |
| Budżet: miejsce liczby | Osobny wiersz w karcie „Miesiąc” nad Dochody / Wydatki / `Wynik`, z odznaką obu stanów i linkiem `?month=` | Odczyt planu zostaje oddzielony od faktów (`Wynik`) | Plan |
| Budżet: miniony bez planu | `Wynik` plus wyciszony „Ten miesiąc nie ma planu”, bez puli Month Remaining | Historia wygląda jak dziś (FR-013) i jest oznaczona (FR-011) | Plan |
| Budżet: szkic | Pasek bez zmian, bez komunikatu | Plan miesiąca nie istnieje przed realizacją, a strona ma już etykietę „Szkic” | Plan |
| Dashboard: bramka kafelka | Każdy budżet bieżącego roku w wykonaniu | „Do wydania” nie zależy od kategorii równomiernych | Plan |
| Dashboard: stan i link | Odznaka tylko przy propozycji i link „Plan miesiąca” | Sygnalizuje tylko stan wymagający działania, bez dublowania powiadomienia S-07 | Plan |
| Link z Dashboardu | `budget.select-year` z nowym `destination=month-plan` | Strona planu bierze rok z sesji, a Dashboard z kalendarza; ten sam ruch co „Otwórz budżet” | Plan |
| Prop Dashboardu | `month_to_spend` zamiast `paced_pool_month_remaining_minor_units`; `current_month` zostaje dla każdego budżetu w wykonaniu | Pula nie ma już użycia, a `current_month` obsługuje linki powiadomień | Plan |
| Propsy Month Remaining na Budżecie | Bez zmian | Nie są renderowane i stoją za `BudgetMonthRemainingTest`; sprzątanie po S-07/S-08 | Plan |

## Scope

**In scope:**
- `MonthToSpend` i refaktor `MonthPlanController` bez zmiany zachowania
- kafelek Dashboardu, prop `month_to_spend`, `destination` w `select-year`
- wiersz „Do wydania” w `BudgetMonthStrip` i prop `monthToSpend`
- testy Feature, w tym zgodność 373 000 na trzech widokach
- `.docs/specs/dashboard-hierarchy.md` (historie 15–16, „Headline tiles”) i FR-REP-13 w `.docs/prd.md`

**Out of scope:**
- powiadomienia planu i `paced_month_remaining` (S-07)
- ostrzeżenia przy operacjach i Imporcie oraz kopie TS Month Remaining (S-08)
- Set-aside (S-02)
- podsumowanie miesiąca (S-09)
- usuwanie propsów Month Remaining strony Budżet
- zmiany w `.ai/rules/js.md` i ADR-0031

## Architecture / Approach

`MonthToSpend::forMonth(Budget, month, today)` składa `MonthPlanProposal` → `reading` → `MonthCategoryActuals` → kategorie wierszy → `ToSpendCalculator` i zwraca `summary(): {state, to_spend_minor_units}`. `MonthPlanController` buduje z tego samego obiektu wiersze strony planu. `DashboardController` (bieżący miesiąc UTC) i `BudgetController` (oglądany miesiąc) wołają klasę tylko dla budżetu w wykonaniu i wysyłają `summary()` jako prop. Vue tylko wyświetla wynik.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Wspólny odczyt „do wydania” | `MonthToSpend` i strona planu na nim, bez zmiany zachowania | Subtelna zmiana kolejności lub zbioru kategorii; chroni `MonthPlanPageTest` bez zmian |
| 2. Kafelek na Dashboardzie | `month_to_spend`, odznaka przy propozycji, link przez `select-year`, spec Dashboardu | Przepisanie dwóch testów bramki; link musi przełączyć rok sesji |
| 3. Pasek miesiąca Budżetu | `monthToSpend`, wiersz ze stanem i linkiem, „Ten miesiąc nie ma planu”, FR-REP-13 | Wiersz w wąskim pasku na telefonie |

**Prerequisites:** S-01 scalone (jest na `development`); kroki „Fresh checkout or worktree” z `AGENTS.md`; Sail uruchomiony dla testów PostgreSQL i podglądu.
**Estimated effort:** ~2 sesje w 3 fazach.

## Open Risks & Assumptions

- **Konflikt z S-02.** S-02 (Set-aside) zmieni wejście liczby. Jeśli scali się pierwsze, Set-aside trafi do `monthPlanPayload` i trzeba go przenieść do `MonthToSpend`. Jeśli pierwsze będzie S-06, S-02 podpina Set-aside w jednym miejscu.
- **Gałęzie S-03, S-04 i S-08** mogą dotknąć `MonthPlanController`; dziś mają tylko commity z dokumentacją.
- **Założenie:** dla budżetu w wykonaniu bieżący miesiąc nigdy nie jest `none` (`MonthPlanProposal.php:93-121`). Dashboard i tak wysyła `null` przy `none` jako strażnik typu.

## Success Criteria (Summary)

- Dashboard i strona Budżet pokazują tę samą liczbę „do wydania” co strona planu, ze stanem planu i jednym kliknięciem do niego.
- Miniony miesiąc bez planu jest oznaczony, a jego `Wynik` wygląda jak dziś.
- Strona „Plan miesiąca” działa bez zmian, a jej testy przechodzą bez modyfikacji.
