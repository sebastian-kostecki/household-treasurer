# Stan roku w menu „Rok budżetu” (S-38) — Implementation Plan

## Overview

Menu „Rok budżetu” w sidebarze dostaje wariant B karty `Sidebar` z design systemu Atrament (MS-05):

- przycisk roku pokazuje stan wybranego roku: pigułkę „bieżący” (rok kalendarza) i „szkic” (faza `Draft`);
- menu dzieli lata na „Ostatnie” (3 najnowsze) i „Starsze”, z tymi samymi oznakami przy każdym roku;
- „Dodaj rok N” ma podpis „szkic z planem roku N−1” (ADR-0068);
- menu jest ciemne jak paleta ⌘K.

Żeby to pokazać, `shell.budgetYears` przestaje być listą lat i staje się listą lat z fazą. Decyzję zapisuje nowy ADR-0097, który rozwija ADR-0087.

## Current State Analysis

- `ShellState::forRequest` (`app/Support/ShellState.php:28,42`) wysyła `budgetYears` jako `list<int>` z `SelectedBudgetYear::budgetYearsForUser` (`app/Support/SelectedBudgetYear.php:54`). Tej samej listy intów używa `SelectedBudgetYear::resolve` (`:19`), a `ShellState` wylicza z niej `firstRun.hasBudget` (`:33`).
- `BudgetPhase` ma dwie wartości: `draft` i `execution` (`app/Enums/BudgetPhase.php`). `BudgetFactory` domyślnie tworzy `Draft` (`database/factories/BudgetFactory.php:23`).
- Konsumenci `shell.budgetYears`:
  - `BudgetYearSwitcher.vue` (przez `AppSidebarLayout.vue:26` → `AppSidebar.vue:26,73`);
  - `useCommandPalette.ts:130` (`nextBudgetYear` przez `budgetYearMenu`);
  - `CreateBudgetDialog.vue:66,75` (rok domyślny i `createBudgetSummary`);
  - `Dashboard.vue:93` (`.includes`);
  - `useTranslations.test.ts:14-56`;
  - `SidebarKitchenSink.vue` (ramki `yearFrames` i `frames`);
  - testy `AppShellTest.php:66`, `BudgetTest.php:375` i `LoadBudgetSheetTest.php:138` (ten ostatni zdejmuje klucz, więc nie zależy od kształtu).
- `budgetYearMenu` (`resources/js/lib/budget-year-menu.ts`) zwraca `years` (unikalne, od najnowszego, `selected`) i `createYear`. Testy są w `budget-year-menu.test.ts`.
- `BudgetYearSwitcher.vue`:
  - przycisk `.sidebar-year-trigger` z `aria-labelledby` etykiety i roku;
  - `DropdownMenuContent` w domyślnych kolorach popovera;
  - `DropdownMenuRadioGroup` lat, separator i „Dodaj rok N”.
- Helper E2E `tests/e2e/support/budget-year.ts` opiera się na nazwach dostępnych:
  - przycisk ma nazwę `^Rok budżetu \d{4}$`;
  - rok w menu to `menuitemradio` o nazwie dokładnie `2026`;
  - pozycja „Dodaj” to `menuitem` o nazwie `^Dodaj budżet na rok \d{4}$`.
- Paleta ⌘K ma ciemne kolory z klas `.command-palette*` w `resources/css/app.css:650-700`, a nie z edycji `components/ui`. Tak samo zrobimy ciemne menu roku.
- Karta `Sidebar` (artefakt Atrament, `project/components/Sidebar/README.md` i `preview.html`, `bundle.css:441-458`):
  - na przycisku rok w JetBrains Mono, pigułka `ht-ystate--now` (obrys `sidebar-border`, tekst `sidebar-foreground`) albo `ht-ystate--draft` (`warning` na `warning-soft`), wyrównana do prawej przed `ChevronsUpDown`;
  - w menu rok wybrany ma ptaszek na `sidebar-accent`, szkic ma pigułkę „szkic”, a rok bieżący notkę „bieżący” (11 px, `sidebar-foreground`);
  - nagłówki grup są w stylu `label-caps`, a pod „Dodaj rok N” stoi `small` z podpisem;
  - „Bieżący” to rok kalendarza, a nie stan budżetu. Rok w realizacji, który nie jest bieżący, nie ma pigułki.
