# Dashboard UI contract pass — Plan Brief

> Full plan: `context/changes/dashboard-ui-contract/plan.md`
> Research: `context/changes/dashboard-ui-contract/research.md`

## What & Why

Dashboard, czyli ekran po zalogowaniu, przechodzi przez kontrakt 10x-ui. Tokeny kolorów są w porządku, ale widok był składany funkcja po funkcji. Kafelki KPI są kopiowane, kolor kwot ma sześć różnych reguł (0,00 zł bywa zielone albo szare), pigułki są budowane ręcznie, a fokus odbiega od reszty. Na telefonie kwota w wierszu kategorii nie ma podpisu, a puste stany nie prowadzą dalej. Celem jest widok zbudowany z tokenów i komponentów repo oraz zabezpieczenie, które utrzyma kolejnego agenta przy kontrakcie.

## Starting Point

Tailwind v4 i shadcn-vue: wartości w `resources/css/app.css`, komponenty w `components/ui/*` i domenowe w `components/`, zasady w `.docs/design-system.md` i `.ai/rules/js.md`. Research zapisał 5 zarzutów z plikiem i linią (C1–C5). W repo nie ma kitchen sinka ani testów zrzutów; jest `npm run check` (lint) w `composer ci:check`.

## Desired End State

Kafelki KPI to jeden `StatTile`, a ton kwoty pochodzi z jednego helpera (zero nigdy nie jest zielone). Pigułki wagi to `Badge`, a fokus kart powiadomień wygląda jak fokus przycisków. Na telefonie listy kategorii mają nagłówek „Kategoria · Pozostało”, a puste stany mówią, co dalej. Nieudany wybór roku pokazuje toast. `/dev/ui/dashboard` pokazuje wszystkie scenariusze, a `npm run check:ui` w CI nie przepuści literału w oczyszczonych plikach.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wariant kontraktu | Rozszerzamy istniejący system, bez drugiej palety i bez `shadcn init` | Tokeny i komponenty już są; dryf jest w kompozycji. | Research |
| Kolor kwoty ze znakiem (C1) | > 0 success, 0 bez koloru, < 0 destructive, przez `amountToneClass` | Tak działają już 4 z 6 helperów, to precedens Planu miesiąca i `design-system.md:28`. | Plan |
| Kafelek KPI (C1) | Nowy komponent domenowy `StatTile` | Usuwa trzy kopie; układ siatki bez zmian. | Research / Plan |
| Pigułki wagi (C4) | Nowe warianty `Badge`: `warning`, `destructive-soft` | Ten sam precedens co `Alert variant="warning"`; jedno źródło dla całej aplikacji. | Plan |
| Fokus kart (C4) | `ring-3` + `ring/50`, jak w `buttonVariants` | Kontrakt `design-system.md:15`; bez wartości arbitralnej, którą złapałby skan. | Research / Plan |
| Podpisy na telefonie (C3) | Wiersz nagłówka „Kategoria · Pozostało” + `sr-only` w wierszu; martwe podpisy usunięte | Wiersz zostaje dwulinijkowy (historyjka 23), a czytnik ekranu dostaje nazwę. | Plan |
| Typografia podpisów (C3) | `@utility column-caption` (11 px wersalikami) | Jeden zapis zamiast czterech wariantów i literału 11 px. | Research / Plan |
| Puste stany (C2) | `IndexEmptyState` z akcją; podpowiedź w szkicu | Nowy użytkownik na telefonie ma drogę do kont; szkic tłumaczy brak kategorii. | Research |
| C5 (nawigacja POST) | Odroczony; tylko toast przy błędzie | Rok w URL zmienia architekturę powłoki i wymaga własnej zmiany z ADR. | Research / Plan |
| Wiersze kont jako linki | Odroczone | To zmiana zachowania, nie poprawka UI. | Plan |
| Bramka wizualna | Kitchen sink dev-only `/dev/ui/dashboard` (404 poza `local`) + zrzuty | Powtarzalny dowód bez instalowania Playwrighta. | Plan |
| Zabezpieczenie | `scripts/check-ui-literals.mjs` jako `npm run check:ui` w `composer ci:check` + reguła w `.ai/rules/js.md` | Nieudana kontrola jest pewniejsza niż reguła; Node bez zależności działa na hoście, w Sail i w CI. | Plan |

