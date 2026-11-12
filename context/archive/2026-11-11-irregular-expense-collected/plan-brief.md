# Wydatek nieregularny i „zebrane” — Plan Brief

> Full plan: `context/changes/irregular-expense-collected/plan.md`

## What & Why

Wydatek płacony kilka razy w roku (prąd, OC, serwis) obniża dziś „do wydania” jednym skokiem w miesiącu rachunku, więc użytkownik przelewa drobne stawki na osobne konto. S-26 (US-02, FR-010, FR-011, FR-013, FR-022) dodaje czwarty rytm „nieregularna”: co miesiąc „do wydania” obniża stała stawka, która rośnie jako „zebrane”, a rachunek zużywa zebrane i obniża „do wydania” tylko o nadwyżkę. Pieniądze zostają na koncie głównym.

## Starting Point

Po S-24 kategoria ma rytm jako historię wpisów (trzy wartości), zmieniany tylko w „Kategoriach”; reguły nieregularnej są już w ADR-0066, ale w kodzie nie ma ani kwoty na wpisie, ani odczytu, który liczyłby coś łańcuchem przez miesiące i lata. Reguła pozycji „do wydania” żyje w parze kalkulatorów PHP/TS i zna tylko plan i wykonanie miesiąca.

## Desired End State

W „Kategoriach” oznaczasz wydatek jako nieregularny i podajesz zebrane startowe. W planie miesiąca kwota wiersza to stawka (plan roczny ÷ 12), pod nazwą stoi „zebrane X”, a rachunek ponad zebrane pokazuje nadwyżkę i „ponad plan”. Prąd ze stawką 150 i zebranym 300 na 1 lipca: rachunek 400 zostawia zebrane 50 i obniża „do wydania” o 150; rachunek 600 zeruje zebrane i obniża „do wydania” o 300.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Stawka, łańcuch zebranego, podłoga 0, nadwyżka, słownictwo | Reguły ADR-0066 | Rozstrzygnięte w F-05 przed kodem. | ADR-0066 |
| Rachunek w miesiącu budżetu-szkicu albo roku bez budżetu (F9 a) | Nie może powstać; stawka 0, zebrane stoi | Operacja wymaga budżetu w realizacji. | Kod |
| Zatwierdzony miesiąc bez pozycji kategorii (F9 b) | Stawka 0, dopóki nie dodasz jej w „Zmień plan” | Zatwierdzony plan zmienia tylko użytkownik, jak każdy wiersz „Poza planem”. | Plan |
| Miesiące sprzed oznaczenia kategorii utworzonej jako nieregularna (F9 c) | Nieregularna z zebranym 0, poza łańcuchem | Jedna reguła odczytu rytmu, a kwota w „do wydania” jest ta sama. | Plan |
| Podpis „zebrane” w wierszu planu | Jedna liczba: stan po miesiącu, w przyszłym miesiącu prognoza | Najkrótszy podpis, zgodny ze szkicem PRD, pomaga przy planowaniu. | Plan |
| Ostrzeżenie przy wpisie i w Imporcie | Miesiąc daty i późniejsze miesiące z planem; wiersze Importu łączą się w łańcuch | Ostrzega zawsze, gdy wpis zepchnie któryś miesiąc pod zero. | Plan |
| Przejście przez 31 grudnia | Łańcuch ciągły już w S-26; S-27 tylko pokazuje „zebrane startowe” w nowym budżecie | Poprawny styczeń 2027 niezależnie od terminu S-27. | Plan |
| Miejsce zebranego startowego | Kolumna na wpisie historii rytmu, zapis tylko przez `ChangeCategoryRhythm` | Miesiąc oznaczenia to start wpisu, więc nie trzeba drugiej tabeli. | Plan |
| Wycofanie migracji | `down()` zamienia nieregularną na równomierną roczną i usuwa kolumnę | Odwracalne bez utraty operacji, strata nazwana w ADR (wzorzec S-24). | Plan |
| Snapshot | v10 z kwotą na wpisie; v9 wczytuje się bez niej | FR-022 i reguła „każda wersja nadal się wczytuje”. | Plan |

## Scope

