# Trend w miesiącach roku w Raportach — Plan Brief

> Full plan: `context/changes/report-trend-by-month/plan.md`

## What & Why

Strona „Raporty” pokazuje sumę roku albo jeden miesiąc, więc pytanie „jak szedł rok miesiąc po miesiącu” wymaga dziś przeklikania 12 miesięcy albo arkusza („Analiza”: miesiące × grupy). Ten wycinek (S-30, FR-017, nice-to-have) dodaje widok „Trend”: rok rozpisany na 12 miesięcy, z przychodami, wydatkami i wynikiem każdego miesiąca oraz kwotami grup i kategorii, domyślnie po grupach.

## Starting Point

`PeriodReport::forYear` już czyta cały rok jednym zapytaniem z kwotami per miesiąc i linia budżetu, po czym sumuje miesiące. Strona ma przełącznik `Rok | Miesiąc`, tabele na siatce `.report-grid` i poziome słupki. Nie ma w aplikacji tabeli z 12 kolumnami kwot, przewijania liczb w poziomie ani formatu kwoty bez groszy.

## Desired End State

Przełącznik ma trzy segmenty: `Rok | Miesiąc | Trend`. Widok „Trend” (`/reports?view=trend`) pokazuje kafelki roku, kartę „Miesiące” z wykresem (dwa pionowe słupki na miesiąc) i tabelą miar Przychody / Wydatki / Wynik oraz tabele Wydatki i Przychody z grupami rozwijanymi do kategorii, wszystko w 12 kolumnach miesięcy i kolumnie „Razem”. Kwoty są w pełnych złotych i mieszczą się na laptopie; na wąskim ekranie tabela przewija się w poziomie z przypiętą nazwą. Zmiana roku w sidebarze zostawia użytkownika w trendzie.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Wejście w trend | Trzeci segment przełącznika, adres `?view=trend` | PRD mówi „przełącza na trend”, strona zostaje krótka, a trend jest liczony tylko na żądanie |
| Dokładność kwot | Pełne złote w całym trendzie, stopka „zaokrąglone do pełnych złotych” | 12 miesięcy naraz już na laptopie; dokładne kwoty zostają w widokach Rok i Miesiąc |
| Zaokrąglenie | Każda komórka zaokrągla swoją dokładną kwotę, połowa od zera; „Razem” zaokrągla dokładną sumę | Każda liczba jest najbliższa prawdzie, kosztem tego, że komórki nie zawsze sumują się na oko do „Razem” |
| Gdy tabela się nie mieści | Przewijanie w poziomie wewnątrz karty, kolumna nazwy przypięta | 12 kwot ułożonych w linie pod nazwą byłoby nieczytelne |
| Wykres | Jeden: dwa pionowe słupki na miesiąc (Przychody `success`, Wydatki `chart-1`) nad tabelą miar | Widać miesiące na plusie i na minusie na wspólnej skali, bez nowych kolorów |
| Miesiąc w toku | Podpis „w toku” pod nazwą miesiąca w tabelach; na wykresie notka w wierszu legendy | Niepełny miesiąc nie wygląda jak spadek; oś z 12 etykietami na telefonie nie mieści drugiej linii |
| Komórka bez operacji | „—”; „0” tylko gdy operacje były i netto wyszło zero; zawsze 12 kolumn | Ta sama reguła co w rok do roku (ADR-0074), układ nie zmienia się w ciągu roku |
| Kolor zmian miesiąc do miesiąca | Brak; kolor ma tylko wiersz Wynik (plus zielony, minus czerwony) | Rachunek nieregularny co kwartał świeciłby jak alarm, przed czym ostrzega PRD |
| Grupy | Startują zwinięte, rozwijają się do kategorii | PRD: „domyślnie po grupach, rozwinięcie na życzenie” |
| Kafelki w trendzie | Trzy kwoty roku i „okres w toku”, bez linii „rok wcześniej” | Porównanie jest w widokach Rok i Miesiąc; trend nie liczy drugiego roku |
| Dodatki | Żaden: bez linku z miesiąca, średniej miesięcznej i roku wcześniej w trendzie | Decyzja użytkownika dla funkcji nice-to-have |
| Źródło kwot | Nowy odczyt `ReportTrend` na tym samym `MonthCategoryActuals::forMonths` i wspólnym drzewie kategorii | Równość z raportem roku wynika z konstrukcji, bez nowego SQL-a i zapytań w pętli |
| Zmiana roku | Nowy cel `reports-trend` w przełączniku roku | Dziś zmiana roku gubi parametry adresu Raportów |

## Scope

