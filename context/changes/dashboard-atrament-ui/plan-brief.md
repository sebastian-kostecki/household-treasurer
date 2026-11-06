# Dashboard w systemie „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/dashboard-atrament-ui/plan.md`
> Research: `context/changes/dashboard-atrament-ui/research.md`

## What & Why

Dashboard wygląda jak inna aplikacja niż Budżet, Operacje i Plan miesiąca, które już przeszły na Atrament. Ma dwie strefy liczb w różnych rozmiarach, dwie karty kategorii z sumą na dole, jaskrawe karty ostrzeżeń, konta schowane za akordeonem na telefonie i kreskę postępu roku za checkboxem. Zmiana przenosi widok na wzorzec MockupCAtrament → Dashboard, żeby w kilka sekund było widać, ile można wydać i co wymaga uwagi.

## Starting Point

Trzy `StatTile` i `BudgetPlanSummaryStrip`, dwa `DashboardCategorySection` z limitem 5 i ręcznym paskiem sumy, `DashboardNotices` z tintami wagi, `DashboardHoldings` z drugim drzewem na telefon. Siatka `lg:grid-cols-3`, brak wiersza tytułu. Spec `.docs/specs/dashboard-hierarchy.md` opisuje obecny układ, a `check:ui` nie łapie typografii ani ramek sprzed Atramentu.

## Desired End State

Wiersz tytułu z datą i stanem budżetu, a pod nim jeden panel miar: Do wydania z paskiem dni, Płynność, Łączne środki i Postęp roku. Niżej ostrzeżenia w stylu `ht-notice`, potem jeden panel „Realizacja budżetu” z zakładkami Wszystko / Dochody / Wydatki, wierszami grup, kolumną Status i klikalnymi wierszami. Obok, w kolumnie 300 px, panele Konta i Lokaty ze stopką sumy. Kreska postępu roku stoi zawsze. Straż `DashboardHardcodedUiTest` blokuje powrót starych stylów, a `/dev/ui/dashboard` pokazuje pełną macierz stanów.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Panel miar | `StatPanel` z opcjonalnymi slotami i wrapper `DashboardStatPanel` | Badge, link POST i pasek dni nie mieszczą się w typie tekstowym, a sloty nie zmieniają Budżetu i Planu miesiąca. | Research |
| Pasek dni i tempo | Nowy props `month_pace` z `MonthPace::forMonth` | Zero nowych zapytań i bez kopii TS logiki miesiąca. | Research |
| Dokumenty przed kodem | ADR-0043 + `CONTEXT.md:60` + noty w specu | Układ przeczy historyjkom 15–22, 25, 28, a Postęp roku to termin słownika. | Research |
| Szkic budżetu | Pasek sum planu tylko w szkicu; w realizacji panel z zakładkami | Zachowuje historyjkę 25 i props `planSummary` z testami. | Plan |
| Wiersz kategorii | Link do Operacji z filtrem kategorii za bieżący rok przez `operationsListQuery` | Odpowiada na „skąd ta kwota” jednym klikiem, zgodnie z regułą jedynego buildera query. | Plan |
| Karta ostrzeżenia | Styl `ht-notice`, treść jak dziś (tożsamość i kwota) | Bez nowych tekstów dla 7 rodzajów i bez zmiany payloadu ADR-0032. | Plan |
| Stopki Kont/Lokat | Suma widocznych wierszy, etykiety „Łączna płynność” / „Suma lokat” | Płynność z serwera liczy też konta zarchiwizowane z saldem, więc nie równa się wierszom. | Plan |
| Zakładki | Stan lokalny, start od „Wszystko” | Historyjka 29: Dashboard zawsze otwiera się w tej samej formie. | Plan |
| Limit kategorii | Bez limitu, bez rozwijania | Decyzja użytkownika, zgodna z mockupem; ADR-0043 zastępuje historyjki 20–21. | Plan |
| Kolejność strony | Miary przed ostrzeżeniami | Bez zmiany dzisiejszego zachowania, zgodne z mockupem; ADR zapisuje odejście od specu. | Plan |

## Scope

**In scope:**