- ADR-0068: N = najwyższy rok + 1, a źródło planu to najnowszy wcześniejszy budżet, czyli zawsze N−1. Podpis jest więc zawsze prawdziwy. Przy pustym stanie nie ma podpisu, bo nie ma źródła.
- `LandingAppPreview.vue:134` rysuje statyczną kopię przycisku roku 2026 (ADR-0057).

## Desired End State

- `shell.budgetYears` to `list<{ year: int, phase: 'draft' | 'execution' }>` rosnąco po roku, po stronie PHP i TS. Każdy konsument działa jak dotąd.
- Przycisk roku pokazuje rok w JetBrains Mono oraz:
  - „bieżący”, gdy wybrany rok to rok kalendarza w przeglądarce;
  - „szkic”, gdy wybrany rok ma fazę `draft`;
  - obie pigułki, gdy zachodzą oba warunki;
  - żadnej pigułki, gdy rok jest w realizacji i nie jest bieżący.
- Otwarte menu ma tło `sidebar`, tekst `sidebar-strong`, ramkę `sidebar-border` i `shadow-dialog`:
  - od 4 lat są nagłówki „Ostatnie” (3 najnowsze lata) i „Starsze” (reszta), a przy ≤3 latach płaska lista bez nagłówków;
  - rok wybrany ma ptaszek i tło `sidebar-accent`;
  - szkic ma pigułkę „szkic”, a rok bieżący notkę „bieżący” (oba, gdy oba warunki);
  - pod separatorem stoi „Dodaj rok N” z podpisem „szkic z planem roku N−1”.
- Bez budżetu zostaje jeden przycisk „Dodaj rok <bieżący rok>”, bez podpisu.
- Nazwy dostępne się nie zmieniają: przycisk „Rok budżetu 2026”, rok „2026”, pozycja „Dodaj budżet na rok 2028”. Stany i podpis są opisem (`aria-describedby`), więc helper E2E działa bez zmian.
- Kitchen sink `/dev/ui/sidebar` pokazuje w obu motywach stany:
  - jeden rok bieżący w realizacji;
  - wybrany szkic, który nie jest bieżący;
  - rok bieżący będący szkicem;
  - ≤3 lata z otwartym menu bez nagłówków;
  - 2020–2027 z otwartym menu i grupami;
  - bez budżetu;
  - oczekiwanie.
- ADR-0097, `.ai/rules/js-sidebar.md` i `context/foundation/design-system.md` („Sidebar”) opisują to zachowanie.

### Key Discoveries:

- `SelectedBudgetYear::resolve` potrzebuje listy intów (`app/Support/SelectedBudgetYear.php:19-35`). Nowa metoda z fazą stoi obok, a stara zostaje.
- Fabryka tworzy szkic, więc obecne asercje `->where('shell.budgetYears', [2025, 2026])` (`tests/Feature/BudgetTest.php:375`) zmieniają się na listę par z fazą.
- `DropdownMenuRadioItem` ma slot `indicator-icon` (`resources/js/components/ui/dropdown-menu/DropdownMenuRadioItem.vue:28`). Ptaszek `Check` podajemy przez slot, bez edycji `components/ui`.
- Paleta czyta `page.props.shell` w chwili wywołania (`useCommandPalette.ts:124-135`). Po zmianie typu wystarczy, że `budgetYearMenu` przyjmie nowe wpisy.
- `BudgetYearSwitcher.vue`, `LandingAppPreview.vue` i `SidebarKitchenSink.vue` są na liście `scripts/check-ui-literals.mjs` (`:22,42,49`). Nowe wartości idą więc tylko przez klasy w `app.css`.

## What We're NOT Doing

- Paleta ⌘K: pozycja „Dodaj rok budżetu” nie dostaje podpisu ani stanu roku.
- Bez nowych faz budżetu (np. zamknięcia roku, którego nie ma na `main`) i bez zmian w zatwierdzaniu budżetu.
- Bez nowych tokenów. Wszystkie wartości (`warning`, `warning-soft`, `--sidebar-*`, `shadow-dialog`, `--font-mono`) już są w `app.css`.
- Bez zmian w `components/ui/*` i w `lang/pl/ui.php` poza nowymi kluczami `shell.*`.
- Bez zmian w `SelectedBudgetYear::resolve` (domyślny rok wg ADR-0075) i w `createBudgetSummary` (dalej przyjmuje lata jako liczby).
- Rok bieżący z zegara serwera. Zostajemy przy `new Date().getFullYear()` w przeglądarce, jak dziś `createYear`.
- Dialogi kitchen sinka (`/dev/ui/dialogs`): prop `budgetYears` w `CreateBudgetDialog` zostaje `number[]`.

