# Rok do roku w Raportach — Plan Brief

> Full plan: `context/changes/year-over-year-report/plan.md`

## What & Why

Strona „Raporty” pokazuje dziś tylko jeden rok, więc pytanie „czy wydaję więcej niż rok temu” wymaga dwóch kart i liczenia w głowie. Ten wycinek (S-29, FR-016) dodaje przy każdym wierszu, sumie, kafelku i na wykresie kwotę z tego samego okresu rok wcześniej oraz różnicę, łącząc lata po kategorii listy, nie po nazwie. Na produkcji drugi rok pojawi się w 2027, więc powstaje też polecenie, które na devie buduje mock roku poprzedniego (potrzebny również S-30…S-32).

## Starting Point

`PeriodReport` liczy raport jednego budżetu w stałej liczbie zapytań i oddaje wiersze z id kategorii listy. Front to tabela na siatce `.report-grid` (Kategoria | Kwota | Udział) bez progów szerokości, panel trzech miar i wykres z jedną serią. Nie ma komponentu „różnica”, mocka 2025 ani drugiego roku w żadnej bazie. Bez roku w sesji aplikacja otwiera najstarszy rok z budżetem.

## Desired End State

Gdy użytkownik ma budżet roku `N − 1` w wykonaniu, raport roku albo miesiąca `N` pokazuje kolumny `N` | `N − 1` | Różnica (i Udział dla wydatków), kafelki z kwotą roku wcześniej i różnicą, dwa słupki na wiersz wykresu z legendą oraz, dla okresu w toku, notkę z zakresem porównania. Bez takiego budżetu strona wygląda jak dziś. Na devie `demo:seed-previous-year` dokłada rok poprzedni i usuwa go przez `--remove`, a po zalogowaniu otwiera się bieżący rok.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Okres w toku | Rok wcześniej ucięty na tym samym dniu (11 lis 2026 → 1 sty–11 lis 2025; listopad → 1–11 lis 2025) | Cały 2025 dawałby pozorną oszczędność (w przykładzie 19 552 zł) aż do grudnia |
| Wiersze | Kategoria z operacją w bieżącym okresie albo rok wcześniej; brak to „—”, różnica liczy go jako 0 | „Razem” roku wcześniej zgadza się z raportem tego roku i widać, na co przestało się wydawać |
| Różnica | Sama kwota ze znakiem (`bieżący − rok wcześniej`), bez procentu | Procent przy małych kwotach myli i zabiera kolumnę na telefonie |
| Kolor różnicy | Zielony przy zmianie korzystnej (mniej wydatków, więcej przychodów, wyższy wynik), czerwony przy niekorzystnej; zero i archiwum bez koloru | Ta sama konwencja co strzałka zmiany planu (ADR-0060) |
| Układ tabeli | Porównanie zawsze widoczne z Udziałem; na wąskim ekranie druga linia wiersza „2025: kwota … różnica” | Wszystkie liczby na każdej szerokości, wzorzec jak w Budżecie i na Dashboardzie |
| Wykres | Dwa słupki na wiersz: bieżący okres w kolorze typu, rok wcześniej w stonowanym szarym (`--chart-2`), z legendą | Widać skalę obu lat, a kolor nadal znaczy typ |
| Panel miar | Pod każdą kwotą „rok: kwota” i różnica | Jedyne miejsce z porównaniem Wyniku |
| Mock roku poprzedniego | Polecenie artisan (tylko `local`) kopiuje operacje bieżącego roku o rok wstecz ze stałym procentem 85–115 na kategorię, bez zmiany sald, z `--remove` | Działa na danych z Importu i na demo, zawsze na tych samych kategoriach listy |
| Domyślny rok | Bieżący rok kalendarzowy, potem najnowszy nie późniejszy niż dziś, potem najstarszy (ADR-0075) | Z dwoma latami świeże logowanie otwierałoby stary rok, na devie od razu, na produkcji w 2027 |
| Z czym porównujemy | Dokładnie budżet `N − 1` w wykonaniu; inaczej brak porównania | PRD mówi „rok wcześniej”; tak samo liczy propozycja planu stycznia |
| Bieżąca kolumna | Nie jest cięta na dziś | Zostaje równa kafelkom i wykonaniu w Budżecie |
| Notka o zakresie | Tylko dla okresu w toku, pod panelem miar | Dla okresu zakończonego wystarcza rok w nagłówku kolumny |
| Usuwanie mocka | Operacje mocka mają znacznik; `--remove` odmawia dla budżetu z operacją bez niego | Bez znacznika usuwanie w 2027 skasowałoby prawdziwy rok 2026 |

## Scope

