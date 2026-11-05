# Widok Budżetu w systemie „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/budget-atrament-ui/plan.md`
> Research: `context/changes/budget-atrament-ui/research.md`

## What & Why

`/budget` przechodzi na wybrany design system „C · Atrament i morski”: gęsta tabela, kwoty w JetBrains Mono, status opisany słowem, jeden panel miar. Przy okazji wchodzą globalnie dwie zasady systemu, które ADR-0035 zostawił na osobny ADR: przepis panelu i kontrolek 32 px oraz nagłówki kolumn `table-header` zamiast 11 px wersalików. Audyt (C1–C5 w research) pokazał, że bez nowych stylów w `app.css` widoku nie da się napisać bez literałów.

## Starting Point

Budżet ma rozmiary pisma z utility (`text-lg`, `text-xs`), „zł” w każdej komórce (`formatMoney`), trzech osobnych miar w karcie, procentu bez paska, Checkboxa i paska miesiąca `BudgetMonthStrip` z Month Result. Tokeny Atramentu (ADR-0035) już są w `app.css`; brakuje stylów typograficznych, `--control-height`, wariantów Badge `ok`/`brand`/`neutral` i Switcha.

## Desired End State

Budżet wygląda jak mockup MockupCAtrament → Budżet: tytuł z odznaką i jedną akcją „Dodaj kategorię”, panel 3–4 miar (z „Wykonanie wydatków” i postępem roku w realizacji), tabele z kwotami mono bez „zł” i stopką „Kwoty w zł”, pasek realizacji z kreską roku i Badge „przekroczono”, kolumna „Typ” w szkicu, Switch archiwum. W całej aplikacji panele mają 10 px i pełną ramkę, przyciski i pola 32 px (małe 26 px), nagłówki kolumn `table-header`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres globalny | Punkty 3 i 5 README w tej zmianie, ADR-0037 | Budżet nie może wyglądać inaczej niż reszta, a ADR-0035 to odłożył | Research |
| `BudgetMonthStrip` | Usunięty z Budżetu | Mockup go nie ma | Research |
| Month Result i „do wydania” | Przejmuje raport okresu (S-11), Dashboard i Plan miesiąca; ADR-0037 i PRD to zapisują | Bez nowej pracy w innych widokach; luka do scalenia S-11 jest uznana | Plan |
| Propsy miesiąca | Usunięte wszystkie martwe, `BudgetMonthReadings` też; testy obliczeń `BudgetMonthResult` przepisane na bezpośrednie wywołanie | Kontroler przestaje liczyć niczytane odczyty, a S-11 zachowuje pokrycie klasy | Plan |
| Stany paska | Norma (`foreground`) i przekroczono (`destructive` + Badge) | Tylko to rozstrzyga domena; „blisko limitu” wymaga reguły w CONTEXT.md | Plan |
| Kontrolki 32 px | Button default/icon 32, sm/icon-sm 26, Input/Select/`.native-select` 32, `radius-md`; Dialog bez zmian | Przyciski i pola w jednym wierszu mają tę samą wysokość | Plan |
| Akcja dodania | Jeden zarysowany „Dodaj kategorię” w tytule, jeden dialog z wyborem typu | Mockup i zasada jednej akcji głównej | Plan |
| Kolumna „Typ” w szkicu | Tak, Badge `neutral` | Dane już są w propsach | Plan |
| Archiwum | Osobna sekcja zostaje, zmienia się wygląd | CONTEXT.md:36 i FR-REP-15 | Research |
| Pasek | Wspólny `ExecutionMeter` dla Budżetu i Dashboardu | Jeden przepis Metera zamiast dwóch | Plan |
| `column-caption` | Przemianowany na `table-header`, bez aliasu | Jeden przepis podpisu kolumn | Plan |

## Scope

**In scope:**
- ADR-0037, adnotacje ADR-0003/0033/0035, PRD FR-REP-13/14, `.docs/design-system.md`, `.ai/rules`
- Style typograficzne i tokeny wymiarów w `app.css`, Card/Button/Input/Select, Badge, Switch, `formatMoneyAmount`, `ExecutionMeter`, `StatPanel`
- Kontrakt propsów Budżetu (`year_elapsed_percent`, usunięte propsy miesiąca)
- Widok Budżetu, kitchen sink, `check-ui-literals`, zrzuty

**Out of scope:**
- Punkty 7 i 8 README, ⌘K, promień Dialog, stan „blisko limitu”
- `StatTile` i typografia innych widoków, `MonthPaceMeter`, ręczne `rounded-2xl`/`h-9` poza `components/ui`
- Nowa powierzchnia Month Result

## Architecture / Approach

Najpierw dokumenty, potem warstwa globalna sprawdzona zrzutami innych widoków, potem kontrakt propsów po obu stronach w jednym commicie, na końcu widok na nowych stylach. Wartości pochodzą z tokenów w `app.css`; widok nie ma literałów (`ui-contract.test.ts`, `check-ui-literals.mjs`). Postęp roku liczy serwer przez `YearElapsed`, procent wykonania jest już w `expenseTotals`, więc nie powstaje kopia TS.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0037 i zgodne dokumenty | Rozjazd PRD z ADR-0033 |
| 2. Warstwa globalna | Style, kontrolki 32/26 px, Badge, Switch, `ExecutionMeter`, zrzuty innych widoków | Regresje wizualne w całej aplikacji; CLI shadcn-vue przy brakującym `tailwind.config.js` |
| 3. Kontrakt propsów | Kontroler bez odczytów miesiąca, `year_elapsed_percent`, przepisane testy | Utrata pokrycia przy usuwaniu testów |
| 4. Widok Budżetu | Nowy widok, sink 7 stanów, straż literałów, zrzuty | Szerokość kolumn na 390 px po dodaniu paska |

**Prerequisites:** Sail z PostgreSQL działa; tokeny ADR-0035 są na `development`.
**Estimated effort:** ~3–4 sesje w 4 fazach.

## Open Risks & Assumptions

- Do scalenia S-11 Month Result miesiąca bez zatwierdzonego planu nie ma w UI (uznane w ADR-0037).
- Hover `secondary` na jasnym `card` ma niski kontrast; zostaje wartość systemu, sprawdzana na zrzucie.
- CLI shadcn-vue może nie działać przez `tailwind.config.js` w `components.json`; plan przewiduje ręczny komponent na `reka-ui`.

## Success Criteria (Summary)

- `/budget` w realizacji i szkicu odpowiada mockupowi w obu motywach, przy 1280 i 390 px
- Inne widoki mają nowy przepis panelu, kontrolek i nagłówków bez regresji na zrzutach
- `composer ci:check`, `sloppy ci`, `npm run check`, `check:ui`, `types:check`, `npm test` zielone