- ADR-0043, wpis w CONTEXT, noty w specu, `design-system.md`, `.ai/rules/js.md`
- sloty w `StatPanel`, nazwane klasy w `app.css` (`--aside-width`, pasek dni, karta ostrzeżenia, Status i wiersz grupy), `DashboardHardcodedUiTest`
- props `month_pace` po obu stronach z `assertInertia`, helper `categoryOperationsQuery`
- wiersz tytułu, `DashboardStatPanel`, karty ostrzeżeń, `DashboardExecutionPanel`, `CategoryMeterRow` ze Statusem i linkiem, dwa panele Konta/Lokaty
- usunięcie checkboxa, akordeonu, limitu 5, martwej gałęzi archiwum i osieroconych kluczy
- 9 nowych scenariuszy kitchen sinka i zrzuty 390/1280 px w obu motywach

**Out of scope:** „Dodaj operację” / „Importuj wyciąg” w tytule, `⌘K`, status „blisko limitu”, zdanie z liczbami w karcie, zakładka w URL, przepięcie linków ostrzeżeń na `operationsListQuery`, restyle `StatTile` w Kontach/Lokatach i `ListSurface` `CardTitle`, zmiana `planSummary`.

## Architecture / Approach

Serwer dokłada jeden props (`month_pace`) z odczytu, który już ma. Reszta to warstwa Vue: wrappery z `computed` nad prostymi prymitywami (`DashboardStatPanel` → `StatPanel`, `DashboardExecutionPanel` → `ListSurface` + `SegmentedControl` + `CategoryMeterRow` + `ExecutionMeter`), a układ w nazwanych klasach `@layer components`. Kwoty sum grup pochodzą z istniejących propsów `incomeTotals` / `expenseTotals`, a jedyne liczenie na kliencie to suma widocznych wierszy kont i lokat, jak dziś.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0043, CONTEXT, noty w specu, design-system, reguły | Dokumenty rozjadą się z kodem, jeśli implementacja zmieni decyzję bez aktualizacji ADR |
| 2. Warstwa wspólna i straże | Sloty `StatPanel`, klasy w `app.css`, `DashboardHardcodedUiTest` | Zmiana `StatPanel` przypadkiem zmieni Budżet i Plan miesiąca |
| 3. Kontrakt propsów i logika | `month_pace` z testami, `categoryOperationsQuery` | Pole wymagane w typie psuje `types:check`, jeśli fixtures nie pójdą w tym samym commicie |
| 4. Górna część widoku | Tytuł, panel miar z paskiem dni, karty ostrzeżeń, siatka 300 px | Utrata obsługi pending i toastu `select-year` przy przenoszeniu formularzy |
| 5. Realizacja i Konta/Lokaty | Panel z zakładkami, szkic, dwa panele, sprzątanie | Link na siatce: fokus i hover przegrywają z utility; przepełnienie na 390 px |
| 6. Stany i bramka wizualna | 9 scenariuszy, zrzuty, pełny `ci:check` | Zrzuty pominięte jak w poprzednich przeglądach |

**Prerequisites:** świeży worktree po krokach z AGENTS.md (`composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`); działający Sail (PostgreSQL i sieć `household-treasurer_sail`) do testów i podglądu.
**Estimated effort:** ~3–4 sesje w 6 fazach; fazy 4 i 5 są największe.

## Open Risks & Assumptions

- Usunięcie limitu 5 przy budżecie z kilkudziesięcioma kategoriami wydłuża lewą kolumnę; zrzut `many-categories` to pokaże.
- Pusty tor `ExecutionMeter` na tle `muted` w wierszu grupy ma ok. 1.1:1 (przegląd budget F2); do oceny na zrzutach, bez zmiany tokenu w tym planie.
- `IndexEmptyState` jest współdzielony: zmiana nagłówka na `section-title` dotyka stanów pustych innych widoków.

## Success Criteria (Summary)

- Użytkownik widzi jedną strefę liczb z Do wydania na pierwszym miejscu i kreską postępu roku bez szukania przełącznika.
- Dochody i wydatki leżą w jednej tabeli z sumą nad pozycjami, a klik w wiersz pokazuje operacje tej kategorii.
- `composer ci:check`, `sloppy ci` i `check:ui` są zielone, a zrzuty w obu motywach na 390 i 1280 px pokazują wszystkie stany macierzy.
