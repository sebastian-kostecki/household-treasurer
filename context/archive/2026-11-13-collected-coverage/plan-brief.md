# Pokrycie „zebranych” płynnością — Plan Brief

> Full plan: `context/changes/collected-coverage/plan.md`

## What & Why

„Zebrane” kategorii nieregularnych to liczba z planu: nie rusza sald i nic dziś nie sprawdza, czy te pieniądze naprawdę leżą na rachunkach. Zmiana dodaje odczyt „pokrycie zebranych” (Collected Coverage): suma „zebranych” wobec płynności dysponowalnej, widoczna stale na pulpicie, z ostrzeżeniem przy niedoborze. To sedno metody YNAB (planuj tylko pieniądze, które masz) podane jako odczyt, bez zmiany modelu aplikacji.

## Starting Point

„Zebrane” liczy `IrregularCollected` i pokazuje je tylko wiersz planu miesiąca („zebrane X”); pulpit zna płynność dysponowalną, ale nie zna sumy „zebranych”. Powiadomienia pulpitu mają już dwa rodzaje wagi `warning` i stały kształt karty.

## Desired End State

Pod „Płynność dysponowalna” na pulpicie stoi „konta, bez lokat · w tym zebrane 600,00 zł”, gdy budżet bieżącego roku jest w realizacji i ma kategorię nieregularną. Gdy płynność jest niższa od tej sumy, w „Wymaga uwagi” pojawia się karta „Zebrane bez pokrycia” z tekstem „brakuje 100,00 zł · zebrane 600,00 zł”, prowadząca do planu miesiąca. Suma zgadza się co do grosza z sumą „zebrane” w wierszach planu.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| Z czym porównać sumę | Płynność dysponowalna (rachunki i gotówka, bez lokat) | Tak samo działa już ostrzeżenie przy „dobieram”; lokata nie zapłaci rachunku w dniu płatności. |
| Waga powiadomienia | `warning` | Nic nie jest jeszcze przekroczone: rachunek nie przyszedł. |
| Która suma „zebranych” | Jak w wierszach planu: stan na dziś ze stawką bieżącego miesiąca | Liczba z pulpitu zgadza się z planem miesiąca i nie powstaje drugi wzór. |
| Widoczność | Karta przy niedoborze i stały podpis w panelu liczb | Sumę widać codziennie, nie dopiero gdy zabraknie. |
| Które kategorie | Niezarchiwizowane pozycje wydatkowe budżetu bieżącego roku o rytmie „nieregularna” w tym miesiącu | Tylko te mają wiersz, w którym niedobór da się prześledzić. |
| Próg | Karta, gdy płynność < suma; przy równości nie | Najprostsza reguła bez tolerancji do utrzymania. |
| Kopia w TypeScript | Brak | Nic nie wymaga podglądu przed zapisem; liczy tylko serwer. |

## Scope

**In scope:**

- Hasło w słowniku i ADR-0090, dopiski w ADR-0032, ADR-0050, `support.md`, `design-system.md`
- Odczyt `App\Support\CollectedCoverage`
- Rodzaj powiadomienia `collected_not_covered` po stronie serwera i frontendu
- Prop `collected_minor_units` i podpis w panelu liczb pulpitu
- Stany w `/dev/ui/dashboard`, changelog

**Out of scope:**

- Porównanie z łącznymi środkami i dwa progi
- Blokady, przelewy, rezerwa, jakikolwiek zapis
- Odczyt poza pulpitem, ostrzeżenia przy wpisie operacji i w Imporcie
- Kategorie zarchiwizowane i budżet poza realizacją

## Architecture / Approach

Kontroler pulpitu ma już `MonthToSpend` bieżącego miesiąca i niezarchiwizowane pozycje wydatkowe. Nowa klasa `CollectedCoverage` sumuje dla pozycji nieregularnych ten sam wzór, którego używa wiersz planu (`rowAmounts` + `CollectedCalculator::collectedAtEndMinorUnits`), i liczy niedobór wobec płynności. Kontroler przekazuje jeden wynik do `DashboardNotices` (karta) i do propa (podpis), więc obie liczby nie mogą się rozjechać. Bez migracji i bez nowych zapytań.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. Słownik i ADR-0090 | Termin, próg i zakres kategorii zapisane przed kodem | Reguła w ADR inna, niż użytkownik miał na myśli; stąd potwierdzenie przed fazą 2 |
| 2. Odczyt | `CollectedCoverage` z czerwonym testem i dowodem równości z planem miesiąca | Pozycja nieregularna bez pozycji w zatwierdzonym planie liczona inaczej niż w wierszu |
| 3. Karta ostrzeżenia | Rodzaj `collected_not_covered` na pulpicie i w kitchen sinku | Testy z twardą liczbą powiadomień i brak testu na dynamiczny klucz etykiety |
| 4. Podpis i bramka | „w tym zebrane X” pod płynnością, pełna bramka | Długi podpis na wąskim ekranie |

**Prerequisites:** worktree `collected-coverage` oparty na `development` (jest), baza `testing_collected_coverage` dla testów na PostgreSQL.
**Estimated effort:** około 2 sesji, 4 fazy.

## Open Risks & Assumptions

- Na początku miesiąca, przed wypłatą, karta może ostrzec o braku w wysokości stawek tego miesiąca. To znany koszt wybranego odczytu.
- Na bazie dev nie ma dziś żadnej kategorii nieregularnej, więc ręczna weryfikacja wymaga oznaczenia jednej albo danych z fabryk.
- Kategoria nieregularna bez pozycji w budżecie bieżącego roku nie wchodzi do sumy, choć jej „zebrane” stoi.

## Success Criteria (Summary)

- Na pulpicie widać, ile płynności jest już „zajęte” przez zebrane, a liczba równa się sumie z planu miesiąca.
- Gdy na rachunkach jest mniej niż zebrano, pulpit to mówi i podaje brakującą kwotę.
- Bez kategorii nieregularnej pulpit wygląda jak dziś.