**In scope:**
- słownik (akapit o trendzie we wpisie Period Report) i ADR-0076;
- `ReportTrend`, wspólne `ReportCategoryTree`, parametr `?view=trend`, prop `trend` i typy;
- cel `reports-trend` przy zmianie roku;
- `formatWholeMoneyAmount` / `formatWholeMoney`;
- siatka trendu z przewijaniem i przypiętą nazwą, komponenty `ReportTrend*`, trzeci segment, teksty;
- wykres miesięcy na istniejącej bibliotece i tokenach;
- kitchen sink, strażnicy UI, `.ai/rules/reports.md`, `.ai/rules/js.md`, design system;
- testy: odczyt (SQLite, PostgreSQL), strona, zmiana roku, `nfr`, Vitest, E2E.

**Out of scope:**
- link z nagłówka miesiąca, średnia miesięczna, rok wcześniej w trendzie;
- wykres wybranego wiersza, linie na grupę, kolory zmian, wyszarzenie miesiąca w toku;
- grosze w trendzie i zmiana dokładności w innych widokach;
- „ostatnie 12 miesięcy” (S-32), przejście do Operacji (S-31);
- nowe tokeny, edycja `ui/chart/**`, zmiany schematu i `PeriodReport::toArray()`.

## Architecture / Approach

Kontroler przy `?view=trend` woła `ReportTrend::forYear($budget, $miesiącWToku)` zamiast `PeriodReport` i oddaje wynik jako prop `trend` (`report` jest wtedy `null`). Odczyt bierze te same trzy źródła co raport roku (kwoty po dniu złożone w miesiące, linie z kategorią listy, grupy), układa wiersze przez wspólne `ReportCategoryTree` i oddaje dla każdego wiersza 12 komórek `int|null` oraz sumę roku, plus 12 miar miesięcy. Wszystko w minor units; klient zaokrągla do wyświetlenia jedną funkcją i rysuje tabele na wspólnym wierszu `ReportTrendRow` oraz wykres z tych samych `months`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Reguły i odczyt trendu na serwerze | Słownik, ADR-0076, `ReportCategoryTree`, `ReportTrend`, testy równości na SQLite i PostgreSQL | Wydzielenie drzewa zmienia wynik `PeriodReport` (pilnują istniejące testy) |
| 2. Widok „Trend” z tabelami | `?view=trend`, propsy, zmiana roku, pełne złote, przewijana tabela, tabela miar i tabele grup, kitchen sink, reguły, test 2 s | 14 kolumn nie mieści się przy 1280 px mimo wyliczeń; przypięta kolumna z prześwitującym tłem |
| 3. Wykres i E2E | Pionowe słupki miesięcy z legendą, design system, E2E, pełna bramka | Czytelność 24 słupków i etykiet osi na telefonie |

**Prerequisites:** worktree `worktree-report-trend-by-month` na `development` (S-28 i S-29 są w nim); `composer install` (hook w worktree się nie powiódł), `.env`, `npm ci`, `npm run build`; Sail z PostgreSQL (sieć `household-treasurer_sail`); dla ręcznej weryfikacji roku zakończonego mock `demo:seed-previous-year` na devie.
**Estimated effort:** ~3 sesje w 3 fazach.

## Open Risks & Assumptions

- Szerokości kolumn są wyliczone, nie zmierzone (ok. 960 px na 14 kolumn w pełnych złotych przy karcie ok. 990 px). Jeśli przy 1280 px tabela jednak przewija, pierwszym krokiem jest zmniejszenie odstępów i minimum kolumny nazwy; faza 2 ma na to punkt ręcznej weryfikacji.
- Zaokrąglone komórki mogą różnić się od „Razem” o kilka złotych (trzy miesiące po 100,40 zł to 100, 100, 100 i „Razem” 301). To świadomy koszt; stopka o tym mówi.
- Niepełny miesiąc w toku ma niższy słupek mimo notki w legendzie.
- Wyjątek od zasady „zawsze dwa miejsca” jest ograniczony do trendu; reguły i design system muszą to mówić wprost, żeby nie rozlał się na inne tabele.
- Zakładamy, że ADR-0076 jest wolny; sprawdzić przed zapisem.
- Tabela trendu dostaje role ARIA tabeli, których nie mają pozostałe tabele Raportów; to celowa różnica, bo wykres jest ukryty dla czytników.

## Success Criteria (Summary)

- Użytkownik przełącza „Raporty” na „Trend” i widzi przychody, wydatki i wynik każdego miesiąca oraz grupy rozwijane do kategorii w 12 kolumnach, jako wykres i tabele.
- Suma miesięcy każdej kategorii jest równa jej kwocie w raporcie roku, a wynik każdego miesiąca Wynikowi tego miesiąca, co do grosza (testy na PostgreSQL).
- `composer ci:check`, `npm run check:ui`, testy `nfr` (≤ 2 s), E2E i `sloppy ci` przechodzą.
