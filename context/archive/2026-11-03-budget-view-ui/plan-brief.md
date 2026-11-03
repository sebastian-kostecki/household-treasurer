# budget-view-ui — Plan Brief

> Full plan: `context/changes/budget-view-ui/plan.md`
> Research: `context/changes/budget-view-ui/research.md`

## What & Why

Widok `/budget` działa, ale został zbudowany funkcja po funkcji. Ma martwy przełącznik „Pokaż zarchiwizowane”, dwa równoległe drzewa desktop/mobile z sześcioma kopiami komórek kwot, które już się rozjechały, pusty stan inny niż każdy inny w aplikacji (bez `<h1>`, z nieprawdziwym tekstem) i brakujące stany (błąd, oczekiwanie, brak wyników, nazwy kontrolek). Zmiana prowadzi go przez kontrakt `/10x-ui`: najpierw kontrakt, potem piksele, a na końcu bramka i reguła.

## Starting Point

Tokeny są czyste: skan literałów daje 0 trafień, więc dryf jest strukturalny, a nie kolorystyczny. `BudgetCategoryList.vue` (1015 linii) renderuje `<table>` na desktopie i osobne karty na telefonie, oba drzewa zawsze w DOM. Repo ma już wzorzec jednego markupu: `.operations-grid` + `FinancialOperationRow`.

## Desired End State

Przełącznik archiwum działa i odzwierciedla URL. Lista kategorii to jeden markup na siatce w jednym `ListSurface`: na telefonie wiersz w 2–3 liniach, na desktopie kolumny, z tą samą kolejnością kwot na każdej szerokości. Zarchiwizowane wiersze są w całości wyciszone. Nowy użytkownik widzi wyśrodkowany pusty stan z nagłówkiem strony. Każdy z 7 stanów jest widoczny w lokalnym kitchen sinku, a test w CI nie pozwala wrócić literałom ani martwemu `:checked`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Kształt listy (C2+C5) | Jeden markup: `.budget-category-grid` w `ListSurface`, wiersz z komórek `BudgetAmountCell` | Usuwa oba drzewa i 6 kopii komórek; to wzorzec Operations, jedynej listy z jednym markupem | Plan |
| Reguła archiwum | Cały wiersz `text-muted-foreground`, bez `success`/`destructive`/koloru %, bez `opacity` | Zgodne z `design-system.md` („muted-foreground = zarchiwizowane”) i z kontrastem 4,5:1 | Plan |
| Zakres C1 | Naprawa tylko `/budget`, jedno issue na GitHubie dla Accounts/TermDeposits/OperationsImport | Zachowuje kontrakt „jeden widok”, a błąd (w tym możliwy zły `has_header` w imporcie) jest śledzony | Plan |
| Bramka wizualna | Strona Inertii `/dev/kitchen-sink/budget`, tylko `app()->isLocal()`, typowane fiksury TS | Wszystkie stany naraz, wielokrotnego użytku; specyfikacje odrzucają asercje zrzutów | Research (kitchen sink) / Plan (miejsce) |
| Kontrola w CI | `resources/js/ui-contract.test.ts` (`vp test`): 0 literałów w plikach Budżetu + zakaz `:checked` w całym repo z kurczącą się listą wyjątków | Bez nowej zależności; poprawka `9abd259` bez strażnika nie przetrwała | Plan |
| Kolory komórek | Czyste funkcje w `lib/budget-category-cells.ts` z testem | Jedno źródło zamiast 4 powtórzeń warunku i lokalnych helperów | Plan |
| Tekst pustego stanu | `budget.no_budget` → „Nie masz jeszcze budżetu” | Gałąź pojawia się tylko bez żadnego budżetu; klucz używany wyłącznie w `Budget.vue` | Research / Plan |
| Błąd zmiany kolejności | Rollback + `toast.error` (vue-sonner już podłączony), przeciąganie zablokowane w trakcie PATCH | Użytkownik dostaje wyjaśnienie bez zmian w backendzie | Plan |
| Uchwyt przeciągania | Tylko dla wskaźnika (`tabindex="-1"`), klawiatura odroczona | Kontrolka w kolejności Tab, która nic nie robi, jest gorsza niż jej brak | Research / Plan |
| Sumy przy filtrze | Bez zmian (cały typ) | Filtr wyszukuje, nie zawęża budżetu | Plan |

## Scope

