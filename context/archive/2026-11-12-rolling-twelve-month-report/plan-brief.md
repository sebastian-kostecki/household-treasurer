# Ostatnie 12 miesięcy — Plan Brief

> Full plan: `context/changes/rolling-twelve-month-report/plan.md`

## What & Why

S-32 (FR-025, nice-to-have): Raporty dostają okres „ostatnie 12 miesięcy”, czyli pełny cykl roku niezależny od roku kalendarzowego. Wydatki nieregularne (OC, prąd) wpadają w nim raz, a w styczniu czy lutym nie trzeba patrzeć na prawie pusty raport bieżącego roku.

## Starting Point

Raport okresu to dziś odczyt jednego Budżetu: miesiąc albo rok (ADR-0070), z porównaniem lat (ADR-0074) i Trendem (ADR-0077). Wiersze są już kluczowane kategorią listy, a kolejność i grupy idą przez wspólny `ReportCategoryTree`, więc złączenie dwóch Budżetów mieści się w modelu. Słownik i ADR trzeba jednak rozszerzyć, bo Period Report jest z definicji „jednym Budżetem”.

## Desired End State

Przełącznik ma segmenty Rok | Miesiąc | 12 miesięcy | Trend. 12.11.2026 „12 miesięcy” pokazuje listopad 2025 – październik 2026: panel z „Wynik 12 miesięcy”, wykres i tabele jak w raporcie roku, bez porównania. Na produkcji, gdzie jest tylko Budżet 2026, notka mówi: „Bez budżetu w realizacji: listopad 2025 – grudzień 2025. Kwoty obejmują styczeń 2026 – październik 2026 (10 z 12 miesięcy).” Nazwa kategorii linkuje do Operacji z zakresem całego okna.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Które 12 miesięcy | 12 zamkniętych miesięcy, koniec na miesiącu przed dzisiejszym (UTC), niezależnie od wybranego roku | Pełny cykl roku, każdy wydatek nieregularny raz, brak logiki „w toku”. |
| Odrzucone okna | bieżący miesiąc w toku, 365 dni, koniec wybierany paskiem miesięcy | Niepełny miesiąc zaniża sumę, 365 dni łamie zgodność z Wynikami miesięcy, wybór końca to większy zakres. |
| Brak Budżetu w części okna | Liczyć tylko miesiące z Budżetem w realizacji, notka nazywa brakujące, zero pokrytych → stan pusty | Działa na produkcji od wdrożenia i mówi wprost, ile miesięcy liczy. |
| Porównanie z rokiem wcześniej | Brak (`comparison: null`) | Pełne porównanie byłoby na produkcji możliwe najwcześniej 1.01.2028, a częściowe myli. |
| Trend okna | Brak | ADR-0077 zostaje rokiem Budżetu. |
| Miejsce w przełączniku | Czwarty segment po „Miesiąc”, `?view=last-12` | Jeden klik, kolejność Rok/Miesiąc bez zmian. |
| Przełącznik roku na oknie | Prowadzi do raportu wybranego roku (dzisiejsze `destination: 'reports'`) | Klik w rok zawsze ma widoczny skutek, bez zmian w backendzie. |
| Wynik okna | Suma Month Result pokrytych miesięcy, UI „Wynik 12 miesięcy” | To nie Year Result; ryzyko z roadmapy wskazuje sumę Wyników miesięcy jako dowód. |
| Znacznik archiwum | Z linii najnowszego pokrywającego Budżetu z tą kategorią, inaczej z listy | Przedłużenie reguły ADR-0070/0074 na dwa Budżety. |
| Link do Operacji | Zakres całego okna (`date_from`/`date_to` bez `year`) | Operacje istnieją tylko w Budżecie w realizacji, więc zakres pokazuje to, co liczy raport. |

## Scope

**In scope:**
- wpis w słowniku (Period Report) i ADR-0081;
- `ReportTwelveMonthWindow` i `PeriodReport::forWindow`;
- `?view=last-12` i prop `period.kind`/`period.window` (PHP i TS);
- testy równości, PostgreSQL i NFR;
- segment, tytuł z zakresem, „Wynik 12 miesięcy”, `ReportCoverageNote` i stan pusty bez pokrycia;
- link z zakresem okna;
- kitchen sink, strażnicy UI, `.ai/rules/reports.md`, E2E.

**Out of scope:**
- porównanie okna z rokiem wcześniej i Trend okna;
- bieżący miesiąc, 365 dni, wybór końca okna;
- wyłączanie segmentu przy częściowym pokryciu;
- nowe `destination` przełącznika roku;
- średnia miesięczna i ekstrapolacja;
- zmiany w Roku, Miesiącu i Trendzie;
- własny zakres dat, eksport i druk.

## Architecture / Approach

`ReportController` przy `?view=last-12` woła `ReportTwelveMonthWindow::resolve(user, today UTC)`. Okno daje daty, pokrywające Budżety w realizacji (≤ 2) oraz zakresy pokrycia i braku. `PeriodReport::forWindow` czyta linie i jeden `MonthCategoryActuals::forRange` na Budżet, złącza wiersze po `category_id` i idzie dalej ścieżką raportu roku (`ReportCategoryTree`, udział, sumy). Strona dostaje `period.window` i `report`, a tylko formatuje: tytuł z zakresem, notkę pokrycia i link `reportRangeOperationsQuery(category, from, through)`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Reguły i odczyt okna na serwerze | Słownik + ADR-0081, okno z pokryciem, odczyt przez dwa Budżety, prop `period` (PHP+TS), testy równości, PostgreSQL, NFR | Granice dat (koniec miesiąca, przejście roku) i równość z sumą Wyników miesięcy co do grosza |
| 2. Widok „12 miesięcy” i linki | Segment, tytuł, notka, stany puste, link z zakresem okna, kitchen sink, strażnicy, reguła, E2E | Cztery segmenty na telefonie (22rem) i stan „draft”, który nie może przejąć okna |

**Prerequisites:** S-28 (done); na devie mock Budżetu 2025 (`php artisan demo:seed-previous-year`); własna baza testowa worktree na PostgreSQL.
**Estimated effort:** ~2 sesje w 2 fazach.

## Open Risks & Assumptions

- Zakładam, że operacje nie istnieją w Budżecie poza realizacją (`app/Support/CategoryList/OperationCategoryChoice.php:48-50`; restore tego nie sprawdza, przyjęty wyjątek). Na tym opiera się link z zakresem całego okna.
- Część niepokryta okna jest zawsze ciągła, bo okno dotyka co najwyżej dwóch lat. Gdyby okno się wydłużyło, model pokrycia trzeba zmienić.
- E2E liczy oczekiwany zakres z prawdziwej daty. Uruchomienie w pierwszym dniu miesiąca przy przesunięciu strefy UTC może dać inny miesiąc niż w przeglądarce, więc test liczy w UTC.

## Success Criteria (Summary)

- Użytkownik wybiera „12 miesięcy” i widzi 12 zamkniętych miesięcy z wynikiem równym sumie Wyników tych miesięcy.
- Przy brakującym Budżecie wie z notki, których miesięcy brak i ile miesięcy liczą kwoty.
- Z kategorii przechodzi do Operacji z tym samym zakresem, a ich suma równa się kwocie w raporcie.
