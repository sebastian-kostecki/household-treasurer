# New Budget From Category List Implementation Plan

## Overview

S-20 (FR-005): nowy budżet przestaje kopiować pozycje budżetu wskazanego w oknie. Dostaje pozycję dla każdej aktywnej kategorii listy, a plan i flagi tej pozycji podpowiada najnowszy wcześniejszy budżet użytkownika. Okno „Nowy budżet” zamiast listy „Kopiuj kategorie z” mówi, ile kategorii dostanie szkic i z którego roku przyjdzie plan.

## Current State Analysis

- `CreateBudget::handle(User, int $year, ?int $sourceBudgetId)` (`app/Actions/CreateBudget.php:13`) tworzy szkic i, gdy podano źródło, `copyCategories()` (`:36-56`) wstawia jednym `insert` pozycje dla linii źródła z `BudgetLine::openForChoice()` (`app/Models/BudgetLine.php:98`): `category_id` źródła, plan = Current Plan, flagi źródła, Initial Plan i wykonanie 0. S-18 zostawił ten zbiór celowo (`context/archive/2026-11-09-category-list-after-migration/plan.md:50`).
- Kategoria aktywna na liście bez pozycji w źródle (np. utworzona, gdy szkicu jeszcze nie było, albo usunięta „Usuń” ze szkicu) dziś nie trafia do nowego budżetu. To jest luka względem FR-005 i ADR-0063 („A new Budget gets Budget Lines from the active Categories of the list, with the plan suggested from the previous year’s Current Plan”).
- `StoreBudgetRequest` (`app/Http/Requests/StoreBudgetRequest.php`) waliduje `source_budget_id` (własny budżet, rok mniejszy, ma otwarte pozycje).
- `CreateBudgetDialog.vue` pokazuje `select` „Kopiuj kategorie z [rok | Bez kopiowania]” z propu powłoki `copyableBudgetSources` (`app/Support/ShellState.php:44,52-64`, `resources/js/types/shell.ts:9-17`); domyślne źródło to rok − 1, a przy przerwie najnowszy wcześniejszy (`resolveDefaultSourceBudgetId`). `budgetYears` w powłoce zawiera wszystkie lata, także szkice (`app/Support/SelectedBudgetYear.php:40`).
- Okno jest montowane w `AppSidebarLayout.vue`, `Dashboard.vue`, `DashboardBudgetBlock.vue`, `BudgetEmptyState.vue:20` (używany przez `Budget.vue` i `BudgetKitchenSink.vue`) i w kitchen sinku `/dev/ui/dialogs` (`DialogsKitchenSink.vue:308-316`, stany `budget-create`, `budget-errors`). Okno czyta `budgetYears` i źródła z powłoki (`useShell()`, `CreateBudgetDialog.vue:46`); jego propy to `open`, `yearInputId`, `suggestedYear`, `initialYear`, `form` (`:24-40`, `form` „used only by the dialogs kitchen sink”). `DialogsKitchenSinkController` nie wysyła danych okna.
- Testy z `source_budget_id`: `tests/Feature/BudgetTest.php` (15 wystąpień, `:951-1200`), `CategoryListBudgetPageTest.php` (4), `CategoryMonthItemKindTest.php` (1), `CategoryPacedFlagTest.php` (1). E2E `tests/e2e/sidebar-year-refresh.spec.ts:46-48` i `command-palette.spec.ts:128` tworzą lub otwierają okno po nazwie „Nowy budżet” i przycisku „Utwórz budżet”.
- Produkcja (`context/archive/2026-11-09-category-list-after-migration/migration-trial.md:50-58`): 41 kategorii listy (40 aktywnych, „Wakacje” archiwalna), budżet 2026 w wykonaniu (39 pozycji), szkic 2027 (38 pozycji). Pary „Jedzenie poza domem”/„Restauracje” i „Eksploatacja domu”/„Utrzymanie domu” czekają na S-21.

## Desired End State

Tworzysz budżet 2028: szkic dostaje 40 pozycji, po jednej na aktywną kategorię listy. Kategoria z pozycją w szkicu 2027 ma plan i flagi z tej pozycji; kategoria bez niej („Jedzenie poza domem”, „Eksploatacja domu”) ma plan 0, flagę „równomierna” i rodzaj „bieżąca”. Okno przed kliknięciem pokazuje „Szkic dostanie 40 aktywnych kategorii z listy. Plan podpowie budżet 2027.” Szkic 2027 i budżet 2026 nie zmieniają się.