**In scope:**
- słownik (Period Report, Budget), ADR-0074 (porównanie) i ADR-0075 (domyślny rok);
- odczyt po zakresie dat, `ReportComparisonPeriod`, porównanie w `PeriodReport`, propsy i typy;
- polecenie `demo:seed-previous-year` z seederem, wpis w README;
- nowa reguła w `SelectedBudgetYear`;
- tabela z drugą linią, ton różnicy, kafelki, notka, teksty;
- token `--chart-2`, druga seria wykresu, legenda, sekcja „Wykresy” w design systemie;
- kitchen sink, `.ai/rules/reports.md`, testy (SQLite, PostgreSQL, `nfr`, Vitest, E2E).

**Out of scope:**
- procent zmiany, przełącznik porównania, wykres różnic;
- trend (S-30), przejście do Operacji (S-31), ostatnie 12 miesięcy (S-32);
- import arkusza 2025 i prawdziwe kwoty 2025;
- mock w `DatabaseSeeder` i `demo:seed-household`; transfery, ruchy kapitału i plany miesięcy w mocku;
- zmiany schematu, snapshotu i `ui/chart/**`.

## Architecture / Approach

Kontroler wyznacza przez `ReportComparisonPeriod::resolve` budżet roku wcześniej i zakres dat (z cięciem na `dziś − 1 rok` dla okresu w toku) i przekazuje go do `PeriodReport`. Raport czyta bieżący okres jak dotąd, a rok wcześniej nowym `MonthCategoryActuals::forRange`, łączy wiersze po id kategorii listy i dodaje do każdego `has_operations`, `previous_minor_units` i `difference_minor_units` oraz blok `comparison` z sumami. Wszystko w minor units na serwerze; klient formatuje, koloruje (`reportDifferenceTone` + istniejące `planChangeToneClass`) i rysuje. Mock wpisuje operacje wprost do tabeli i sam ustawia licznik wykonania, więc nie dotyka kont.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Reguły i odczyt porównania na serwerze | Słownik, ADR-0074, odczyt zakresu, porównanie w propsach, testy równości i 2 s | Rozjazd kwot roku wcześniej z raportem tego roku; czas przy dwóch latach po 10 000 operacji |
| 2. Mock roku poprzedniego i domyślny rok | Polecenie z `--remove`, README, ADR-0075, nowa reguła roku | Mock niespójny z licznikiem wykonania albo usuwający prawdziwe dane |
| 3. Tabela i panel miar | Kolumny, druga linia na telefonie, kolor różnicy, kafelki, notka, kitchen sink | Pięć kolumn obok sidebara; nowe znaczenie koloru wbrew regule „destructive to przekroczenie” |
| 4. Wykres i E2E | `--chart-2`, dwie serie, legenda, test E2E, poprawka dwóch testów | Kontrast i czytelność drugiej serii; baza E2E wymaga mocka |

**Prerequisites:** worktree `worktree-year-over-year-report` na `development`; `composer install` (hook w worktree się nie powiódł), `.env`, `npm ci`, `npm run build`; Sail z PostgreSQL (sieć `household-treasurer_sail`); dla fazy 4 mock w bazie `household_treasurer_e2e`.
**Estimated effort:** ~4 sesje w 4 fazach.

## Open Risks & Assumptions

- Rachunek zapłacony w innym dniu miesiąca niż rok temu pokaże się przejściowo jako różnica (świadomy koszt cięcia po dniu).
- Każdy wzrost wydatku nieregularnego świeci na czerwono, także sezonowy (świadomy koszt koloru według korzyści).
- Mock zostawia w 2025 operacje sprzed daty otwarcia kont; edycja daty otwarcia konta na devie może przez to dać błąd walidacji do czasu `--remove`.
- Budżet 2025 z mocka zasila propozycję planu stycznia 2026, jeśli ten plan jeszcze nie istnieje (na devie już jest).
- Numery ADR-0073 i 0074 może zająć równoległa zmiana `irregular-expense-collected`; sprawdzić przed zapisem. (S-26 zajęła 0073, więc S-29 ma 0074 i 0075.)
- Zakładamy, że próg szerokości dla pięciu kolumn da się dobrać tak, by okno 1280 px miało jedną linię; jeśli nie, układ dwuliniowy obejmie też to okno.

## Success Criteria (Summary)

- Użytkownik z dwoma latami widzi w raporcie roku i miesiąca kwotę roku wcześniej i różnicę w tabeli, kafelkach i na wykresie; bez drugiego roku strona jest jak dziś.
- Kwoty roku wcześniej są co do grosza równe raportowi tamtego roku za ten sam zakres, a suma grupy równa się sumie jej wierszy w obu kolumnach.
- `composer ci:check`, testy PostgreSQL, `nfr` (≤ 2 s z dwoma latami), E2E i `sloppy ci` przechodzą.