## Implementation Approach

Najpierw decyzja (ADR i reguły), potem dane bez zmiany wyglądu, na końcu widok. Faza 2 zmienia typ współdzielonego propsa w jednym commicie po obu stronach, z testem `assertInertia` (AGENTS.md, `.ai/rules/inertia-props.md`). Aplikacja wygląda wtedy tak samo, więc regresję widać osobno. Faza 3 przenosi całą logikę (grupy, stany, rok źródłowy) do czystej funkcji `budgetYearMenu` z testami Vitest. Komponent tylko ją rysuje, a stany w kitchen sinku idą z tej samej funkcji.

## Critical Implementation Details

- **Nazwy dostępne a opisy.** Pozycja `menuitemradio` dostaje nazwę z treści. Pigułki i notka w wierszu roku są więc w elemencie z `aria-hidden="true"` i z `id`, do którego odwołuje się `aria-describedby` pozycji. Opis czyta tekst ukrytego elementu, a nazwa zostaje „2027”. Na przycisku nazwa i tak pochodzi z `aria-labelledby` (etykieta i rok), więc pigułki opisuje `aria-describedby` na ich wspólnym opakowaniu. Przy „Dodaj rok N” `aria-label` zostaje, a podpis `small` ma `id` w `aria-describedby`.
- **Jedna grupa radiowa.** Oba nagłówki (`DropdownMenuLabel`) stoją wewnątrz jednego `DropdownMenuRadioGroup`. Wtedy jeden `model-value` obejmuje lata z obu grup, a klawiatura przechodzi przez nie bez przerwy.

## Phase 1: Decyzja i dokumentacja

### Overview

Zapisanie reguły przed kodem: nowy ADR rozwijający ADR-0087 oraz aktualizacja reguły sidebara i strony design systemu.

### Changes Required:

#### 1. ADR-0097

**File**: `context/foundation/adr/0097-budget-year-state-in-the-menu.md`

**Intent**: Zapisać zachowanie wariantu B karty `Sidebar` i trzy decyzje z planowania, tak żeby ADR-0087 dalej obowiązywał poza punktami, które ten ADR rozszerza.

**Contract**: ADR po angielsku, w formie ADR-0087 (reguła, stany, dane, konsekwencje, „We rejected”). Treść:
- **Stan roku:** „bieżący” to rok kalendarza, „szkic” to `BudgetPhase::Draft`. Oba są niezależne i pokazywane razem, a rok w realizacji, który nie jest bieżący, nie ma oznaki.
- **Przycisk:** rok w JetBrains Mono i pigułki przed chevronem.
- **Menu:**
  - od 4 lat grupy „Ostatnie” (3 najnowsze) i „Starsze”, przy ≤3 latach płaska lista bez nagłówków;
  - pigułka „szkic” i notka „bieżący”;
  - podpis „szkic z planem roku N−1” (ADR-0068), którego nie ma w pustym stanie;
  - ciemne menu przez klasę na `DropdownMenuContent`.
- **Dostępność:** nazwy jak w ADR-0087, stany i podpis przez `aria-describedby`.
- **Dane:** `shell.budgetYears` to `list<{year, phase}>`. Zastępuje to z ADR-0087 zdanie, że `shell.budgetYears` się nie zmienia.
- **Odrzucone:**
  - osobna lista `draftBudgetYears` (dwie listy do uzgadniania, każdy kolejny stan to nowa lista);
  - „szkic wygrywa” na przycisku albo wszędzie (karta traktuje stany jako niezależne, w styczniu znikałby rok bieżący);
  - stały nagłówek „Ostatnie” przy ≤3 latach (nagłówek, który niczego nie oddziela);
  - stan w nazwie dostępnej (zmienia kontrakt nazw ADR-0087 i helpera E2E).
- **Numer:** 0097. Jeśli F-07 scali się wcześniej z tym samym numerem, ten ADR bierze następny wolny (`context/foundation/agents/parallel-changes.md:27`).

