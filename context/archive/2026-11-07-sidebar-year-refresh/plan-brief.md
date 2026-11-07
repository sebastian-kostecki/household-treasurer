# Sidebar pokazuje aktualny rok budżetu po każdej wizycie — Plan Brief

> Full plan: `context/changes/sidebar-year-refresh/plan.md`
> Research: `context/changes/sidebar-year-refresh/research.md`

## What & Why

Po przełączeniu albo utworzeniu roku budżetu strona pokazuje nowy rok, a sidebar nadal zaznacza stary, dopóki użytkownik nie zrobi F5. Kliknięcie roku, który sidebar błędnie uważa za bieżący, nic nie robi, więc nie ma jak do niego wrócić. Plan naprawia przyczynę w jednym helperze i zostawia po sobie testy, które tę wadę łapią.

## Starting Point

`useShell()` czyta `page.props.shell` jeden raz w `setup`. Inertia przy każdej wizycie podstawia nowy obiekt strony, a trwały `AppSidebarLayout` nie jest montowany ponownie, więc `AppSidebar` i `CreateBudgetDialog` z layoutu trzymają `shell` z pierwszego załadowania. Serwer zawsze wysyła świeży `shell`, więc po stronie PHP nic nie jest nieaktualne. Błąd odtworzono w przeglądarce przy trzech przejściach.

## Desired End State

Po każdej wizycie sidebar zaznacza rok z bieżącego `shell`, pokazuje aktualne segmenty (także nowo utworzony rok), a dialog „Nowy budżet” z layoutu proponuje rok i źródła kopiowania z bieżącego stanu. Użytkownik nie musi odświeżać strony.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Przyczyna | Migawka `shell` w trwałym layoucie | Potwierdzona w przeglądarce, mechanizm w kodzie Inertia | Research |
| Wariant poprawki | `useShell(): ComputedRef<ShellState>` | `vue-tsc` wskaże każde pominięte `.value`, zgodne z wzorcem z dokumentacji Inertia | Plan |
| `useTranslations` | Bez zmian, uwaga w regule sidebara | `translations` zależy tylko od `app.locale`, brak szkody dziś, a poprawka to 88 plików | Plan |
| Warstwa testu | E2E Playwright + test jednostkowy `useShell` | E2E łapie trwały layout i prawdziwą wizytę, test jednostkowy daje szybką diagnozę | Plan |
| Testy komponentów Vue | Nie dodajemy zależności | Wymaga zgody i nie jest potrzebne przy powyższym duecie | Plan |
| Lista źródeł w `CreateBudgetDialog` | Bez własnej asercji E2E | Pokrywa ją test reaktywności `shell`, a w przeglądarce sprawdzamy sidebar | Plan |
| Kolejność | Test czerwony przed poprawką | Reguła lekcji: błąd najpierw staje się nieprzechodzącym testem | Plan |
| Reguła | Nowa sekcja w `.ai/rules/js-sidebar.md` | Klasa błędu (migawka w trwałym layoucie) ma nie wrócić | Research / Plan |

## Scope

**In scope:** `useShell` jako `ComputedRef`, `.value` w `CreateBudgetDialog`, `Dashboard` i `useFirstRunChecklist`, test jednostkowy, spec E2E (utworzenie roku, wybór roku, wybór z `/accounts`, sprzątanie), reguła w `js-sidebar.md`.

**Out of scope:** zmiany `useTranslations`, zależności do testów komponentów, zmiany PHP i `ShellState`, zmiany wyglądu sidebara, `preserveState` na Dashboardzie, osobna asercja E2E na źródła kopiowania.

## Architecture / Approach

`useShell()` zwraca `computed(() => page.props.shell as ShellState)`. Szablony `<script setup>` rozpakowują ref, więc `AppSidebarLayout` i `Dashboard` zmieniają się tylko tam, gdzie skrypt czyta `shell` (`CreateBudgetDialog`, `Dashboard.vue:93`, `useFirstRunChecklist`). `BudgetYearSwitcher` ma `segments` jako `computed` nad propsami, więc po przekazaniu świeżych wartości działa bez zmian.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Test czerwony, potem poprawka `useShell` | Test jednostkowy (czerwony, potem zielony) i `ComputedRef` w 4 miejscach | Mock `usePage` musi podmieniać cały obiekt strony jak Inertia, inaczej test nie padnie przed poprawką |
| 2. E2E i reguła | Spec Playwright z trzema przejściami i sprzątaniem, sekcja w `js-sidebar.md` | E2E wymaga stosu Sail i buildu; spec musi sprzątać drugi rok także przy błędzie |

**Prerequisites:** działający stos Sail, brak `public/hot`, użytkownik i baza `household_treasurer_e2e` (`context/foundation/test-stack.md`).
**Estimated effort:** ~1–2 sesje, 2 fazy, kilkanaście linii kodu produkcyjnego i dwa nowe pliki testów.

## Open Risks & Assumptions

- Zakładamy, że poza `AppSidebarLayout` i `CreateBudgetDialog` żaden komponent trwale zamontowany w layoucie nie czyta `shell`; research przeszukał `resources/js` (`*.vue`, `*.ts`), ale nie PHP ani Blade.
- Skutek dla `CreateBudgetDialog` (rok domyślny, źródła kopiowania) wynika z kodu, nie z obserwacji w przeglądarce.
- `watch(defaultYear, …)` w `CreateBudgetDialog` resetuje wpisany rok, gdy `shell` zmieni się przy otwartym dialogu; dziś dialog zamyka się po sukcesie, więc nie widać tego w praktyce.

## Success Criteria (Summary)

- Po przełączeniu lub utworzeniu roku sidebar od razu zaznacza właściwy rok i pokazuje nowy segment, bez F5.
- Test jednostkowy `useShell` i spec E2E przechodzą, a oba padają na kodzie sprzed poprawki.
- `npm run check`, `npm run types:check` i `npm test` są zielone, a baza E2E po specu wraca do jednego roku.