**In scope:**
- C1: `v-model` na przełączniku archiwum w `Budget.vue`
- C2+C5: jeden markup listy, nowe `BudgetCategoryRow`, `BudgetAmountCell`, `BudgetCategoryHeaderRow`; usunięcie drzewa mobilnego i martwych `.list-surface-table*`
- C3: `BudgetEmptyState` z `IndexEmptyState`, `<h1>`, nowy tekst
- C4: nazwa filtra, wyzwalacz archiwum (`<span>`, hover), brak wyników filtra, toast błędu kolejności, natychmiastowa reakcja wyboru miesiąca (też MonthPlan), stała wysokość „Do wydania”, `aria-hidden` ikony
- Kitchen sink, test-strażnik, reguła w `.ai/rules/js.md`, wpis w `design-system.md`, issue na GitHubie

**Out of scope:**
- Naprawa checkboxów w Accounts/TermDeposits/OperationsImport (issue)
- Przestawianie z klawiatury, tooltip menu akcji (wymaga zmiany `IconAction`), podpowiedzi w etykietach mobilnych
- Ujednolicenie kickerów, nagłówka strony (`Heading.vue`), wartości arbitralnych w kontrakcie list
- `?month[]=1` (backend), Playwright, nowe zależności

## Architecture / Approach

Kolejność `/10x-ui`: środowisko → kontrakt → widok → stany → zabezpieczenie. Kitchen sink powstaje pierwszy na obecnych komponentach i daje zrzuty „przed”. Potem do `app.css` trafia `.budget-category-grid` (obszary `handle category initial current execution remaining actions`, modyfikatory `-draft` i `-no-handle`, sticky nagłówek i suma od `md`), a reguły kolorów lądują w `lib/budget-category-cells.ts`. Na tym kontrakcie powstaje jeden wiersz używany dla aktywnych, zarchiwizowanych i sum, więc stany z fazy 5 dokładamy w jednym miejscu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Środowisko | Kitchen sink tylko lokalnie + test 404 + zrzuty „przed” | Trasa nazwana lub import Wayfindera złamie `types:check` w CI |
| 2. Kontrakt | `.budget-category-grid` w `app.css`, `budget-category-cells.ts` z testem, `design-system.md` | Szerokości kolumn różne od dzisiejszej tabeli |
| 3. Widok — strona | Działający przełącznik archiwum, pusty stan z `<h1>` | Lokalny `ref` rozjedzie się z propem przy `preserveState` |
| 4. Widok — jedna lista | Jeden markup, drzewo mobilne i `.list-surface-table*` usunięte | Utrata sticky nagłówka/sumy lub przeciągania dotykiem |
| 5. Stany | Brak wyników, toast błędu, oczekiwanie miesiąca, macierz 7 stanów | Zmiana `BudgetMonthPicker` dotyka MonthPlan |
| 6. Zabezpieczenie | Strażnik w CI, issue, reguła w `.ai/rules/js.md` | Lista wyjątków nieaktualizowana po naprawie issue (test to wymusza) |

**Prerequisites:** worktree po krokach „Fresh checkout” z `AGENTS.md`; działający Sail (PostgreSQL) dla obrazu `sail-8.5/app`; podgląd z osobną bazą i `APP_ENV=local`; `gh` zalogowany (faza 6).
**Estimated effort:** ~3–4 sesje: fazy 1–3 razem w jednej, faza 4 osobno (największy diff), fazy 5–6 w jednej.

## Open Risks & Assumptions

- Zakładamy, że SortableJS w `draggable` z `tag="div"` działa z dotykiem tak samo jak dziś na kartach. Weryfikacja ręczna w fazie 4.
- Stany chwilowe (toast, oczekiwanie miesiąca) nie są statycznie w kitchen sinku. Sprawdzamy je ręcznie z DevTools.
- Strażnik jest tekstowy: łapie literały i nazwy atrybutów, ale nie zachowanie. Wiązanie checkboxa potwierdza ręczna kontrola 3.5.

## Success Criteria (Summary)

- Klik „Pokaż zarchiwizowane” zmienia listę, a link z `?show_archived=1` daje zaznaczone pole, które da się odznaczyć.
- Ta sama kategoria czyta się tak samo na telefonie, na desktopie i w archiwum, w jednym `ListSurface`.
- Kitchen sink pokazuje komplet 7 stanów w obu motywach, a `npm test` oblewa każdy nowy literał w Budżecie i każde nowe `:checked` na `Checkbox`.
