<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Domyślny procent „odkładam” w propozycji planu

- **Plan**: context/changes/default-set-aside-percent/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Weryfikacja automatyczna (worktree, obraz `sail-8.5/app`, PostgreSQL `testing_default_set_aside_percent`): `npm run build` OK; `composer ci:check` exit 0 (lint, `check:ui`, `types:check`, Vitest, 1488 testów PHP zielonych, 2 pominięte); `DefaultSetAsidePercentMigrationTest` uruchomiony na pgsql (nie pominięty); `vendor/bin/sloppy ci` 100/100, 0 nowych znalezisk (w kontenerze, cały projekt względem baseline); `pint --test` czysty (461 plików). FAIL w Plan Adherence wynika z brakującego testu z planu (F1), nie z błędu w kodzie.

## Findings

### F1 — Oszczędność zapytań z `chaperone()` nie jest przypięta testem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/EntryToSpendInputsTest.php:177
- **Detail**: Plan (Faza 3, „3. Testy”, ostatni punkt) zakłada, że `EntryToSpendInputsTest` pokazuje brak zapytania o właściciela na budżet dla budżetów z `$user->budgets()`. Plik nie jest w diffie, a istniejące testy tego nie łapią: `:177-199` porównuje liczbę zapytań przy różnej liczbie miesięcy jednego budżetu, `:201-228` liczy tylko zapytania `category_rhythms`. Usunięcie `->chaperone()` z `User::budgets()` (`app/Models/User.php:85`) dodałoby jedno zapytanie `users` na budżet w `EntryToSpendInputs::budgetPayload` (Operacje, Import), a żaden test by nie padł. Kod jest dziś poprawny.
- **Fix**: Dopisać w `EntryToSpendInputsTest` test z 1 i 3 budżetami w realizacji, który liczy zapytania `from "users"` i sprawdza, że ich liczba nie rośnie z liczbą budżetów.
- **Decision**: FIXED — `test_budgets_in_execution_read_their_owner_without_a_users_query` w `EntryToSpendInputsTest`; czerwony bez `->chaperone()` (2 zapytania `users` zamiast 0), zielony z nim.

### F2 — Podpowiedź bez separatora „·”, choć krok ręczny 4.4 jest odhaczony

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/components/MonthPlanTable.vue:438, lang/pl/ui.php:497
- **Detail**: Desired End State i krok 4.4 oczekują „10 % planowanych przychodów (9 000,00) · Zmień w ustawieniach”. Klucz `month_plan.set_aside_default_basis` to `':percent % planowanych przychodów (:amount)'`, a `TextLink` stoi po zwykłej spacji, więc na ekranie jest „… (9 000,00) Zmień w ustawieniach”. Krok 4.4 jest `[x]`, choć tekst odbiega od kryterium.
- **Fix**: Dodać „ · ” między podstawą a linkiem w szablonie (`MonthPlanTable.vue`, przed `<TextLink>`) i sprawdzić `/dev/month-plan` w stanie `proposal-default-percent`.
- **Decision**: FIXED — „·” przed `TextLink` w `MonthPlanTable.vue`, jak w `MonthPlanItemRow.vue`.

### F3 — Nieplanowana klasa `DefaultSetAside` buduje cały `MonthPlanReading`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: app/Support/DefaultSetAside.php:19-35
- **Detail**: Plan umieszcza regułę w `MonthPlanProposal::computePlan`. Implementacja wydziela `DefaultSetAside::reading()`, który sumuje przychody, liczy kwotę i zwraca kompletny `MonthPlanReading` z zakodowanym stanem `'proposal'`. To jedyne miejsce poza `MonthPlanProposal`, które tworzy odczyt planu, więc budowa stanu propozycji jest w dwóch plikach. Sąsiednie helpery statyczne (np. `AnnualPlanShare::forMonth`) zwracają liczbę. Funkcja jest czysta, bez zapytań, z odwołaniem do ADR-0081, a commit a606ba0 ją nazywa, ale plan jej nie opisuje. `MonthPlanProposal.php` ma 307 linii.
- **Fix A ⭐ Recommended**: `DefaultSetAside` zwraca tylko kwotę i podstawę (`array{percent, income_minor_units, set_aside_minor_units}` albo dwie metody), a `computePlan` buduje `MonthPlanReading` jak w gałęzi bez procentu
  - Strength: Stan odczytu powstaje w jednym pliku; helper ma kształt sąsiadów z `app/Support`, a test reguły nadal może go wołać.
  - Tradeoff: Mała przeróbka i ponowne przejście `MonthPlanProposalTest` + Sloppy.
  - Confidence: MED — nie sprawdzono, czy wydzielenie było potrzebne, by `computePlan` nie przekroczył progu god-method Sloppy.
  - Blind spot: Próg Sloppy dla `computePlan` po wciągnięciu sumowania z powrotem.
- **Fix B**: Zostawić klasę i dopisać ją do planu jako addendum (Faza 3, „Changes Required”)
  - Strength: Zero zmian w kodzie, który przechodzi bramkę.
  - Tradeoff: Rozproszenie budowy odczytu zostaje.
  - Confidence: HIGH — funkcja jest poprawna i przetestowana.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — `DefaultSetAside::plannedIncome()` i `::amount()` zwracają liczby, `computePlan` buduje `MonthPlanReading`; addendum w planie.