#### 2. Reguła sidebara

**File**: `.ai/rules/js-sidebar.md`

**Intent**: Uzupełnić akapit o przełączniku roku („The year switcher is a menu (ADR-0087)…”) o stan roku, grupy, podpis, ciemne menu i opisy dostępne, z odwołaniem do ADR-0097.

**Contract**: Sekcja „## The active item comes from isCurrentSection”, zdania o menu roku i o stanach kitchen sinka (dopisać nowe stany z Desired End State). Bez nowej sekcji. Plik indeksu się nie zmienia, więc `boost:index-rules` nie jest potrzebny, chyba że zmienią się `paths:`.

#### 3. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: W sekcji „Sidebar” opisać pigułki stanu roku, ciemne menu roku na tokenach `--sidebar-*` i podpis „Dodaj rok”, a w zdaniu wstępu dopisać ADR-0097 obok ADR-0087.

**Contract**: Tabela ról tokenów w „## Sidebar” (`sidebar-accent`: rok wybrany w menu, `sidebar-border`: obrys pigułki „bieżący”) i nowy punkt listy o menu roku. Bez nowych tokenów.

### Success Criteria:

#### Automated Verification:

- ADR istnieje i nie dubluje numeru: `ls context/foundation/adr | grep -c '^0097-'` zwraca `1`
- Reguła i strona design systemu odwołują się do ADR-0097: `grep -l "ADR-0097" .ai/rules/js-sidebar.md context/foundation/design-system.md` zwraca oba pliki

#### Manual Verification:

- Treść ADR-0097 zgadza się z trzema decyzjami z planowania i z kartą `Sidebar` (wariant B)

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Lata z fazą w `shell`

### Overview

Zmiana kształtu współdzielonego propsa po obu stronach w jednym commicie, z testami. Wygląd się nie zmienia.

### Changes Required:

#### 1. Odczyt lat z fazą

**File**: `app/Support/SelectedBudgetYear.php`

**Intent**: Dodać metodę zwracającą lata budżetów użytkownika z ich fazą, rosnąco po roku, jednym zapytaniem. Obecna `budgetYearsForUser` zostaje dla `resolve`.

**Contract**: `public static function budgetYearEntriesForUser(User $user): array`, PHPDoc `@return list<array{year: int, phase: string}>`, `phase` jako `BudgetPhase->value`.

#### 2. Shell

**File**: `app/Support/ShellState.php`

**Intent**: `budgetYears` w odpowiedzi to wpisy z fazą. `firstRun.hasBudget` wylicza się z tych samych wpisów.

**Contract**: Kształt PHPDoc `budgetYears: list<array{year: int, phase: 'draft'|'execution'}>`. Klucz bez zmiany nazwy.

#### 3. Typy TS

**File**: `resources/js/types/shell.ts`

**Intent**: Typ wpisu roku i nowy typ `ShellState.budgetYears`.

**Contract**: `export type BudgetYearEntry = { year: number; phase: 'draft' | 'execution' }`, a `budgetYears: BudgetYearEntry[]`.

#### 4. Konsumenci

**Files**: `resources/js/lib/budget-year-menu.ts` (+ `.test.ts`), `resources/js/components/BudgetYearSwitcher.vue`, `resources/js/components/AppSidebar.vue`, `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/js/composables/useCommandPalette.ts`, `resources/js/components/CreateBudgetDialog.vue`, `resources/js/pages/Dashboard.vue`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `resources/js/composables/useTranslations.test.ts`

**Intent**: Każdy konsument przyjmuje `BudgetYearEntry[]` i zachowuje się jak dotąd. `budgetYearMenu` przyjmuje wpisy i na razie zwraca dotychczasowy kształt. `CreateBudgetDialog` podaje `createBudgetSummary` same lata. `Dashboard` sprawdza rok przez `some`. Ramki kitchen sinka dostają wpisy z fazą `execution`, żeby wyglądał jak dziś.

**Contract**: `budgetYearMenu(budgetYears: BudgetYearEntry[], selectedYear: number | null, currentYear: number)`. Props `budgetYears: BudgetYearEntry[]` w `AppSidebar` i `BudgetYearSwitcher`. `CreateBudgetDialog` zostawia swój prop `budgetYears?: number[]` (tylko kitchen sink dialogów).