Weryfikacja: testy Feature reguły na PostgreSQL, Vitest funkcji zdania, `assertInertia` propu powłoki, zielone E2E okna i ręczny przebieg na devie.

### Key Discoveries:

- Polska odmiana liczebnika jest gotowa: `activeCountForm(count): 'one' | 'few' | 'many'` (`resources/js/lib/category-list.ts:26`, test `category-list.test.ts:31`), używana z kluczami `_one/_few/_many` (`lang/pl/ui.php:537-539`, `pages/Categories.vue:26`).
- `FormField` ma `hint` z `aria-describedby` (`resources/js/components/FormField.vue:9,64`); zdanie okna to hint pola roku, nie osobny blok.
- Domyślne flagi nowej pozycji bez źródła: `is_paced = true`, `month_item_kind = Running` (tak jak `CreateCategory.php:36-39`, także dla przychodu).
- Tłumaczenia `t.budget` są typowane jako `Record<string, string>` (`resources/js/types/shell.ts:44`), więc nowe klucze dochodzą tylko w `lang/pl/ui.php`; `npm run check:ui` pilnuje, żeby tekst nie był literałem w widoku.
- Twarda reguła AGENTS.md: zmiana reguły słownika zaczyna się od wpisu w `context/foundation/glossary.md` i nowego ADR (następny wolny numer: 0066). Słownik mówi dziś „A new Budget takes it from the Budget Line of that Category in the previous year” (Month Item Kind, `glossary.md:84`), a uzgodnione „najnowszy wcześniejszy budżet” zmienia to sformułowanie.

## What We're NOT Doing

- Nie zmieniamy istniejących budżetów: szkic 2027 na produkcji zostaje z 38 pozycjami; brakujące aktywne kategorie dołączają przy pierwszym użyciu (ADR-0063), a pary nazw scala S-21.
- Bez akcji „Uzupełnij z listy” w szkicu i bez ponownego tworzenia szkicu 2027.
- Bez wyboru roku planu w oknie i bez opcji „Bez kopiowania” / pustego szkicu; rok wstecz (np. 2025 przy 2026 i 2027) nie jest wyjątkiem: aktywne kategorie z planem 0.
- Bez planu z późniejszego budżetu, gdy wcześniejszego nie ma.
- Bez zmian w snapshocie (`user:backup` / `user:restore`), migracjach, rytmie (F-05), grupach (S-22) i selektorach kategorii (S-19).
- Bez nowego testu E2E; istniejące E2E okna ma przejść.
- Bez edycji ADR-0063 (ADR się nie przepisuje; ADR-0066 go doprecyzowuje) i bez edycji ryzyka S-20 w roadmapie poza statusem.

## Implementation Approach

Reguła domenowa najpierw trafia do słownika i ADR-0066, potem do serwera, na końcu do okna. Serwer zmienia tylko `CreateBudget` i `StoreBudgetRequest`; okno liczy rok źródła po stronie klienta z `budgetYears` (ta sama reguła „najnowszy rok mniejszy od docelowego”), a z serwera potrzebuje tylko liczby aktywnych kategorii, która zastępuje `copyableBudgetSources` w powłoce. Po fazie 2 okno przez chwilę wysyła jeszcze `source_budget_id`, które serwer ignoruje, więc każda faza kończy się zielonymi testami.

## Critical Implementation Details

- **Źródło wybierane przed utworzeniem budżetu.** Najnowszy wcześniejszy budżet (`year < $year`, najwyższy `year`, szkic się liczy, także bez pozycji: bez `whereHas` na pozycjach, inaczej okno i serwer wskażą różne lata) czytaj przed `create()` nowego szkicu albo z warunkiem `year < $year`, żeby nowy budżet nigdy nie był własnym źródłem. Pozycje źródła czytaj raz, indeksowane po `category_id`; insert zostaje jednym zapytaniem (Sloppy flaguje zapytanie w pętli).
- **Pozycja źródła zarchiwizowana przy aktywnej kategorii** (możliwe tylko ze starych danych przez regułę (d) migracji): plan 0, flagi z tej pozycji. Na danych produkcji przypadek nie występuje; test buduje go ręcznie.

