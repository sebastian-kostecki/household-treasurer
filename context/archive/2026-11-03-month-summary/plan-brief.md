# S-09: Podsumowanie miesiąca — Plan Brief

> Full plan: `context/changes/month-summary/plan.md`
> Research: `context/changes/month-summary/research.md`

## What & Why

Użytkownik po zakończeniu miesiąca z zatwierdzonym planem ma zobaczyć jego podsumowanie: plan zatwierdzony i końcowy, wykonanie, odchylenia, odkładanie zamierzone i realne oraz dobrane kwoty w skali roku. To ostatnia brakująca część US-01 (FR-009) i druga połowa FR-013: minione miesiące z planem pokazują podsumowanie i końcowe „do wydania”.

## Starting Point

Na `development` miniony zatwierdzony miesiąc pokazuje na stronie „Plan miesiąca”:

- „do wydania” liczone na żywo;
- odkładanie końcowe;
- tabele pozycji z kolumną „Zatwierdzono”.

Wszystkie dane już są (`MonthToSpend`, `BudgetMonthResult`, kolumna `approved_set_aside_minor_units`). Brakuje odczytu zatwierdzonego odkładania, sum na poziomie miesiąca, sumy „dobieram” w roku i karty w UI.

## Desired End State

Strona „Plan miesiąca” dla minionego miesiąca z zatwierdzonym planem pokazuje kartę „Podsumowanie miesiąca”:

- tabelę sum Dochody/Wydatki × Zatwierdzony | Końcowy | Faktycznie | Odchylenie;
- odkładanie zatwierdzone, końcowe, faktyczne (Wynik) i różnicę;
- „Dobrane od początku roku”.

Karta „do wydania” ma tam etykietę „Do wydania (końcowe)”. Pasek miesiąca na stronie Budżet linkuje do niej tekstem „Podsumowanie miesiąca”. Późna operacja zmienia podsumowanie po odświeżeniu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| „Dobrane w skali roku” | Σ \|końcowego odkładania\| < 0 z zatwierdzonych planów, od stycznia do podsumowywanego miesiąca włącznie (luty: sty −300 końcowe, lut +1000, mar −200 → 300) | Ten sam plan końcowy co reszta karty; miniona karta nie zmienia się po edycji przyszłego planu | Plan |
| Odkładanie zamierzone a realne | Zatwierdzone, końcowe, Wynik i różnica = Wynik − końcowe (1000/500/700 → +200; −400 i −100 → +300) | Używa kolumny zapisanej dla S-09 i mierzy względem planu, którym użytkownik skończył | Plan |
| „Odchylenia” miesiąca | Tabela sum: Faktycznie − Końcowy, zwykłe sumy; niezapłacony rachunek liczy się jako −plan | Jedna zwarta tabela, która uzgadnia się z Wynikiem | Plan |
| Powierzchnia i moment | Karta na stronie Plan miesiąca, tylko miniony miesiąc z zatwierdzonym planem | Strona ma już wiersze, kwoty zatwierdzone i znacznik „past”; S-01 zostawił ją pod S-09 | Plan |
| Źródło „Faktycznie” i Wyniku | `BudgetMonthResult` | Kanoniczna definicja Wyniku, więc tabela zgadza się z Wynikiem z konstrukcji | Plan |
| Odczyt na żywo | Bez zamrażania; późna operacja zmienia podsumowanie | Rozstrzygnięte w PRD („Rozliczenie końca miesiąca”) | Research |
| Miniony bez planu | Bez zmian: Wynik i znacznik braku planu | Rozstrzygnięte w S-06 | Research |
| Rodzaj pozycji | Bieżący rodzaj kategorii, bez snapshotu | Ten sam kompromis co zaakceptowane „approved 0” dla kategorii dołączonych | Research → Plan |
| Logika w TS | Brak; serwer liczy, prop `monthPlan.summary` | Odczyt tylko do odczytu, zgodnie z regułą `AGENTS.md` | Plan |
| Karta odkładania w minionym miesiącu | Ukryta, gdy jest podsumowanie | Ta sama treść byłaby na ekranie dwa razy | Plan |

## Scope

**In scope:**

- `MonthPlanReading.approvedSetAsideMinorUnits`;
- nowa klasa `App\Support\MonthSummary` i `tests/Feature/MonthSummaryTest.php`;
- prop `monthPlan.summary` i typ TS;
- `MonthSummaryCard.vue`;
- etykieta „Do wydania (końcowe)” i tekst linku w `BudgetMonthStrip`;
- klucze `lang/pl/ui.php`;
- asercje w `MonthPlanPageTest`;
- doprecyzowanie wpisu Month Summary w `CONTEXT.md`.

**Out of scope:**

- podsumowanie bieżącego miesiąca;
- osobna strona;
- zamykanie i przenoszenie miesiąca;
- snapshot rodzaju pozycji;
- przywracanie „Pozostało w miesiącu”;
- „dobieram” z przyszłych miesięcy;
- licznik pozycji ponad plan;
- sprzątanie propsów Month Remaining.

## Architecture / Approach

`MonthPlanController::monthPlanPayload` ma już `MonthToSpend` (odczyt, wykonanie, kategorie, propozycja). Dla `timing === 'past'` woła `MonthSummary::forMonth($toSpend, $budget, $month)`, które zwraca null bez zatwierdzonego planu. Klasa liczy sumy z `reading->approvedAmounts`/`amounts`, „Faktycznie” i Wynik z `BudgetMonthResult` (jedno zapytanie), a „dobieram” z `proposal->reading(1..N)` na planach już załadowanych w pamięci. `toArray()` jest kontraktem propa, lustrzanym z typem `MonthSummary` w `resources/js/types/month-plan.ts`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Odczyt podsumowania na serwerze | `MonthSummary` z czterema definicjami, testy (SQLite + PostgreSQL), wpis w `CONTEXT.md` | Funkcje dat w `BudgetMonthResult`; zielony SQLite nic nie mówi o PostgreSQL |
| 2. Prop i strona | Karta na „Plan miesiąca”, etykiety, link w pasku Budżetu, `assertInertia`, `ci:check` | Konflikt merge z S-10 w `MonthPlan.vue` / `MonthPlanPageTest` |

**Prerequisites:** gałąź z `development`; na nią trafia `context/changes/month-summary/` (cherry-pick z `worktree-month-summary-research`). Działający stack Sail, `composer install`, `.env`, `npm ci`, `npm run build` w worktree (`AGENTS.md`).
**Estimated effort:** ~1–2 sesje w 2 fazach.

## Open Risks & Assumptions

- Ten worktree wyszedł z `main`, a kod planu miesiąca jest tylko na `development`. Implementacja na złej bazie nie skompiluje się.
- S-10 (`worktree-month-plans-vs-year`) zmienia te same pliki. Kolejność merge decyduje, kto rozwiązuje konflikty (oczekiwane: importy i sąsiednie linie).
- Suma „Odchylenie” wydatków może różnić się od sumy odchyleń widocznych w wierszach, gdy rachunek lub dochód nie został rozliczony. To świadoma definicja, nie błąd.

## Success Criteria (Summary)

- Miniony miesiąc z zatwierdzonym planem pokazuje podsumowanie z liczbami zgodnymi z przykładami z planu, a Faktycznie dochody − Faktycznie wydatki = Wynik.
- Bieżący miesiąc i miniony bez planu wyglądają jak dziś.
- `composer ci:check` przechodzi w obrazie Sail.