#### 5. Testy Feature

**Files**: `tests/Feature/BudgetTest.php`, `tests/Feature/AppShellTest.php`

**Intent**: `test_shell_exposes_budget_years_and_selected_year` tworzy 2025 w realizacji i 2026 jako szkic, a potem sprawdza listę par w kolejności rosnącej. Asercja pustej listy w `AppShellTest` zostaje.

**Contract**: `->where('shell.budgetYears', [['year' => 2025, 'phase' => 'execution'], ['year' => 2026, 'phase' => 'draft']])`.

### Success Criteria:

#### Automated Verification:

- Testy shella przechodzą na PostgreSQL w worktree: `docker run … artisan test --compact tests/Feature/BudgetTest.php tests/Feature/AppShellTest.php tests/Feature/LoadBudgetSheetTest.php` (polecenie z AGENTS.md, baza `testing_budget_year_menu_state`)
- Testy JS przechodzą: `npm test`
- Typy przechodzą: `npm run types:check` i `composer types:check`
- Lint i formatowanie: `npm run check`, `vendor/bin/pint --dirty --format agent`, `vendor/bin/sloppy ci`

#### Manual Verification:

- `/dev/ui/sidebar` i Dashboard wyglądają tak samo jak przed zmianą, a przełączenie roku i „Dodaj rok” działają

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Menu ze stanem roku

### Overview

Logika grup, stanów i roku źródłowego w `budgetYearMenu`, widok w `BudgetYearSwitcher`, klasy w `app.css`, teksty, kitchen sink i landing.

### Changes Required:

#### 1. Logika menu

**File**: `resources/js/lib/budget-year-menu.ts`

**Intent**: Funkcja zwraca wszystko, co widok rysuje: dwie grupy lat (przy ≤3 latach wszystko w `recent`, a `older` jest puste), stan każdego roku, wpis wybranego roku dla przycisku, rok do utworzenia i jego rok źródłowy. Zostają dotychczasowe reguły: lata unikalne od najnowszego i `createYear` = najwyższy + 1 albo bieżący przy braku budżetu.

**Contract**:

```ts
export type BudgetYearMenuEntry = {
    year: number;
    selected: boolean;
    current: boolean; // year === currentYear
    draft: boolean;   // phase === 'draft'
};

export type BudgetYearMenu = {
    recent: BudgetYearMenuEntry[];         // 3 newest; all years when there are ≤ 3
    older: BudgetYearMenuEntry[];          // the rest, newest first; [] when there are ≤ 3
    selected: BudgetYearMenuEntry | null;  // the entry of selectedYear, null when it has no budget
    createYear: number;
    createSourceYear: number | null;       // createYear − 1 (the highest year); null with no budget
};
```

`useCommandPalette` czyta dalej tylko `createYear`. Puste menu to `recent.length === 0`.

#### 2. Testy logiki

**File**: `resources/js/lib/budget-year-menu.test.ts`

**Intent**: Pokryć nowe reguły na latach użytkownika i przenieść obecne przypadki na nowy kształt.

**Contract**: Przypadki:
- 2020–2027 (2027 szkic, bieżący 2026) → `recent` 2027, 2026, 2025, `older` 2024…2020; 2027 `draft`, 2026 `current`;
- dokładnie 3 lata → `older` puste;
- 4 lata → jeden rok w `older`;
- rok bieżący będący szkicem → `current` i `draft` jednocześnie;
- rok w realizacji, który nie jest bieżący → oba `false`;
- `createSourceYear` = najwyższy rok, a przy braku budżetu `null`;
- wybrany rok bez budżetu → `selected: null`;
- duplikaty i nieposortowane wejście.

#### 3. Teksty

**Files**: `lang/pl/ui.php`, `resources/js/types/shell.ts` (`UiTranslations.shell`), `tests/Feature/AppShellTest.php`

**Intent**: Nowe teksty sekcji `shell` i ich asercje obok istniejących `translations.shell.*`.

**Contract**: Klucze `year_current` → „bieżący”, `year_draft` → „szkic”, `years_recent` → „Ostatnie”, `years_older` → „Starsze”, `add_year_source` → „szkic z planem roku :year”.

#### 4. Klasy

**File**: `resources/css/app.css`