## Phase 1: Słownik i ADR-0066

### Overview

Zapisuje doprecyzowanie FR-005, zanim ruszy kod: co znaczy „poprzedni rok” dla nowego budżetu, co dostaje rok bez wcześniejszego budżetu i że istniejące szkice nie są uzupełniane.

### Changes Required:

#### 1. ADR-0066

**File**: `context/foundation/adr/0066-new-budget-from-active-list-categories.md`

**Intent**: Nowy ADR w stylu ADR-0063/0065 (angielski, akapity z etykietami, „We rejected:”). Doprecyzowuje słowa „previous year” o nowym budżecie we wszystkich trzech miejscach ADR-0063: „Joining Budget Lines” (`0063-…:7`), nowe brzmienie ADR-0014 (`:28`) i część nowego brzmienia ADR-0033 o Month Item Kind (`:31`). Żadnej innej reguły ADR-0063 nie zmienia.

**Contract**: Treść ustala:
- Nowy budżet dostaje jedną Budget Line na każdą aktywną (nie archiwalną) kategorię listy użytkownika; archiwalna nie dostaje żadnej.
- Źródło planu i flag (Paced / One-off, Month Item Kind) to najnowszy wcześniejszy budżet użytkownika (najwyższy rok mniejszy od nowego, szkic też się liczy). Plan = Current Plan pozycji tej kategorii w źródle; Initial Plan i Execution 0.
- Bez źródła, bez pozycji tej kategorii w źródle: plan 0, flagi domyślne (Paced, Running). Pozycja w źródle zarchiwizowana: plan 0, flagi z pozycji.
- Rok wcześniejszy niż każdy istniejący budżet nie jest wyjątkiem (aktywne kategorie, plan 0). Pusta lista daje pusty szkic.
- Najnowszy wcześniejszy budżet jest źródłem także wtedy, gdy nie ma żadnej pozycji (np. stary pusty szkic): wszystkie aktywne kategorie dostają plan 0 i flagi domyślne, starszy budżet nie jest brany pod uwagę. Dzięki temu okno liczy rok źródła z samego `budgetYears`.
- Nie ma wyboru źródła ani opcji bez kategorii; istniejące szkice nie są uzupełniane przy wdrożeniu ani przy tworzeniu innego budżetu.
- Zakres: reguła dotyczy tylko tworzenia budżetu. Propozycja stycznia (ADR-0033 w brzmieniu ADR-0063 `:31`) dalej czyta grudzień tej samej kategorii w budżecie roku − 1.
- Przykład na danych produkcji: 2028 tworzony przy 2026 (wykonanie) i szkicu 2027 bierze plany ze szkicu 2027; „Jedzenie poza domem” i „Eksploatacja domu” (brak pozycji w 2027) dostają plan 0; szkic 2027 zostaje z 38 pozycjami.
- We rejected: ściśle rok − 1 (przerwa w latach daje same zera); tylko budżet w wykonaniu (pomija poprawki ze szkicu); plan z późniejszego budżetu dla roku wstecz; pusty szkic dla roku wstecz (wyjątek od FR-005); wybór roku planu w oknie; akcja „Uzupełnij z listy” (na danych produkcji podwoiłaby limity par przed S-21).

#### 2. Słownik

**File**: `context/foundation/glossary.md`

**Intent**: Wpisy, które mówią o nowym budżecie, odsyłają do reguły ADR-0066 tym samym sformułowaniem.

**Contract**: Wpis **Budget** (`:24`) dostaje zdanie: nowy budżet dostaje Budget Line dla każdej aktywnej kategorii listy, z planem i flagami z najnowszego wcześniejszego budżetu (szkic też), plan 0 i flagi domyślne bez niego (ADR-0066). Wpis **Month Item Kind** (`:84`): „from the Budget Line of that Category in the previous year” → „in the newest earlier Budget (ADR-0066)”. Wpis **Budget Line** (`:36`) bez zmian treści reguł dołączania.

### Success Criteria:

#### Automated Verification:

- Plik ADR istnieje i ma następny wolny numer: `ls context/foundation/adr/0066-*.md`
- Słownik odsyła do ADR-0066 w obu wpisach: `grep -c "ADR-0066" context/foundation/glossary.md` zwraca co najmniej 2

