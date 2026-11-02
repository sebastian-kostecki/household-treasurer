# S-04: Pozycje miesiąca — plan i wykonanie — Plan Brief

> Full plan: `context/changes/month-items-plan-vs-actual/plan.md`
> Research: `context/changes/month-items-plan-vs-actual/research.md`

## What & Why

Strona „Plan miesiąca” ma pokazywać przy każdej pozycji, jak bardzo wykonanie odbiega od planu i czy rachunek albo dochód jest już rozliczony (FR-006). Kategorie jednorazowe mają pokazywać resztę rocznego limitu, a te bez planu i bez operacji w miesiącu mają nie zaśmiecać listy (FR-008, ryzyko roadmapy: „długa lista kategorii z zerowym planem”). Z tego widoku skorzystają później tempo (S-05), powiadomienia (S-07) i podsumowanie miesiąca (S-09).

## Starting Point

S-01 dał stronę z kolumnami Plan i Faktycznie, plakietkami „Jednorazowa”, „Zarchiwizowane” i „Poza planem”, edytowalną propozycją i podglądem „do wydania” w TS. Każda niezarchiwizowana jednorazowa jest już wierszem planu z kwotą 0, a zatwierdzenie wymaga kwot wszystkich pozycji planu.

## Desired End State

W miesiącu z planem każdy wiersz ma odchylenie (faktycznie − plan), a wydatek ponad plan jest wyróżniony kolorem destructive. Rachunek po wpłacie ma plakietkę „Zapłacone”, a dochód po wpływie „Wpłynęło”. Każda jednorazowa pokazuje „Pozostały limit roczny”. Jednorazowe bez planu i bez operacji leżą w zwiniętej sekcji z licznikiem. W edytowalnej propozycji odchylenie przelicza się podczas wpisywania kwot.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Znacznik realizacji | „Zapłacone” (rachunek) / „Wpłynęło” (dochód) po pierwszej kwocie dodatniej; bieżąca bez znacznika | W arkuszu ✓ oznacza, że faktyczna zastąpiła plan (reguła Rachunku); procent dla bieżących dublowałby tempo S-05 | Plan (arkusz) |
| Odchylenie | faktycznie − plan; rachunek/dochód bez kwoty dodatniej → „—”; wydatek ponad plan destructive, dochód > 0 success, ujemne bez koloru | Jedna reguła znaku, kolory z design-system | Plan |
| Ponad plan | wydatek z faktycznie > plan, każdy rodzaj, także plan 0 | Predykat już zdefiniowany w `CONTEXT.md` dla S-07 | Research |
| Lista vs zwinięta sekcja | na liście jednorazowa z planem > 0 albo dowolną operacją w miesiącu (także sam zwrot, także netto 0) | Każdy ruch w miesiącu jest widoczny | Plan |
| Reszta limitu | Available Limit na dziś (plan roczny − wykonanie), przy każdej jednorazowej, na liście i w sekcji | Ta sama liczba co strona Budżet i powiadomienie `one_off_over_limit` | Plan |
| Edytowalna propozycja | odchylenie na żywo przez parę PHP/TS `MonthItemDeviation`; sekcje ustalone przy wczytaniu; pola kwot także w sekcji | Spójne z podglądem „do wydania”; wiersze nie skaczą pod kursorem | Plan |
| Zatwierdzenie | kontrakt `ApproveMonthPlan` bez zmian; sekcja to flaga `is_idle_one_off`, wiersze zostają w `expense_items` | Ukryte pola i podgląd dalej obejmują wszystkie pozycje | Plan |
| Miniony miesiąc bez planu | bez zmian | Należy do S-06 (FR-011, FR-013) | Plan |

## Scope

**In scope:**
- pięć nowych pól wiersza liczonych na serwerze i para `MonthItemDeviation` (PHP i TS z testami),
- kolumna odchylenia od `lg` (węziej: pod kwotą faktyczną), plakietki rozliczenia, Pozostały limit roczny,
- zwinięta sekcja jednorazowych bez planu i operacji,
- komponent `MonthPlanItemRow.vue`, `formatSignedMoney`, tłumaczenia,
- wpis pary w `AGENTS.md`.

**Out of scope:**
- procent realizacji dla pozycji bieżących i tempo (S-05),
- powiadomienia Dashboardu (S-07),
- miesiąc bez planu (S-06),
- limit na koniec przeglądanego miesiąca albo po odjęciu planu,
- przenoszenie wierszy między sekcjami na żywo,
- zmiany schematu i kontraktu zatwierdzenia.

## Architecture / Approach

`MonthPlanController::itemRow` dokłada pola `deviation_minor_units`, `is_over_plan` (z `App\Support\MonthItemDeviation`), `is_settled`, `available_limit_minor_units` (z `CategoryExecutionMetrics`) i `is_idle_one_off` (nowe `MonthCategoryActuals::hasOperations`). `MonthPlan.vue` dzieli wydatki na listę i `Collapsible` według flagi. Oba miejsca rysują ten sam `MonthPlanItemRow.vue`. Poza edycją wiersz pokazuje pola z serwera. W edytowalnej propozycji strona liczy odchylenie kopią TS na wpisanej kwocie, tak jak podgląd „do wydania”.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Odczyt pozycji na serwerze | para PHP/TS z testami, nowe propsy z `assertInertia`, wpis w `AGENTS.md`; strona bez zmian wizualnych | rozjazd kopii PHP i TS, gdy testy nie mają tych samych przypadków |
| 2. Widok pozycji i zwinięta sekcja | kolumna odchylenia, plakietki, limit, zwinięta sekcja, podgląd na żywo, CI i przegląd ręczny | zwinięta sekcja zgubi ukryte pola zatwierdzenia; ciasna siatka na 360 px |

**Prerequisites:** S-01 na gałęzi (jest). Worktree po krokach z `AGENTS.md` („Fresh checkout or worktree”). Do przeglądu ręcznego osobna baza i podgląd z worktree („Preview from a worktree”).
**Estimated effort:** ~2 sesje, po jednej na fazę.

## Open Risks & Assumptions

- Dla minionego zatwierdzonego miesiąca Pozostały limit roczny to stan na dziś, a nie na koniec tamtego miesiąca. To decyzja, nie przeoczenie. Etykieta mówi „roczny”, a nie „w miesiącu”.
- Trzecia para logiki PHP/TS zwiększa koszt utrzymania. Jest mała, a łagodzą ją wpis w `AGENTS.md` i wspólna tabela przypadków w obu testach.
- Brzmienie tekstów („Zapłacone”, „Wpłynęło”, „Pozostały limit roczny”, „Jednorazowe bez planu i wydatków”) to propozycja, do potwierdzenia w przeglądzie ręcznym.

## Success Criteria (Summary)

- Użytkownik widzi przy każdej pozycji, o ile wykonanie odbiega od planu, i od razu rozpoznaje wydatek ponad plan.
- Rachunki zapłacone i dochody, które wpłynęły, są oznaczone. Jednorazowe pokazują, ile zostało z rocznego limitu.
- Lista wydatków nie zawiera jednorazowych bez planu i bez ruchu. Leżą w zwiniętej sekcji, a ich kwoty nadal da się ustawić i zatwierdzić.