**Intent**: Klasy w `@layer components` obok `.sidebar-year-trigger`, przepisane z `ht-ystate` / `ht-ymenu` karty na tokeny aplikacji:
- pigułka stanu i jej dwa warianty: „bieżący” z obrysem `sidebar-border` i tekstem `sidebar-foreground`, „szkic” z `warning` na `warning-soft`;
- ciemna treść menu: tło `sidebar`, tekst `sidebar-strong`, ramka `sidebar-border`, `shadow-dialog`, `rounded-lg`;
- pozycja menu: podświetlenie i rok wybrany na `sidebar-accent`, fokus `sidebar-ring`;
- nagłówek grupy (`label-caps` w `sidebar-foreground`), notka „bieżący” i podpis „Dodaj rok” (`sidebar-foreground`), separator `sidebar-border`.

**Contract**: Nazwy z prefiksem `.sidebar-year-` (np. `.sidebar-year-state`, `.sidebar-year-state-current`, `.sidebar-year-state-draft`, `.sidebar-year-menu`, `.sidebar-year-menu-item`, `.sidebar-year-menu-group`, `.sidebar-year-menu-note`). Utility z `!` tam, gdzie trzeba przebić klasy `components/ui/dropdown-menu`, tak jak w `.command-palette`. Wysokość pigułki i rozmiar 11 px bez dowolnych wartości w szablonie. Jeśli żadna nazwana klasa ich nie daje, dopisać wartość w regule CSS, jak w `.kbd` (`text-[11px]/4`). Bez nowych tokenów, bez `dark:`.

#### 5. Przełącznik roku

**File**: `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Narysować wariant B z `budgetYearMenu`:
- **przycisk:** rok w `font-mono`, pigułki z `selected` przed chevronem;
- **menu:**
  - klasa ciemnego menu na `DropdownMenuContent`;
  - przy niepustym `older` nagłówki `DropdownMenuLabel` „Ostatnie” i „Starsze” wewnątrz jednego `DropdownMenuRadioGroup`;
  - ptaszek `Check` w slocie `indicator-icon`;
  - pigułka „szkic” i notka „bieżący” w wierszu;
  - „Dodaj rok N” z podpisem, gdy `createSourceYear !== null`.
- Pusty stan i oczekiwanie bez zmian.
- Prop `currentYear?: number` (tylko kitchen sink) zastępuje `new Date().getFullYear()`, gdy jest podany.

**Contract**: Nazwy dostępne i `aria-describedby` jak w „Critical Implementation Details”. Bez `aria-pressed`, bez edycji `components/ui`. `aria-busy` i `aria-disabled` jak dziś.

#### 6. Kitchen sink

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: Ramki `yearFrames` z fazą i stałym `currentYear: 2026`:
- jeden rok (2026, realizacja);
- wybrany szkic (2025–2027, wybrany 2027 jako szkic);
- rok bieżący będący szkicem (tylko 2026 jako szkic);
- ≤3 lata z otwartym menu (2024–2026);
- wiele lat z otwartym menu (2020–2027, 2027 szkic);
- bez budżetu;
- oczekiwanie.

**Contract**: Typ `YearFrame` z `budgetYears: BudgetYearEntry[]`. Każda ramka przekazuje `current-year`.

#### 7. Landing

**File**: `resources/js/components/LandingAppPreview.vue`

**Intent**: Statyczna kopia przycisku roku 2026 dostaje pigułkę „bieżący” i rok w `font-mono`, tak jak przycisk w stanie zamkniętym karty. Dalej bez elementów fokusowalnych.

**Contract**: `span` z klasami pigułki „bieżący” i tekstem `t.shell.year_current` w `div.sidebar-year-trigger`.

### Success Criteria:

#### Automated Verification:

- Testy logiki menu przechodzą: `npm test -- resources/js/lib/budget-year-menu.test.ts`
- Pełna bramka przechodzi w worktree: `docker run … --entrypoint composer sail-8.5/app ci:check` (lint, `check:ui`, typy, Vitest, PHPUnit)
- Sloppy bez nowych znalezisk: `vendor/bin/sloppy ci`
- E2E roku budżetu przechodzą bez zmian helpera: `npx playwright test tests/e2e/sidebar-year-refresh.spec.ts tests/e2e/command-palette.spec.ts`

#### Manual Verification:

- `/dev/ui/sidebar` w obu motywach pokazuje wszystkie stany z ramek zgodnie z kartą `Sidebar` (wariant B): pigułki, grupy, ptaszek, podpis, ciemne menu
- Czytnik ekranu albo drzewo dostępności (DevTools) podaje dla przycisku nazwę „Rok budżetu 2026” z opisem „bieżący”, a dla roku 2027 nazwę „2027” z opisem „szkic”
- Na telefonie (≤768 px) menu roku w arkuszu sidebara wygląda i działa tak samo
- Strona powitalna `/dev/ui/welcome` pokazuje przycisk 2026 z pigułką „bieżący”

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `budget-year-menu.test.ts`: granica 3/4 lat, stany `current` / `draft` osobno i razem, `createSourceYear`, `selected: null`, duplikaty.

### Integration Tests:

- `BudgetTest::test_shell_exposes_budget_years_and_selected_year`: pary `{year, phase}` rosnąco, dla szkicu i roku w realizacji.
- `AppShellTest`: pusta lista i nowe klucze `translations.shell.*`.
- E2E `sidebar-year-refresh.spec.ts`: przełączenie i utworzenie roku dalej odświeżają menu (helper bez zmian dowodzi stałych nazw dostępnych).

### Manual Testing Steps:

1. `/dev/ui/sidebar`, motyw jasny i ciemny: przejrzeć każdą ramkę roku.
2. W aplikacji z latami 2020–2027 otworzyć menu i sprawdzić grupy, „szkic” przy 2027, „bieżący” przy 2026 i „Dodaj rok 2028 / szkic z planem roku 2027”.
3. Wybrać 2027 i sprawdzić, że przycisk pokazuje „2027 [szkic]” bez „bieżący”.

## Performance Considerations

Odczyt lat z fazą to jedno zapytanie `year, phase` po budżetach użytkownika, na liście kilkunastu lat. `resolve` dalej robi swoje zapytanie o lata, tak jak dziś.

## Migration Notes

Bez migracji. `phase` już jest w tabeli `budgets`.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-38, MS-05)
- Karta `Sidebar`: artefakt Atrament https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, `project/components/Sidebar/README.md`, `preview.html`, `bundle.css:441-458`
- ADR-0087 (menu roku), ADR-0068 (źródło planu nowego budżetu), ADR-0075 (domyślny rok), ADR-0057 (landing)
- Wzór ciemnej warstwy: `resources/css/app.css:650-700` (`.command-palette*`)
- Helper E2E: `tests/e2e/support/budget-year.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzja i dokumentacja

