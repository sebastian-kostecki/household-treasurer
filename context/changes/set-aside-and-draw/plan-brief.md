# S-02: Odkładam / dobieram — Plan Brief

> Full plan: `context/changes/set-aside-and-draw/plan.md`
> Research: `context/changes/set-aside-and-draw/research.md`

## What & Why

Użytkownik wpisuje w planie miesiąca jedną kwotę „odkładam / dobieram” i od razu widzi jej wpływ na „do wydania” (FR-004). Odkładanie obniża „do wydania”, dobieranie (Draw) je podnosi. Dobieranie ponad płynność dysponowalną (Spendable Liquidity) daje ostrzeżenie, ale niczego nie blokuje i nie zmienia sald (FR-016).

## Starting Point

S-01 dał plan miesiąca z kwotami kategorii, propozycję liczoną przy odczycie, parę wzoru „do wydania” PHP/TS i snapshot v5. Set-aside jest w domenie (`CONTEXT.md`, ADR-0033), ale w kodzie równa się 0: brak kolumny, parametru wzoru i pola na stronie.

## Desired End State

Pod kartą „Do wydania” jest karta z przełącznikiem „Odkładam / Dobieram” i kwotą. Liczba reaguje od razu, a zatwierdzenie zapisuje kwotę w planie. Kolejny miesiąc proponuje tę samą kwotę odkładania, a po dobieraniu proponuje 0. Draw większy niż dzisiejsza płynność pokazuje ostrzeżenie dla miesiąca bieżącego i przyszłych. Backup i restore przenoszą kwotę.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zapis | Para `approved_/current_set_aside_minor_units` w `month_plans`, domyślnie 0 | Set-aside jest pozycją planu, więc ma wersję zatwierdzoną i bieżącą dla S-03 i S-09, a 0 obsługuje stare plany. | Research / domena |
| Reguła propozycji | Kopiuj dodatni Set-aside ze źródła propozycji; Draw lub brak źródła → 0 | Nawyk odkładania przechodzi dalej, a jednorazowy Draw (przesunięcie pensji, `prd.md:124`) nie zawyża po cichu kolejnego miesiąca. | Plan |
| Wprowadzanie znaku | Przełącznik „Odkładam / Dobieram” + nieujemna kwota, zapis ze znakiem | Kierunek jest jawny, a klawiatura dziesiętna na telefonie nie ma minusa. | Plan |
| Zakres ostrzeżenia | Miesiąc bieżący i przyszłe w każdym stanie; nigdy miniony; porównanie z saldem na dziś | Ryzyko zostaje widoczne po zatwierdzeniu, a dla minionego miesiąca dzisiejsze saldo nic nie znaczy. | Plan |
| Snapshot | Wersja 5 bez podbicia, przy odczycie `?? 0` | Snapshoty v5 już zrobione na `development` nadal się przywracają (precedens `is_paced`). | Plan |
| Miejsce na stronie | Osobna karta pod „Do wydania”, przed listami | Wpis i jego wpływ są na jednym ekranie nawet przy długiej liście kategorii. | Plan |
| Warunek ostrzeżenia | Jedna funkcja TS z testem, serwer podaje tylko płynność (`null` dla minionego) | Podgląd musi reagować przed zapisem, a serwer niczego nie blokuje, więc nie potrzebuje kopii. | Plan |
| Spendable Liquidity | Helper `SpendableLiquidity::forUser`, użyty też przez Dashboard | Jedna definicja zamiast trzeciej kopii zapytania. | Research / Plan |

## Scope

**In scope:**

- migracja, model, fabryka, odczyt i reguła propozycji;
- wzór PHP/TS z testami;
- request (±1e12, wymagane), akcja, kontroler, propsy;
- karta z przełącznikiem, podglądem i ostrzeżeniem, teksty;
- snapshot v5;
- `CONTEXT.md`, ADR-0033, FR-ADM-01.

**Out of scope:**

- zmiana Set-aside po zatwierdzeniu (S-03);
- powiadomienie Dashboardu o Draw;
- saldo na inną datę;
- blokada przy Draw;
- snapshot v6;
- podsumowanie miesiąca (S-09).

## Architecture / Approach

Kwota przechodzi tą samą drogą co kwoty kategorii: kolumna → `MonthPlanProposal` → `MonthPlanReading` → `ToSpendCalculator` (PHP i TS) → props → `MonthPlan.vue` → `ApproveMonthPlan`. Wyjątkiem jest `MonthCategoryActuals`, bo Set-aside nie ma kwoty faktycznej. Ostrzeżenie tylko czyta saldo (`SpendableLiquidity`) i nic nie zapisuje.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Zapis i propozycja | Kolumny, odczyt z kwotą Set-aside, reguła propozycji | Łańcuch źródła przez propozycje i styczeń z poprzedniego budżetu |
| 2. Wzór, zatwierdzenie i strona | Para wzoru, zatwierdzenie ze znakiem, karta z podglądem i ostrzeżeniem | Kontrakt propsów po obu stronach; −0 i nieprawidłowa kwota w formularzu |
| 3. Snapshot i dokumenty | Backup/restore v5 z `?? 0`, reguła w dokumentach domeny | Snapshoty v5 z `development` bez nowych kluczy |

**Prerequisites:** S-01 na gałęzi (jest). Sail z PostgreSQL i obraz `sail-8.5/app` do testów oraz podglądu z worktree.
**Estimated effort:** ~2–3 sesje w 3 fazach. Faza 2 jest największa.

## Open Risks & Assumptions

- Plany zatwierdzone przed S-02 mają Set-aside 0 i do S-03 nie da się go zmienić.
- Dla miesiąca przyszłego ostrzeżenie porównuje Draw z dzisiejszym saldem, nie z saldem w tym miesiącu. Tekst mówi wprost „dzisiejsza płynność”.
- S-03 powinien wyjść od gałęzi z S-02, bo zmienia te same pliki i `current_set_aside_minor_units`.

## Success Criteria (Summary)

- Wpisanie „Odkładam 500” obniża „do wydania” o 500,00 zł od razu, a po zatwierdzeniu kolejny miesiąc proponuje 500,00 zł.
- „Dobieram” ponad płynność pokazuje ostrzeżenie, a zatwierdzenie przechodzi bez zmiany sald.
- Obie kopie wzoru dają 1700,00 i 2500,00 dla przykładu F-01. Backup i restore zachowują kwotę.