#### Manual Verification:

- ADR-0066 i wpisy słownika opisują regułę zgodnie z decyzjami z tabeli w `plan-brief.md` (źródło, rok wstecz, szkic 2027 bez zmian)

**Implementation Note**: Po fazie 1 zatrzymaj się na potwierdzenie treści ADR przez użytkownika przed kodem.

---

## Phase 2: Reguła tworzenia na serwerze

### Overview

`CreateBudget` buduje szkic z aktywnych kategorii listy według ADR-0066; żądanie traci `source_budget_id`. Testy Feature kopiowania przechodzą na nową regułę.

### Changes Required:

#### 1. Akcja tworzenia

**File**: `app/Actions/CreateBudget.php`

**Intent**: Zastąpić `copyCategories()` budowaniem pozycji z listy: aktywne kategorie użytkownika, plan i flagi z pozycji tej kategorii w najnowszym wcześniejszym budżecie, w jednej transakcji i jednym `insert`. PHPDoc opisuje regułę z odwołaniem do ADR-0066.

**Contract**: `handle(User $user, int $year): Budget` (parametr `?int $sourceBudgetId` znika). Dla każdej `Category` użytkownika z `archived_at IS NULL`: `budget_id` nowego szkicu, `category_id` kategorii, `planned_amount_minor_units` = Current Plan pozycji źródła, gdy istnieje i nie jest zarchiwizowana, inaczej 0; `is_paced` / `month_item_kind` z pozycji źródła, gdy istnieje (także zarchiwizowanej), inaczej `true` / `Running`; `initial_plan_minor_units` i `execution_minor_units` 0; `archived_at` null.

#### 2. Żądanie i kontroler

**File**: `app/Http/Requests/StoreBudgetRequest.php`, `app/Http/Controllers/BudgetController.php`

**Intent**: Usunąć regułę, `prepareForValidation` i `withValidator` dla `source_budget_id`; kontroler woła `handle($user, $year)`. Przesłane `source_budget_id` jest ignorowane (nie jest w `validated()`).

**Contract**: `rules()` zawiera tylko `year` (wymagany, integer, 2000–2100, unikalny dla użytkownika). `BudgetController::store` (`:103-121`) bez trzeciego argumentu.

#### 3. Testy Feature

**File**: `tests/Feature/BudgetTest.php`, `tests/Feature/CategoryListBudgetPageTest.php`, `tests/Feature/CategoryMonthItemKindTest.php`, `tests/Feature/CategoryPacedFlagTest.php`

**Intent**: Testy kopiowania z `source_budget_id` (`BudgetTest.php:990-1200`) przepisać na regułę ADR-0066. Testy walidacji `source_budget_id` (budżet z przyszłości, cudzy, nieznany, bez otwartych pozycji, rok równy) usunąć, bo pole przestało istnieć: zmienione wymaganie, zatwierdzone tym planem. Testy flag w `CategoryMonthItemKindTest` i `CategoryPacedFlagTest` sprawdzają przeniesienie flag z najnowszego wcześniejszego budżetu bez przekazywania źródła. `test_shell_exposes_copyable_budget_sources` zostaje do fazy 3.

Do usunięcia (wymaganie znika, asercji nie przerabiamy na nowe zachowanie):
- `BudgetTest`: `test_creating_budget_without_source_creates_empty_draft_even_when_source_exists` (`:1054`, „pusty szkic” przestaje istnieć), `test_future_year_budget_is_rejected_as_source` (`:1099`), `test_same_year_budget_is_rejected_as_source` (`:1116`), `test_other_users_budget_is_rejected_as_source` (`:1133`), `test_budget_with_only_archived_categories_is_rejected_as_source` (`:1151`), `test_unknown_source_budget_id_creates_no_budget` (`:1168`), `test_creating_budget_with_source_still_blocks_duplicate_year` (`:1183`; duplikat roku pokrywa `test_creating_second_budget_for_same_year_is_blocked`, `:62`);
- `CategoryListBudgetPageTest`: `test_a_source_budget_with_only_list_archived_categories_is_refused` (`:338`).

