# Stan roku w menu „Rok budżetu” (S-38) — Plan Brief

> Full plan: `context/changes/budget-year-menu-state/plan.md`

## What & Why

Menu „Rok budżetu” w sidebarze nie mówi dziś, który rok jest bieżący, a który jest jeszcze szkicem. Lista lat 2020–2027 jest płaska, a „Dodaj rok N” nie mówi, skąd przyjdzie plan. Slice S-38 (MS-05) wdraża wariant B karty `Sidebar` z design systemu Atrament, który wybrałeś 2026-11-14.

## Starting Point

`BudgetYearSwitcher.vue` rysuje menu z ADR-0087: rok, chevron, płaską grupę radiową lat i „Dodaj rok N”, w domyślnych kolorach popovera. `shell.budgetYears` to sama lista lat (`list<int>`), bez fazy. Czyta ją sidebar, paleta ⌘K, dialog „Nowy budżet” i Dashboard.

## Desired End State

Przycisk pokazuje rok w JetBrains Mono z pigułkami „bieżący” i/lub „szkic”. Ciemne menu (jak paleta ⌘K) dzieli lata na „Ostatnie” i „Starsze” (od 4 lat), oznacza szkic i rok bieżący, a pod kreską ma „Dodaj rok 2028 / szkic z planem roku 2027”. Nazwy dostępne się nie zmieniają, a każdy stan jest w kitchen sinku `/dev/ui/sidebar` w obu motywach.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Kształt danych | `shell.budgetYears` = `list<{year, phase}>` | Jedno źródło roku i fazy, jak opisują roadmapa i karta; nowy stan roku to nowa wartość `phase`, nie nowa lista. |
| Rok bieżący, który jest szkicem | Obie oznaki na przycisku i w menu | Karta traktuje „bieżący” (kalendarz) i „szkic” (faza) jako niezależne fakty; w styczniu i przy pierwszym roku żaden się nie gubi. |
| Grupy przy ≤3 latach | Płaska lista bez nagłówków; od 4 lat „Ostatnie” (3 najnowsze) i „Starsze” | Nagłówek pojawia się tylko wtedy, gdy coś oddziela. |
| Dostępność | Nazwy jak w ADR-0087, stan i podpis w `aria-describedby` | Kontrakt nazw i helper E2E zostają bez zmian. |
| Ciemne menu | Klasy `.sidebar-year-menu*` w `app.css`, wzór `.command-palette` | Bez edycji `components/ui` i bez nowych tokenów. |
| Rok bieżący | Zegar przeglądarki; prop `currentYear` tylko w kitchen sinku | Tak jak dziś liczy się `createYear`; stany kitchen sinka nie rozjadą się w 2027. |
| Zapis decyzji | ADR-0098 rozwijający ADR-0087 | Zmienia zdanie ADR-0087, że `shell.budgetYears` się nie zmienia. |

## Scope

**In scope:**
- ADR-0098, `js-sidebar.md`, `design-system.md`
- `shell.budgetYears` z fazą (PHP, TS, wszyscy konsumenci, testy `assertInertia`)
- `budgetYearMenu` (grupy, stany, rok źródłowy) z testami, `BudgetYearSwitcher`, klasy CSS, teksty `shell.*`
- Ramki kitchen sinka i pigułka „bieżący” na landingu

**Out of scope:**
- Podpis i stan roku w palecie ⌘K
- Nowe fazy budżetu i zmiany zatwierdzania
- Nowe tokeny, edycje `components/ui`, rok bieżący z serwera

## Architecture / Approach

Serwer (`SelectedBudgetYear::budgetYearEntriesForUser`, jedno zapytanie) daje wpisy `{year, phase}` w `shell`. Czysta funkcja `budgetYearMenu(wpisy, wybrany rok, bieżący rok)` liczy grupy, stany, wpis wybranego roku i rok źródłowy. `BudgetYearSwitcher` tylko to rysuje na klasach `.sidebar-year-*`. Paleta czyta z tej samej funkcji tylko `createYear`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzja i dokumentacja | ADR-0098, reguła sidebara, design system | Kolizja numeru ADR z równoległym F-07 |
| 2. Lata z fazą w `shell` | Nowy kształt propsa po obu stronach, bez zmiany wyglądu | Przeoczony konsument (np. `.includes` na obiektach); łapią to typy i testy |
| 3. Menu ze stanem roku | Wariant B w aplikacji, kitchen sink, landing | Przebicie klas `components/ui/dropdown-menu` w ciemnym menu bez edycji `ui` |

**Prerequisites:** worktree zrebase'owany na `development` (zrobione), testowa baza `testing_budget_year_menu_state` dla PostgreSQL.
**Estimated effort:** ~1–2 sesje w 3 fazach.

## Open Risks & Assumptions

- Jeśli F-07 scali się pierwszy z ADR-0098, ten ADR bierze następny numer (procedura w `parallel-changes.md`).
- „Bieżący” z zegara przeglądarki może się różnić od roku serwera (UTC) przez godzinę w Nowy Rok. Przyjmujemy to, jak dziś przy `createYear`.

## Success Criteria (Summary)

- Przy latach 2020–2027 widać od razu, że 2026 jest bieżący, a 2027 to szkic, a „Dodaj rok 2028” mówi, że plan przyjdzie z 2027.
- Pełna bramka `ci:check`, Sloppy i E2E roku budżetu przechodzą bez zmian helpera E2E.