**In scope:**
- ADR-0073 i słownik, kolumna `opening_collected_minor_units`, wartość `Irregular`
- Kalkulator łańcucha (para PHP/TS) i odczyt zebranego przez miesiące i lata
- Okno „Kategorii”: czwarta opcja rytmu i pole „Zebrane startowe”, korekta na istniejącym wpisie
- Plan miesiąca: stawka w propozycji, „zebrane”, nadwyżka, „do wydania”, wyjątek od podnoszenia planu do wydatków
- Dashboard („Ponad plan” z nadwyżką, brak kreski roku), ostrzeżenia przy wpisie i w Imporcie z łańcuchem
- Snapshot v10, restore v9, E2E historii US-02, reguły i roadmapa

**Out of scope:**
- „Zebrane startowe” na ekranie nowego budżetu (S-27), zebrane przy scalaniu (S-21), grupy w planie (S-25)
- Zebrane przy saldach, na Dashboardzie, na liście „Kategorii” i w karcie Podsumowania miesiąca
- Kolumna „Stawka”, widok historii rytmu, zmiana danych demo, blokowanie zapisu

## Architecture / Approach

`CategoryRhythms` zaczyna znać okres nieregularny i jego zebrane startowe. `IrregularCollected` ładuje stałą liczbą zapytań budżety, pozycje, kwoty zatwierdzonych planów i wykonanie po miesiącach tylko dla kategorii nieregularnych i odpowiada „zebrane na początek miesiąca”. `CollectedCalculator` (PHP i TS) liczy koniec miesiąca, nadwyżkę i łańcuch. `MonthToSpend::rowAmounts` podaje zebrane z początku miesiąca do `ToSpendCalculator` i `MonthItemDeviation`, więc plan miesiąca, Dashboard i powiadomienia czytają jedną liczbę. Ostrzeżenia dostają okresy w `EntryToSpendInputs` i przeliczają łańcuch w przeglądarce.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0073, słownik, kolumna i odczyt łańcucha | Decyzje, migracja z `down()`, kalkulator i odczyt zebranego | Reguła stawki w łańcuchu (szkic, archiwum, brak pozycji) |
| 2. „Kategorie” i snapshot v10 | Oznaczanie, zebrane startowe, korekta, backup i restore | Cztery opcje w przełączniku na 390 px; warunek wersji w restore |
| 3. Plan miesiąca | Stawka, „zebrane”, nadwyżka, „do wydania”, powiadomienie | Pary PHP/TS rozjechane; wiersz i suma z różnych liczb |
| 4. Dashboard, Budżet i ostrzeżenia | Brak kreski roku, łańcuch w ostrzeżeniach wpisu i Importu | Limit 2 s strony Operacji; ostrzeżenie o innym miesiącu niż data |
| 5. E2E, dokumenty i bramka | Test US-02 w przeglądarce, reguły, roadmapa, pełna bramka | Niestabilne dane E2E |

**Prerequisites:** F-05 i S-24 (done); stos Sail z PostgreSQL dla testów worktree; `composer install` w worktree (hook zgłosił niepowodzenie przy tworzeniu).
**Estimated effort:** ~5 sesji, jedna na fazę; faza 3 największa.

## Open Risks & Assumptions

- Między fazą 2 a 3 kategorię da się oznaczyć, a odczyty liczą ją jeszcze jak bieżącą; to stan tylko na gałęzi roboczej.
- Stawka miesiąca, w którym kategoria została oznaczona, to kwota z już zatwierdzonego planu (ADR-0066), więc w listopadzie „Rekolekcje” mogą mieć stawkę 0, dopóki jej nie poprawisz w „Zmień plan”.
- Późniejsza zmiana planu rocznego zmienia stawki minionych niezatwierdzonych miesięcy i przez łańcuch także „do wydania” zatwierdzonych (ADR-0066); plan tego nie łagodzi.
- Po `down()` giną zebrane startowe i oznaczenia; snapshot v10 nie wczyta się w kodzie sprzed zmiany.
- Kwoty z danych dev (zatwierdzony tylko listopad 2026, „Rekolekcje” 2 380 zł) pochodzą z planu S-24 i mogły się zmienić.
- Wdrożenie S-26 czeka na próbę S-24 na kopii produkcji, która jest nadal otwarta.

## Success Criteria (Summary)

- Historia US-02 przechodzi w aplikacji i w testach co do grosza: rachunek 400 obniża „do wydania” o 150, rachunek 600 o 300 z wierszem „ponad plan”.
- Rachunek pokryty zebranym nie daje ostrzeżenia ani powiadomienia, a salda, Wynik miesiąca i raporty liczą faktyczny rachunek.
- Liczby kontrolne przed i po migracji są identyczne, a `user:backup` / `user:restore` przenosi zebrane startowe i miesiąc oznaczenia.