Do przepisania na regułę ADR-0066 (bez `source_budget_id`):
- `BudgetTest`: `test_creating_budget_with_source_copies_non_archived_categories_from_previous_year` (`:970`), `test_gap_year_copy_uses_newest_earlier_copyable_budget` (`:1073`);
- `CategoryListBudgetPageTest`: `test_a_category_archived_on_the_list_leaves_the_choice_where_its_line_stays_active` (`:293`, wywołanie `:329`), `test_a_new_budget_gets_lines_of_the_same_list_categories_without_copying_records` (`:431`);
- `CategoryPacedFlagTest::test_creating_budget_with_source_copies_paced_flag` (`:146`), `CategoryMonthItemKindTest::test_creating_budget_with_source_copies_month_item_kind` (`:232`).

**Contract**: Przypadki (nazwy testów opisowe, po angielsku jak w pliku):
- wszystkie aktywne kategorie listy dostają pozycję; archiwalna na liście nie dostaje;
- plan = Current Plan źródła, Initial Plan i wykonanie 0, flagi ze źródła;
- aktywna kategoria bez pozycji w źródle: plan 0, `is_paced` true, `Running`;
- przerwa w latach: 2029 przy 2026 i 2027 bierze plany z 2027;
- szkic jako źródło: 2028 przy szkicu 2027 bierze plany wpisane w szkicu;
- pusty najnowszy wcześniejszy budżet: 2028 przy 2026 z planami i pustym szkicu 2027 dostaje wszystkie aktywne kategorie z planem 0 (2026 nie jest źródłem);
- rok wstecz: 2025 przy 2026 dostaje wszystkie aktywne kategorie z planem 0;
- zarchiwizowana pozycja źródła przy aktywnej kategorii: plan 0, flagi z pozycji;
- pusta lista daje szkic bez pozycji;
- tworzenie budżetu nie zmienia pozycji innych budżetów (liczba i plany szkicu 2027 bez zmian);
- kategorie innego użytkownika nie trafiają do szkicu.

#### 4. Reguła ścieżki

**File**: `.ai/rules/categories.md`

**Intent**: Reguła ładowana przy edycji `CreateBudget` przestaje mówić, że źródło nowego budżetu to `openForChoice()`.

**Contract**: W `:35` nawias „(selector options, the source of Create Budget)” traci „the source of Create Budget”; dochodzi zdanie: „Create Budget takes the active Categories of the list (`categories.archived_at IS NULL`), with the plan and flags of their Budget Line in the newest earlier Budget (ADR-0066).”

### Success Criteria:

#### Automated Verification:

- Testy budżetu i flag przechodzą na PostgreSQL (polecenie worktree z AGENTS.md): `artisan test --compact tests/Feature/BudgetTest.php tests/Feature/CategoryListBudgetPageTest.php tests/Feature/CategoryMonthItemKindTest.php tests/Feature/CategoryPacedFlagTest.php`
- `rg "sourceBudgetId|copyCategories" app` nie zwraca nic
- Formatowanie: `vendor/bin/pint --dirty --format agent`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Na devie budżet utworzony z okna (jeszcze ze starym `select`) ma wszystkie aktywne kategorie listy z planami z najnowszego wcześniejszego budżetu

**Implementation Note**: Po fazie 2 zatrzymaj się na ręczne potwierdzenie przed oknem.

---

## Phase 3: Okno „Nowy budżet”

### Overview

Powłoka podaje liczbę aktywnych kategorii zamiast budżetów do kopiowania; okno pokazuje pod polem roku zdanie o tym, co dostanie szkic, i nie ma już wyboru źródła.

### Changes Required:

#### 1. Prop powłoki (PHP i TS w jednym commicie)

**File**: `app/Support/ShellState.php`, `resources/js/types/shell.ts`, `tests/Feature/BudgetTest.php`

**Intent**: `copyableBudgetSources` i `copyableBudgetSourcesForUser()` znikają; dochodzi `activeCategoryCount` liczone jednym `count()` na kategoriach użytkownika bez `archived_at` (0 bez użytkownika). `test_shell_exposes_copyable_budget_sources` zamienia się na test `assertInertia` nowego propu (liczy tylko aktywne kategorie tego użytkownika).

**Contract**: `shell.activeCategoryCount: int` / `activeCategoryCount: number`; typ `CopyableBudgetSource` usunięty. PHPDoc kształtu w `ShellState.php:15-16` zaktualizowany.

#### 2. Funkcja zdania