### F4 — Widoczność podpowiedzi poza tym, co plan rozstrzyga

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/MonthPlanTable.vue:138-140, resources/js/pages/MonthPlan.vue:85-98
- **Detail**: Podpowiedź wymaga `editable` (`state === 'proposal' && can_approve`), zgodnie z planem. Skutek, którego plan nie omawia: propozycja, której nie da się zatwierdzić (np. przyszły miesiąc przed zatwierdzeniem poprzedniego), pokazuje „odkładam” z procentu bez wyjaśnienia skąd. Podpowiedź zostaje też po ręcznej zmianie „odkładam”, więc „10 % planowanych przychodów (9 000,00)” stoi obok innej kwoty. Plan rozstrzyga tylko edycję przychodów (krok 4.5). Wiersz podpowiedzi nie ma testu Vitest ani E2E (jest tylko w sinku i jako prop w `MonthPlanPageTest`).
- **Fix**: Uznać obecne zachowanie za zamierzone i dopisać to zdanie do planu (podpowiedź opisuje kwotę startową propozycji, tylko gdy pole jest edytowalne)
  - Strength: Bez zmian w kodzie; spójne z ADR-0081 (bez przeliczania na żywo).
  - Tradeoff: Nieedytowalna propozycja nadal nie wyjaśnia kwoty.
  - Confidence: MED — to decyzja UX, nie błąd.
  - Blind spot: Jak często użytkownik ogląda przyszłą, niezatwierdzalną propozycję.
- **Decision**: FIXED — zachowanie uznane za zamierzone i dopisane do planu (Addendum).

### F5 — Trasy ustawień planu w grupie `auth` bez `verified`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: routes/settings.php:15-16
- **Detail**: Nowe trasy stoją obok profilu w grupie `auth`; Wygląd, Bezpieczeństwo i plan miesiąca (`routes/web.php:36`) są za `auth` + `verified`. Profil jest tam celowo (zmiana e-maila przed weryfikacją). Niezweryfikowany użytkownik może więc ustawić opcję planu, którego nie otworzy. Bez ekspozycji danych (zapis tylko własnego wiersza). Plan mówi „obok profilu”, więc to dziedzictwo planu.
- **Fix**: Przenieść obie trasy `month-plan-settings.*` do grupy `['auth', 'verified']` obok `appearance.edit`.
- **Decision**: FIXED — trasy przeniesione do grupy `auth` + `verified`. Uwaga: `User` nie implementuje `MustVerifyEmail`, więc `verified` dziś niczego nie blokuje; to tylko spójność grup tras, bez testu zachowania, którego aplikacja nie ma. Addendum w planie.

### F6 — Kitchen sink: kopie markupu bez atrybutów pola i nieaktualny `stateMatrix`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/SettingsKitchenSink.vue:80, :200-269
- **Detail**: Sekcje `month-plan-error` i `month-plan-saving` odtwarzają markup strony zamiast renderować `settings/MonthPlan.vue` i pomijają `min`, `max`, `step`, `inputmode` (wzór istniejącej sekcji `field-error`, więc spójny z plikiem). Wiersz `empty` w `stateMatrix` (`:80`) nadal mówi, że ustawienia zawsze mają dane, choć pusty procent to teraz realny stan (`:186`), a wiersze `error` i `disabled` nie wymieniają nowych sekcji. `tests/Feature/SettingsKitchenSinkTest.php` z listy plików planu nie został zmieniony.
- **Fix**: Uzupełnić atrybuty `Input` w obu kopiach i wymienić sekcje „Plan miesiąca” w wierszach `empty`, `error` i `disabled` `stateMatrix`.
- **Decision**: FIXED — `min`/`max`/`step`/`inputmode` w obu kopiach sinka, wiersze `empty`, `error` i `disabled` `stateMatrix` wymieniają sekcje Planu miesiąca.

### F7 — Granica 0–100 tylko na dwóch wejściach; brak testu v11 bez klucza

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_11_12_094753_add_default_set_aside_percent_to_users_table.php:18, app/Actions/RestoreUserData.php:162-163
- **Detail**: Na PostgreSQL `unsignedTinyInteger` to `int2` bez CHECK, więc 0–100 pilnują tylko `MonthPlanSettingsUpdateRequest` i `RestoreUserData::readSnapshot`; `DefaultSetAside` nie przycina wartości (np. −5 z ręcznego SQL dałoby Draw). Żadna migracja w repo nie używa CHECK, więc to zgodne z konwencją. Odmowa snapshotu v11 bez klucza `default_set_aside_percent` jest w kodzie, ale nie ma testu.
- **Fix**: Dopisać w `UserSnapshotCommandTest` przypadek v11 bez klucza (odmowa); CHECK w bazie zostawić, dopóki projekt nie przyjmie takiej konwencji.
- **Decision**: FIXED — `test_restore_command_refuses_a_v11_snapshot_without_the_default_set_aside_percent`; CHECK w bazie świadomie pominięty (brak konwencji w repo).

### F8 — Test poprzedniego budżetu twierdzi więcej, niż sprawdza

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/MonthPlanProposalTest.php:580-592
- **Detail**: `test_the_previous_budget_proposal_also_uses_the_default_percent` sprawdza tylko „odkładam” stycznia (90 000), które przy procencie zależy wyłącznie od przychodów stycznia; „odkładam” grudniowej propozycji poprzedniego budżetu nie jest przy procencie nigdzie czytane, więc nazwa obiecuje coś nieobserwowalnego. Test faktycznie przypina to, że styczeń liczy procent z przychodów proponowanych na podstawie grudnia poprzedniego budżetu.
- **Fix**: Zmienić nazwę na `test_january_takes_the_percent_of_income_proposed_from_the_previous_budget` (bez zmiany asercji).
- **Decision**: FIXED — nazwa zmieniona na `test_january_takes_the_percent_of_income_proposed_from_the_previous_budget`.

## Triage

- Fixed: F1, F2, F3 (Fix A), F4, F5, F6, F7, F8 (8)