## Scope

**In scope:**

- `Dashboard.vue`, `DashboardHoldings.vue`, `DashboardNotices.vue`, `DashboardCategorySection.vue`, `BudgetPlanSummaryStrip.vue` (także na stronie Budżet), `CategoryMeterRow.vue`
- Nowe: `StatTile.vue`, `lib/amount-tone.ts` (+ test), `lib/budget-year-selection.ts`, `types/dashboard.ts`, kitchen sink (kontroler, trasa, strona, fixture'y, test Feature), `scripts/check-ui-literals.mjs`
- `components/ui/badge` (2 warianty), `app.css` (`column-caption`, nagłówek kategorii na telefonie), `lang/pl/ui.php` + `UiTranslations`
- `.docs/design-system.md`, `.ai/rules/js.md`, `package.json`, `composer.json`

**Out of scope:**

- Rok budżetu w URL (C5), wiersze kont jako linki, układ siatki kafelków
- `column-caption` i `amountToneClass` w innych widokach (Operacje, Plan miesiąca, Import, Konta, Lokaty)
- Skeletony i loading (N/A), Playwright / `toHaveScreenshot`, hover wierszy (konwencja)

## Architecture / Approach

Kolejność według 10x-ui: biblioteka i kitchen sink → wartości kontraktu → jeden widok → stany → zabezpieczenie. Nowe elementy (`amountToneClass`, `StatTile`, warianty `Badge`, `column-caption`) powstają przed wymianą w widoku, więc fazy 1–2 nie zmieniają Dashboardu, a zrzuty „przed” są czyste. Kitchen sink renderuje prawdziwy `Dashboard.vue` z typowanymi fixture'ami (`DashboardPageProps`) dla 7 scenariuszy danych. Interaktywne stany (hover, fokus, disabled) są dokumentowane zrzutami z działającej aplikacji w `states.md`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kitchen sink i biblioteka | `/dev/ui/dashboard`, zrzuty „przed”, `amountToneClass`, `StatTile`, warianty `Badge` | Fixture'y rozjadą się z propsami PHP (pilnuje `vue-tsc` i `DashboardTest`) |
| 2. Wartości tokenów | `column-caption`, kontrakt zapisany w `design-system.md` | `@apply` własnej klasy w v4 wymaga `@utility` |
| 3. Jeden widok (C1–C4) | Kafelki, neutralne zero, `Badge`, fokus, nagłówek na telefonie, puste stany | Zmiany widoczne też na stronie Budżet (pasek planu) |
| 4. Stany | Toast dla C5, pełna macierz 7 stanów, `states.md`, zrzuty „po” | Nazwa zdarzenia błędu `<Form>` / `<Link>` w Inertia v3 do potwierdzenia w docs |
| 5. Zabezpieczenie | `npm run check:ui` w `ci:check`, reguła w `.ai/rules/js.md`, deferred w `research.md` | Fałszywe trafienia grepa w przyszłych plikach |

**Prerequisites:** działający Sail z bazą demo (`DemoHouseholdSeeder`), `npm run build` po dodaniu trasy (Wayfinder), przeglądarka do zrzutów przy 1440 px i 390 px.
**Estimated effort:** ~3–4 sesje w 5 fazach. Fazy 1 i 3 są największe.

## Open Risks & Assumptions

- Kitchen sink renderuje całą stronę `Dashboard.vue`, więc checklista pierwszego uruchomienia i `shell.budgetYears` pochodzą z prawdziwej powłoki zalogowanego użytkownika, nie z fixture'ów.
- Pigułki na `Badge` mają ikonę 12 px i mniejszy padding niż dziś; to zamierzona różnica.
- Podpisy kolumn w Dashboardzie zmieniają się z 12 px `tracking-wide` na 11 px `tracking-wider`, także w pasku planu na stronie Budżet.

## Success Criteria (Summary)

- Na telefonie każda kwota w wierszu kategorii ma podpis, a 0,00 zł nigdzie na Dashboardzie nie jest zielone.
- Nowy użytkownik bez kont widzi na każdej szerokości akcję „Otwórz konta”, a nieudany wybór roku kończy się komunikatem.
- `composer ci:check` przechodzi z `npm run check:ui`, skan widoku daje 0 trafień, a macierz 7 stanów w `states.md` jest kompletna.