**File**: `resources/js/lib/create-budget-summary.ts`, `resources/js/lib/create-budget-summary.test.ts`

**Intent**: Czysta funkcja wybiera wariant zdania z roku docelowego, `budgetYears` i liczby aktywnych kategorii, z tą samą regułą źródła co serwer (najwyższy rok mniejszy od docelowego).

**Contract**: `createBudgetSummary(targetYear: number, budgetYears: number[], activeCategoryCount: number)` zwraca `null` (rok nie jest liczbą całkowitą), `{ kind: 'empty' }` (0 kategorii), `{ kind: 'from-source', count, sourceYear }` albo `{ kind: 'no-source', count }`. Vitest: 2028 przy [2026, 2027] → źródło 2027; 2029 przy [2026, 2027] → 2027; 2025 przy [2026, 2027] → brak źródła; pierwszy budżet → brak źródła; 0 kategorii → `empty`; rok równy istniejącemu (2027 przy [2026, 2027]) → źródło 2026; `NaN` → `null`.

#### 3. Okno i teksty

**File**: `resources/js/components/CreateBudgetDialog.vue`, `lang/pl/ui.php`

**Intent**: Usunąć `select` źródła, ukryte `source_budget_id`, `availableSources` i `resolveDefaultSourceBudgetId`; zdanie z `createBudgetSummary` jest `hint` pola roku i zmienia się z rokiem. Liczebnik przez `activeCountForm`. Opis okna mówi o roku bez wzmianki o kopiowaniu.

**Contract**: W `lang/pl/ui.php` (`budget`) usunięte `copy_source_label`, `copy_source_none`, `copy_source_description`; nowe klucze z `:count` / `:year`:
- `create_budget_from_source_one|few|many`: „Szkic dostanie :count aktywną kategorię z listy. Plan podpowie budżet :year.” / „… :count aktywne kategorie …” / „… :count aktywnych kategorii …”
- `create_budget_no_source_one|few|many`: „Szkic dostanie :count aktywną kategorię z listy z planem 0.” / „… aktywne kategorie …” / „… aktywnych kategorii …”
- `create_budget_empty`: „Szkic będzie pusty. Kategorie dodasz w Budżecie.”

Tytuł „Nowy budżet” i przycisk „Utwórz budżet” bez zmian (E2E szuka ich po nazwie).

Okno dostaje opcjonalne propy `budgetYears?: number[]` i `activeCategoryCount?: number`; podane nadpisują wartości z powłoki, pominięte (wszystkie miejsca montowania poza kitchen sinkiem) zostawiają powłokę. Komentarz przy propach jak przy `form`: „used only by the dialogs kitchen sink”.