#### Automated

- [x] 1.1 ADR istnieje i nie dubluje numeru — 75062c9
- [x] 1.2 Reguła i strona design systemu odwołują się do ADR-0097 — 75062c9

#### Manual

- [ ] 1.3 Treść ADR-0097 zgadza się z trzema decyzjami z planowania i z kartą `Sidebar` (wariant B)

### Phase 2: Lata z fazą w `shell`

#### Automated

- [x] 2.1 Testy shella przechodzą na PostgreSQL w worktree — 7921a61
- [x] 2.2 Testy JS przechodzą — 7921a61
- [x] 2.3 Typy przechodzą — 7921a61
- [x] 2.4 Lint i formatowanie — 7921a61

#### Manual

- [ ] 2.5 `/dev/ui/sidebar` i Dashboard wyglądają tak samo jak przed zmianą, a przełączenie roku i „Dodaj rok” działają

### Phase 3: Menu ze stanem roku

#### Automated

- [x] 3.1 Testy logiki menu przechodzą
- [x] 3.2 Pełna bramka przechodzi w worktree
- [x] 3.3 Sloppy bez nowych znalezisk
- [x] 3.4 E2E roku budżetu przechodzą bez zmian helpera

#### Manual

- [ ] 3.5 `/dev/ui/sidebar` w obu motywach pokazuje wszystkie stany z ramek zgodnie z kartą `Sidebar` (wariant B)
- [ ] 3.6 Czytnik ekranu albo drzewo dostępności podaje nazwy bez stanu i stan jako opis
- [ ] 3.7 Na telefonie menu roku w arkuszu sidebara wygląda i działa tak samo
- [ ] 3.8 Strona powitalna pokazuje przycisk 2026 z pigułką „bieżący”