#### 4. Kitchen sink

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Stany okna pokrywają trzy warianty zdania; kitchen sink podaje `initialYear`, `budgetYears` i `activeCategoryCount` propami okna (#3), bez zależności od danych deva (np. 2028 przy [2026, 2027] i 40; 2025 przy [2026, 2027] i 40; 0 kategorii).

**Contract**: Stany `budget-create` (ze źródłem), nowe `budget-create-no-source` i `budget-create-empty`, oraz `budget-errors` bez zmian; etykiety w mapie nazw stanów (`:93-94`).

### Success Criteria:

#### Automated Verification:

- Vitest funkcji zdania i kategorii: `npm run test -- resources/js/lib/create-budget-summary.test.ts resources/js/lib/category-list.test.ts`
- Cały Vitest, w tym `ui-contract.test.ts`: `npm run test`
- Typy po buildzie: `npm run build && npm run types:check`
- Bez literałów w widokach: `npm run check:ui`
- Lint i format: `npm run check`
- Prop powłoki na PostgreSQL: `artisan test --compact tests/Feature/BudgetTest.php`
- `rg "copyableBudgetSources|CopyableBudgetSource|source_budget_id|copy_source" app resources lang tests` nie zwraca nic
- E2E okna: `npx playwright test tests/e2e/sidebar-year-refresh.spec.ts tests/e2e/command-palette.spec.ts`
- Bramka: `composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/dialogs`: trzy warianty zdania wyglądają poprawnie w jasnym i ciemnym motywie, liczebnik odmienia się (1, 2, 5)
- Na devie zmiana roku w oknie zmienia rok źródła w zdaniu (np. 2029 przy przerwie), a po „Utwórz budżet” Budżet pokazuje tyle kategorii, ile mówiło zdanie

**Implementation Note**: Po fazie 3 zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `create-budget-summary.test.ts`: wybór roku źródła (następny rok, przerwa, rok wstecz, pierwszy budżet, rok zajęty), wariant pusty, rok niepoprawny.

### Integration Tests:

- Feature na PostgreSQL: reguła ADR-0066 (zbiór, plan, flagi, archiwum na obu poziomach, przerwa, szkic jako źródło, rok wstecz, pusta lista, inne budżety nietknięte, izolacja użytkowników) i `assertInertia` propu `activeCategoryCount`.
- E2E istniejące: `sidebar-year-refresh.spec.ts`, `command-palette.spec.ts`.

### Manual Testing Steps:

1. Na devie otwórz „Nowy budżet” z paska bocznego; sprawdź zdanie dla roku domyślnego.
2. Zmień rok na rok z przerwą i na rok wcześniejszy niż istniejące; zdanie zmienia rok źródła albo mówi o planie 0.
3. Utwórz budżet i porównaj liczbę kategorii i plany z budżetem źródłowym; usuń budżet testowy.

## Performance Considerations

Powłoka zamienia jedno zapytanie (`budgets` z `whereHas`) na jedno `count()` na `categories`; liczba zapytań na żądanie bez zmian. `CreateBudget` robi stałą liczbę zapytań (źródło, jego pozycje, kategorie, jeden insert).

## Migration Notes

Bez migracji i bez zmiany danych. Istniejący szkic 2027 na produkcji zostaje bez zmian (ADR-0066). Wycofanie to revert kodu.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-20), PRD: `context/foundation/prd-v3.md:167` (FR-005)
- ADR: `context/foundation/adr/0063-one-category-list-across-years.md`, `0065-operations-reference-budget-line-and-reversible-category-migration.md`
- S-18: `context/archive/2026-11-09-category-list-after-migration/plan.md:50,235`, `migration-trial.md:50-58`
- Kod: `app/Actions/CreateBudget.php:13-56`, `app/Http/Requests/StoreBudgetRequest.php`, `app/Support/ShellState.php:44,52-64`, `resources/js/components/CreateBudgetDialog.vue`, `resources/js/lib/category-list.ts:26`, `app/Actions/CreateCategory.php:36-39`
- Reguły: `.ai/rules/categories.md` (`:35` do zmiany w fazie 2), `.ai/rules/js-dialogs.md`, `.ai/rules/inertia-props.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Słownik i ADR-0066

#### Automated

- [x] 1.1 Plik ADR istnieje i ma następny wolny numer — b86be67
- [x] 1.2 Słownik odsyła do ADR-0066 w obu wpisach — b86be67

#### Manual

- [x] 1.3 ADR-0066 i wpisy słownika opisują regułę zgodnie z decyzjami — b86be67

### Phase 2: Reguła tworzenia na serwerze

#### Automated

- [x] 2.1 Testy budżetu i flag przechodzą na PostgreSQL
- [x] 2.2 Brak `sourceBudgetId` i `copyCategories` w `app`
- [x] 2.3 Formatowanie Pint
- [x] 2.4 Bramka `composer ci:check` i `sloppy ci`

#### Manual

- [x] 2.5 Budżet utworzony na devie ma wszystkie aktywne kategorie z planami ze źródła

### Phase 3: Okno „Nowy budżet”

#### Automated

- [ ] 3.1 Vitest funkcji zdania i kategorii
- [ ] 3.2 Cały Vitest, w tym `ui-contract.test.ts`
- [ ] 3.3 Typy po buildzie
- [ ] 3.4 Bez literałów w widokach (`check:ui`)
- [ ] 3.5 Lint i format (`npm run check`)
- [ ] 3.6 Prop powłoki na PostgreSQL
- [ ] 3.7 Brak starych nazw propu, pola i kluczy
- [ ] 3.8 E2E okna
- [ ] 3.9 Bramka `composer ci:check` i `sloppy ci`

#### Manual

- [ ] 3.10 Trzy warianty zdania w kitchen sinku, oba motywy, odmiana liczebnika
- [ ] 3.11 Zdanie na devie zmienia się z rokiem, a utworzony budżet ma zapowiedzianą liczbę kategorii
