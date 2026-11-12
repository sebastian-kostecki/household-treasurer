---
date: 2026-11-12T14:10:00+01:00
researcher: Claude Opus 5.5 (sesja użytkownika Sebastian Kostecki)
git_commit: 2b50fbed55a557274092ff69f90a808c216d98b4
branch: worktree-reports-view
repository: household-treasurer
topic: "Widok Raporty (Reports.vue, Report*, /dev/ui/reports): dwukierunkowy audyt 10x-ui i weryfikacja czterech zarzutów z pre-audytu na zrzutach 1440 px i 22rem"
tags: [research, codebase, ui, reports, kitchen-sink, charts, atrament, 10x-ui]
status: complete
last_updated: 2026-11-12
last_updated_by: Claude Opus 5.5 (sesja użytkownika Sebastian Kostecki)
last_updated_note: "Dopisane decyzje użytkownika do C1–C5 (sekcja Decisions); Open Questions rozstrzygnięte"
---

# Research: widok Raporty, audyt dwukierunkowy

**Date**: 2026-11-12T14:10:00+01:00
**Researcher**: Claude Opus 5.5 (sesja użytkownika Sebastian Kostecki)
**Git Commit**: 2b50fbed55a557274092ff69f90a808c216d98b4 (working tree: tylko ten plik i `screenshots/` w `context/changes/reports-view/`)
**Branch**: worktree-reports-view
**Repository**: household-treasurer

## Research Question

Czy cztery zarzuty z pre-audytu w `change.md` są prawdziwe w kodzie i na zrzutach (desktop 1440 px i 22rem = 352 px)?

1. `Reports.vue:125-130`: stany no-budget i draft bez `h1` i przełącznika, „12 miesięcy” nieosiągalne w szkicu.
2. `Reports.vue:18`, `ReportBarChart.vue:76-87`, `ReportTrendSummary.vue:8`: asynchroniczny wykres bez placeholdera, przeskok layoutu, sink błędnie oznacza loading jako N/A.
3. `ReportsKitchenSink.vue:328-460`: kopia kompozycji `Reports.vue:196-272`, która już dryfuje (brak `range`, tytuł).
4. `ReportBarChart.vue:76-80`: stałe rozmiarów poza `check:ui`.

Wynik trafia do `## Charges` jako wejście dla `/10x-plan`.

## Summary

- **C1 potwierdzony.** Przy obu stanach strona nie ma żadnego `h1` i żadnego przełącznika okresu. To samo widać w 1440 px i 352 px, a w szkicu tak samo dla `/reports`, `?month=3` i `?view=trend`. Pomiar DOM jest w `screenshots/audit-measurements.json`.
  - W szkicu „12 miesięcy” otwiera się tylko z wpisanego URL.
  - Z tego widoku kliknięcie „Rok” prowadzi do stanu pustego bez przełącznika, więc droga jest w jedną stronę.
  - To ograniczenie przyjęto świadomie w S-32 (`context/archive/2026-11-12-rolling-twelve-month-report/plan.md:102`), więc dostęp z draftu to decyzja produktowa, a nie błąd.
- **C2 potwierdzony i zmierzony.** Fragment JS wykresu opóźniłem o 1,5 s. W tym czasie tabela „Wydatki” stoi wyżej, a po doładowaniu zjeżdża o 414 px (1440 px) i o 410 px (352 px). Tabela miar Trendu zjeżdża o 314 px.
  - Bez opóźnienia, na lokalnym buildzie, CLS strony roku wynosi 0,299 (1440 px) i 0,364 (352 px), a Trendu 0,132.
  - Uzasadnienie „loading N/A” w sinku (`ReportsKitchenSink.vue:76-79`) pomija ten fragment.
- **C3 potwierdzony częściowo.** Sink trzyma trzy własne kopie wiersza tytułu i ciała strony.
  - Dryf tytułu jest prawdziwy: 6 scenariuszy miesiąca ma w sinku tytuł „Raporty”, a strona pokazuje „Raport: marzec 2026”. Wszystkie 26 tytułów w sinku to `<p>`, nie `h1`.
  - Brak `range` w gałęzi rok/miesiąc nie zmienia niczego, co widzi użytkownik, bo strona przekazuje tam `null`.
- **C4 potwierdzony w kodzie, bez widocznej wady na zrzutach.** Oba strażniki (`check-ui-literals.mjs` i `ReportsHardcodedUiTest.php`) szukają tylko wartości w nawiasach `-[…]`, więc stałych w skrypcie nie widzą. `check:ui` kończy się kodem 0. Na zrzutach w obu szerokościach wykres jest czytelny.
- **Nowa obserwacja C5, spoza briefu.** W 352 px tabela wydatków ucina nazwy kategorii („Żywn…”, „Mies…”, „Trans…”). W scenariuszu `phone` sinka wiersz zarchiwizowany pokazuje sam Badge bez nazwy. Pełne nazwy widać wtedy tylko na wykresie, a wykres jest `aria-hidden`.

Dowody pochodzą z kodu, z dwóch równoległych przeglądów tylko do odczytu (strażniki i tokeny; historia i ścieżki serwera) oraz z podglądu worktree:
- serwer `php -S` w obrazie `sail-8.5/app` na porcie 8091, baza `preview_reports_view`, seed `DemoHouseholdSeeder`;
- w tej bazie dodałem Budżet 2027 w fazie `draft` dla `test@example.com` i użytkownika `nobudget@example.com` bez budżetu;
- zrzuty i pomiary zrobiłem w Playwright (Chromium);
- testów ani `composer ci:check` nie uruchamiałem;
- MCP Boost był niedostępny.

## Charges

Format: plik:linia, werdykt weryfikacji, dowód ze zrzutu, wpływ na użytkownika, kategoria 10x-ui i proponowana faza dla `/10x-plan`. Zrzuty leżą w `context/changes/reports-view/screenshots/`.

### C1. Stany „brak budżetu” i „budżet w przygotowaniu” bez tytułu strony i przełącznika; „12 miesięcy” w szkicu tylko z URL

**Werdykt: potwierdzony.** Brak dostępu z draftu to świadoma decyzja z S-32, która teraz wraca do ponownego rozważenia.

**Kod**
- `Reports.vue:125-130`: przy `budgetYear === null` i przy `budgetPhase !== 'execution' && period.kind !== 'last-12'` strona renderuje tylko `ReportEmptyState`. `h1.page-title` i `SegmentedControl` żyją w gałęzi `v-else` (`:132-150`).
- `ReportEmptyState.vue` renderuje `IndexEmptyState`, którego tytuł to `h2.section-title` (`IndexEmptyState.vue:13`). Oba rodzaje stanu mają ten sam przycisk „Przejdź do budżetu” (`ReportEmptyState.vue:21-31` teksty, `:34-42` przycisk).

**Serwer**
- W szkicu `?view=last-12` zwraca pełne okno i raport, bo widok jest rozstrzygany przed fazą (`ReportController.php:49-50`, `:75-92`; test `tests/Feature/ReportsPageTest.php:519-534`).
- Plain `/reports`, `?month=N` i `?view=trend` zwracają w szkicu `report: null` i `trend: null` (`ReportController.php:66-67`).
- Bez żadnego budżetu każda kwerenda, także `?view=last-12`, kończy się odpowiedzią „brak budżetu” (`ReportController.php:29-43`; test `ReportsPageTest.php:555-566`).

**Wejścia do last-12**
- Jedyna droga w UI to przełącznik na stronie (`Reports.vue:30-32`, `:144-149`).
- Sidebar, skróty i paleta poleceń prowadzą do `reports()` bez kwerendy (`resources/js/composables/useAppViews.ts:33`).
- Przełącznik roku zna tylko cele `reports` i `reports-trend` (`BudgetController.php:155-160`).
- Na Dashboardzie podglądu nie ma żadnego linku z `last-12` w `href`: pole `shell-last12-links` w `audit-measurements.json` jest puste.

**Zrzuty**
- `audit-draft-1440.png`, `audit-draft-352.png`, `audit-no-budget-1440.png`: tylko „Budżet w przygotowaniu” / „Brak budżetu”, opis i przycisk. W 352 px sidebar jest schowany, więc nic na ekranie nie mówi, że to Raporty; widać sam pasek „Domowy Skarbnik”.
- `audit-draft-last12-1440.png`: ten sam szkic 2027 pod `?view=last-12` pokazuje `h1` „Raport: ostatnie 12 miesięcy”, przełącznik z czterema segmentami i raport z notką pokrycia „10 z 12 miesięcy”.
- `audit-draft-last12-then-rok-1440.png`: po kliknięciu „Rok” jest `h1: []` i 0 przełączników, więc nie ma powrotu do „12 miesięcy” bez wpisania URL.

**Historia**
- `rolling-twelve-month-report/plan.md:102` wyłącza z zakresu „Dostęp do „12 miesięcy” ze stanu „Budżet w przygotowaniu”. Ten stan nie ma przełącznika okresu i tak zostaje.”
- ADR-0081 w wierszu 7 mówi, że segment „is always available when the User has any Budget”. Zdanie stoi w akapicie o częściowym pokryciu, więc nie przesądza o drafcie, ale z planem S-32 jest w napięciu.
- `MonthPlan.vue:572-599` stosuje ten sam wzorzec (stan pusty bez `h1`). `Dashboard.vue:101-117` i `Budget.vue:160-171` / `BudgetEmptyState.vue:14` trzymają `h1` nad stanem pustym.
- `design-system.md:95` każe stronom domenowym budować wiersz tytułu `h1` według `Budget.vue`.

**Wpływ:** w szkicu i bez budżetu użytkownik nie widzi, na jakiej stronie jest. Czytnik ekranu nie znajduje nagłówka pierwszego poziomu. Raport 12 miesięcy, który ma dane z poprzedniego roku w realizacji, jest ukryty, a z URL prowadzi w ślepy zaułek.

**Kategoria:** przypadkowa architektura (punkt wejścia: draft / brak danych / prosto z linku). **Faza:** stany (wiersz tytułu nad stanem pustym). Decyzja: Decisions 1.

### C2. Asynchroniczne wykresy bez zarezerwowanego miejsca: tabela zjeżdża po doładowaniu fragmentu; sink błędnie oznacza loading jako N/A

**Werdykt: potwierdzony i zmierzony.**

**Kod**
- `Reports.vue:18-20` (`ReportBarChart`), `ReportsKitchenSink.vue:18-20` (to samo) i `ReportTrendSummary.vue:8-10` (`ReportTrendChart`) używają gołego `defineAsyncComponent(() => import(...))`, bez `loadingComponent` i bez `delay`.
- `<Suspense>` nie występuje w `resources/js`.
- `ReportBarChart.vue:97` renderuje `ListSurface` dopiero po załadowaniu fragmentu, a jego wysokość to inline style z `:81-83`.
- W `components/ui/skeleton/Skeleton.vue` jest `Skeleton`, ale używają go tylko `CommandPalette.vue:148` i `ui/sidebar`. Żaden plik Raportów go nie używa.
- Według ADR-0071:18 i `.ai/rules/reports.md:20` import asynchroniczny ma trzymać Unovis poza innymi stronami. Żaden ADR ani archiwum nie mówi o placeholderze ani o przeskoku.

**Pomiar** (`audit-measurements.json`). Obserwowałem `PerformanceObserver('layout-shift')` przy pełnym wczytaniu strony; wizyt Inertia między okresami nie mierzyłem.

| Ścieżka, szerokość | Opóźnienie fragmentu | Góra tabeli przed → po | Przesunięcie | CLS |
| --- | --- | --- | --- | --- |
| `/reports`, 1440 px | 0 ms | 623 → 623 (wykres już był) | — | 0,299 |
| `/reports`, 1440 px | 1500 ms | 209 → 623 | 414 px | 0,292 |
| `/reports`, 352 px | 0 ms | 853 → 853 | — | 0,364 |
| `/reports`, 352 px | 1500 ms | 443 → 853 | 410 px | 0,332 |
| `?view=trend`, 1440 px (tabela miar) | 0 ms | 554 → 554 | — | 0,132 |
| `?view=trend`, 1440 px (tabela miar) | 1500 ms | 240 → 554 | 314 px | 0,132 |

Wiersze „0 ms” to build lokalny bez dławienia. Przesunięcie i tak następuje (główny wpis `layout-shift` 0,9–1,5 s po wejściu), tyle że zanim Playwright odczytał pozycję tabeli.

**Zrzuty:** `audit-chart-pending-1440.png` / `audit-chart-loaded-1440.png`, `audit-chart-pending-352.png` / `audit-chart-loaded-352.png`, `audit-trend-chart-pending-1440.png`. Przed doładowaniem tabela „Wydatki” stoi zaraz pod panelem miar, a „Przychody” są widoczne. Po doładowaniu obie zjeżdżają o wysokość wykresu.

**Sink:** wiersz `loading` macierzy stanów brzmi „N/A: strona nie ma deferred props; zmiana okresu to zwykła wizyta Inertia z paskiem postępu.” (`ReportsKitchenSink.vue:76-79`). O deferred props to prawda, ale stan „fragment wykresu w drodze” istnieje, a sink nie może go pokazać, bo sam ładuje wykres tak samo.

**Ułatwienie dla planu:** liczba słupków jest znana synchronicznie. `reportChartBars` z `@/lib/report-chart` liczy je w `ReportBarChart.vue:35-37`, a pusty wynik oznacza „bez wykresu”, więc strona może zarezerwować dokładną wysokość przed nadejściem fragmentu. Wykres Trendu ma stałą wysokość `h-64` (`ReportTrendChart.vue:91`).

**Wpływ:** przy pierwszym wejściu na Raporty tabela, którą użytkownik zaczął czytać albo chce kliknąć (nazwy kategorii to linki), ucieka o 300–400 px. W 352 px przeskok zajmuje prawie pół ekranu.

**Kategoria:** brakujący współdzielony komponent (placeholder wykresu, `Skeleton`) plus stan `loading` w macierzy. **Faza:** stany. Wymaga wspólnej formuły wysokości z C4. Decyzja: Decisions 3.

### C3. Kitchen sink powiela kompozycję strony i już dryfuje

**Werdykt: duplikacja i dryf tytułu potwierdzone. Dryf „brak `range`” obalony jako widoczny dla użytkownika.**

**Duplikacja**
- Sink ma trzy własne kopie wiersza tytułu z przełącznikiem: `ReportsKitchenSink.vue:167-184` (last-12), `:257-269` (Trend) i `:329-348` (rok/miesiąc). Strona ma jedną (`Reports.vue:133-150`).
- Ciała gałęzi sinka (`:186-249`, `:271-325`, `:350-436`) odtwarzają `Reports.vue:152-272` ręcznie.

**Dryf tytułu (potwierdzony w DOM, `sink-titles` w `audit-measurements.json`)**
- Sink liczy tytuł jako `title_year` albo `t.reports.title` (`ReportsKitchenSink.vue:332-340`). Strona używa `title_month` z nazwą miesiąca (`Reports.vue:106-115`; `lang/pl/ui.php:388`).
- Sześć scenariuszy miesiąca (`month-closed`, `refund-negative-share`, `non-positive-expenses`, `no-operations`, `compared-month-closed`, `compared-empty-current`) ma w sinku tytuł „Raporty”. Strona pod `?month=3` ma „Raport: marzec 2026” (`audit-sink-month-closed-1440.png` wobec pola `month-desktop` w pomiarach).
- Wszystkie 26 scenariuszy z tytułem używa `<p class="page-title">` (`:171`, `:260`, `:332`); strona ma `h1` (`Reports.vue:135`).

**`range` (bez widocznego dryfu)**
- W gałęzi rok/miesiąc sink nie przekazuje `range` (`:386-435`). Strona przekazuje `range` (`Reports.vue:240`, `:262`), ale to `null` poza last-12 (`Reports.vue:71-79`).
- Etykiety linków nazw w sinku mają tę samą postać co na stronie, np. „Operacje kategorii Streaming, marzec 2026”, „Operacje kategorii Żywność, 2026”.
- W gałęzi last-12 sink przekazuje `range` z `reportWindowRange` (`:230`, `:246`) i daje tę samą etykietę co strona: „Operacje kategorii Żywność, listopad 2025 – październik 2026”.
- Zaszyty w sinku `:is-in-progress="false"` dla last-12 (`:206`) równa się wartości z serwera (`ReportController.php:86`), więc to też nie jest dryf.

**Luka scenariuszy:** strona przepuszcza last-12 przy drafcie (`Reports.vue:128`), a sink rozgałęzia się po `scenario.kind`, więc kombinacji „draft + last-12” i drogi w jedną stronę z C1 nie ma w sinku.

**Historia:** to trzeci raz, gdy duplikacja sinka wychodzi przy przeglądzie albo planie.
- `context/archive/2026-11-10-period-report-by-group/reviews/impl-review.md:126-134` (F8: „Kitchen sink odtwarza markup strony ręcznie”); naprawione wydzieleniem samego `ReportEmptyState`.
- `rolling-twelve-month-report/plan.md:56` i `:118`: `ReportCoverageNote` wydzielony, „żeby kitchen sink jej nie powielał”.
- Precedens pełnego rozwiązania: ADR-0056:37. `/dev/ui/auth` renderuje prawdziwy komponent strony z propsami z fixture.

**Wpływ:** sink to bramka wizualna tej zmiany (10x-ui). Gdy pokazuje inny tytuł i inne znaczniki niż strona, przegląd zrzutów zatwierdza coś, czego użytkownik nie widzi. Każda poprawka C1/C2 musiałaby trafić w dwa miejsca.

**Kategoria:** brakujący współdzielony komponent. **Faza:** architektura przed stanami (C1 i C2 lądują wtedy raz). Decyzja: Decisions 2.

### C4. Stałe rozmiarów wykresu słupkowego w skrypcie, poza obydwoma strażnikami

**Werdykt: potwierdzony w kodzie; na zrzutach bez widocznej wady.**

**Kod**
- `ReportBarChart.vue:75-79`: `ROW_HEIGHT_REM = 3`, `COMPARED_ROW_HEIGHT_REM = 4.5`, `AXIS_HEIGHT_REM = 2`, `NAME_WIDTH_PX = 110`, `VALUE_TICKS = 3`.
- `:81-83` składa z nich wysokość jako `` `${…}rem` ``. `:134` podaje szerokość nazw w px do Unovis.
- Wysokość rośnie liniowo z liczbą wierszy; szerokość nazw jest w px, a wiersze w rem.
- W `app.css` nie ma tokenu ani klasy na wysokość wykresu ani wiersza wykresu. Tokeny wykresów to tylko kolory: `--chart-1..5` (`:151-155`, `.dark` `:226-230`), `--color-chart-*` (`:70-74`). Najbliższe tokeny rozmiaru to `--row-height` i `--control-height` (`:157-175`).

**Strażniki**
- `scripts/check-ui-literals.mjs:176-184` łapie hex, funkcje kolorów, `-\[[0-9.]+(px|rem)\]`, `/[.N]`, klasy palety i `dark:`. Skanuje cały plik, także `<script>` (`:199-201`), a `ReportBarChart.vue` jest na liście (`:121-134`). Gołych liczb i szablonu `${}rem` nie łapie; uruchomiony skrypt kończy się kodem 0.
- `tests/Unit/ReportsHardcodedUiTest.php:24-36` też wymaga `-[` przed nawiasem. Jego wzorce zastosowane w `php -r` dają 0 trafień; samego Pest nie uruchamiałem.
- `h-64` w `ReportTrendChart.vue:91` to zwykła skala Tailwinda, nie literał.

**Zrzuty:** `audit-chart-loaded-1440.png`, `audit-year-352.png`, `audit-sink-month-closed-1440.png`. Nazwy z danych demo i fixture („Mieszkanie”, „Abonamenty”, „Wynagrodzenie”) mieszczą się w kolumnie 110 px w obu szerokościach; słupki i oś są czytelne. Długich nazw na wykresie nie sprawdzałem.

**Wpływ:** pośredni. Rozmiary wykresu żyją poza źródłem wartości i poza strażnikami. Placeholder z C2 musi znać tę samą formułę, więc dziś musiałby skopiować stałe.

**Kategoria:** brakujące tokeny. **Faza:** razem z C2 (formuła wysokości jako czysta funkcja w `@/lib/report-chart`, używana przez wykres i placeholder). Decyzja: Decisions 3.

### C5. (nowy, spoza briefu) Tabela okresu ucina nazwy kategorii w 22rem

**Werdykt: zaobserwowany na zrzutach;** tylko tabela z kolumną Udział.

**Zrzuty**
- `audit-year-352.png` (strona, viewport 352 px, treść 320 px): w tabeli „Wydatki” „Żywn…”, „Mies…”, „Trans…”, „Zdro…”, „Rozry…”. Wykres nad nią pokazuje te same nazwy w całości.
- Tabela „Przychody” (bez Udziału) pokazuje pełne „Pensja”, „Odsetki”.
- `audit-sink-phone-352.png` (scenariusz `phone`): „Ab…”, „St…”, „Żywn…”, a w wierszu zarchiwizowanym „Stara prenumerata” widać tylko Badge „zarchiwizowana”, bez nazwy.

**Kod:** `.report-grid` (`app.css:1462-1465`) to `minmax(0, 1fr) minmax(5.5rem, auto) 4.5rem` z `gap-x-4`. Nazwa dostaje resztę po kwocie, Udziale i chipie kategorii. Scenariusz `phone` sprawdza tylko brak poziomego przewijania (podpis w fixture).

**Wpływ:** według `.ai/rules/reports.md:20` tabela jest czytelną formą danych obok wykresu `aria-hidden`. Na telefonie nie mówi, która to kategoria, a wiersz zarchiwizowany traci nazwę całkiem. Linki zachowują pełną nazwę w `aria-label`, więc czytnik ekranu ją podaje; problem jest wizualny.

**Kategoria:** brakujące tokeny / układ (`.report-grid` w `app.css`). **Faza:** widok. Decyzja: przyjęty do tej zmiany (Decisions 4), choć wykracza poza cztery zarzuty z pre-audytu.

## Detailed Findings

### Źródło → widoki (co czytają pliki Raportów)

- Wszystkie pliki Raportów czytają klasy z `app.css` (`.report-grid*`, `.report-trend-*`, `.report-table`, `.report-name-link`, `page-title`, `section-title`, `meta`, `amount`/`amount-sum`, `table-header` w sinku) i komponenty współdzielone.
- Te komponenty to: `ListSurface` (w `ReportBarChart`, `ReportEntryTable`, `ReportTrendSummary`, `ReportTrendTable`), `IndexEmptyState`, `SegmentedControl`, `StatPanel` (przez `ReportStatPanel`), `BudgetMonthPicker`, `CategoryChip`, `Badge`, `Alert` i `ChartContainer` z `ui/chart`. Listę klas i importów zebrał przegląd plików `Report*.vue`, `Reports.vue` i sinka.
- Skan wartości zakodowanych na sztywno z 10x-ui (`check-ui-literals.mjs`) daje 0 trafień na 14 plikach z listy Raportów (`check-ui-literals.mjs:121-134`), zgodnie z pre-audytem.
- Rozmiar mają w `app.css` tylko kontrolki i wiersze (`--control-height*`, `--row-height`; `app.css:157-175`, utility `:329-362`). Wykresy dostają z tokenów tylko kolory; wysokość jest lokalna w każdym wykresie (`ReportBarChart.vue:81-83` inline, `ReportTrendChart.vue:91` `h-64`).
- `ChartContainer` ma domyślnie `aspect-video h-full w-full` (`ui/chart/ChartContainer.vue:40-42`). Oba wykresy nadpisują to `aspect-auto`, więc wysokość wyznacza opakowanie.

### Widok → źródło

- Literałów kolorów ani klas palety w widoku nie ma (wynik skanu powyżej).
- Wartości, które powinien pokryć token lub klasa, siedzą w skrypcie (C4) i w gridzie, który nie mieści nazwy w 22rem (C5).
- Brakujący komponent to placeholder wykresu (C2) i jedno źródło kompozycji strony dla sinka (C3).

### Reguły agenta

- `.ai/rules/reports.md:20` wymaga `defineAsyncComponent` i nic nie mówi o rezerwacji miejsca, więc kolejny wykres powtórzy przeskok.
- `.ai/rules/reports.md:28` każe dodawać każdy stan do `/dev/ui/reports`, a nie wymaga, by sink składał stronę tym samym komponentem.
- Żadna reguła nie zachęca do wartości jednorazowych.
- `/dev/ui/reports` nie figuruje na listach sinków w `design-system.md:104-127` ani `:177` ani w `.ai/rules/js.md` („Component sandboxes”).

## Code References

- `resources/js/pages/Reports.vue:18-20` — `ReportBarChart` przez goły `defineAsyncComponent`
- `resources/js/pages/Reports.vue:71-79` — `range` jest `null` poza last-12
- `resources/js/pages/Reports.vue:93-118` — tytuł strony (rok, miesiąc, last-12, Trend)
- `resources/js/pages/Reports.vue:125-150` — stany puste przed wierszem tytułu i przełącznikiem
- `resources/js/pages/Reports.vue:196-272` — ciało raportu okresu
- `resources/js/components/ReportBarChart.vue:35-37` — `reportChartBars`, liczba słupków znana synchronicznie
- `resources/js/components/ReportBarChart.vue:75-83` — stałe rozmiarów i inline wysokość
- `resources/js/components/ReportBarChart.vue:97` — `v-if="bars.length > 0"`, bez wykresu przy pustych słupkach
- `resources/js/components/ReportTrendSummary.vue:8-10`, `:39-42` — async `ReportTrendChart` w tym samym `ListSurface` co tabela miar
- `resources/js/components/ReportTrendChart.vue:91` — `h-64`
- `resources/js/components/ReportEmptyState.vue:21-42` — teksty i przycisk stanów pustych
- `resources/js/components/IndexEmptyState.vue:13` — tytuł jako `h2.section-title`
- `resources/js/pages/dev/ReportsKitchenSink.vue:55-96` — macierz stanów (`loading` w `:76-79`)
- `resources/js/pages/dev/ReportsKitchenSink.vue:167-184`, `:257-269`, `:329-348` — trzy kopie wiersza tytułu
- `resources/js/pages/dev/ReportsKitchenSink.vue:332-340` — tytuł rok/miesiąc bez nazwy miesiąca
- `app/Http/Controllers/ReportController.php:29-43`, `:49-50`, `:66-67`, `:75-92` — brak budżetu, last-12 przed fazą, `report: null` w drafcie, odpowiedź okna
- `app/Http/Controllers/BudgetController.php:149-160` — cele przełącznika roku
- `resources/js/composables/useAppViews.ts:33` — nawigacja do Raportów bez kwerendy
- `resources/css/app.css:1462-1475` — `.report-grid`, `.report-grid-no-share`, `.report-phone-frame` (22rem)
- `scripts/check-ui-literals.mjs:121-134`, `:176-184`, `:199-201` — lista plików, wzorce, skan całego pliku
- `tests/Unit/ReportsHardcodedUiTest.php:7-22`, `:24-36` — lista plików i wzorce
- `tests/Feature/ReportsPageTest.php:130-155`, `:422-441`, `:519-566` — propsy brak budżetu / draft / last-12
- `tests/e2e/reports.spec.ts:504-553` — last-12 z przełącznika i z URL; markupu draftu nie sprawdza nikt

## Architecture Insights

- Stany puste Raportów są wyjściem wcześniejszym niż wiersz tytułu. Każdy nowy widok przełącznika dziedziczy więc pytanie „czy draft go przejmuje” (`Reports.vue:128` ma już wyjątek dla last-12). Wiersz tytułu nad stanami pustymi usuwa ten wyjątek ze struktury.
- Wykres to dekoracja z danymi znanymi przed załadowaniem fragmentu, bo `reportChartBars` jest czystą funkcją w `@/lib`. Miejsce można więc zarezerwować dokładnie, bez zgadywania i bez ładowania Unovis.
- Sink Raportów rośnie przez kopiowanie gałęzi strony. Wcześniejsze poprawki wydzielały po jednym kawałku (`ReportEmptyState`, `ReportCoverageNote`), a ADR-0056 zna rozwiązanie całościowe: sink renderuje prawdziwy komponent.

## Historical Context (from prior changes)

- `context/archive/2026-11-12-rolling-twelve-month-report/plan.md:102` — dostęp do last-12 z draftu świadomie poza zakresem. Twierdzenie nadal trafne jako opis stanu, a pomiar C1 je potwierdza.
- `context/archive/2026-11-12-rolling-twelve-month-report/plan.md:56`, `:118` — sink powiela markup; `ReportCoverageNote` wydzielony, by go nie powielać. Potwierdzone: reszta kompozycji nadal jest powielona.
- `context/archive/2026-11-10-period-report-by-group/reviews/impl-review.md:126-134` — F8, sink odtwarza markup ręcznie; poprawione tylko dla stanów pustych. Potwierdzone jako częściowe.
- `context/archive/2026-11-10-period-report-by-group/plan.md:225` — macierz 7 stanów z „N/A i powodem”. Powód dla `loading` nie jest nigdzie uzasadniony i pomija fragment wykresu (C2).
- `context/archive/2026-11-11-report-trend-by-month/plan.md:323` — wykres Trendu async o stałej wysokości. Potwierdzone (`h-64`); stała wysokość nie zapobiega przeskokowi, bo przed doładowaniem miejsca nie ma wcale.
- ADR-0071:18, :24 — async dla rozmiaru bundla. ADR-0081:7, :17 — segment dostępny przy każdym Budżecie, rozstrzygany przed fazą. ADR-0056:37 — sink auth renderuje prawdziwą stronę.

## Related Research

- `context/archive/2026-11-12-report-to-operations/research.md` — linki nazw kategorii i `range`, tło dla C3.
- Inne research Raportów w `context/archive/*report*/` nie były potrzebne do tych pytań.

## Decisions (2026-11-12)

Użytkownik rozstrzygnął cztery pytania otwarte w rozmowie po researchu. Przy każdym widział porównanie wariantów i szkic na danych podglądu, a każdy wybór zgadza się z rekomendacją. Żaden zarzut nie jest odroczony.

1. **C1 → wiersz tytułu i pełny przełącznik w każdym stanie z Budżetem.**
   - W drafcie `h1` ma tytuł wybranego widoku (np. „Raport roku 2027”), przełącznik ma cztery segmenty, a kartka „Budżet w przygotowaniu” leży pod nimi.
   - „12 miesięcy” pokazuje dane okna. Rok, Miesiąc i Trend pokazują kartkę. Z każdego segmentu jest droga powrotna, więc droga w jedną stronę znika.
   - Bez żadnego Budżetu: sam `h1` „Raporty” i kartka „Brak budżetu”, bez przełącznika (last-12 nie działa, `ReportController.php:29-43`).
   - Odrzucone: przełącznik z wyłączonymi segmentami (stan disabled wbrew ADR-0081:7), sam tytuł (dostęp do last-12 dalej tylko z URL) i drugi przycisk w kartce (dwa wejścia do jednego widoku).
   - Zmienia zakres przyjęty w S-32 (`rolling-twelve-month-report/plan.md:102`), więc plan zapisuje to w ADR: dopisek do ADR-0081 albo nowy numer.
2. **C3 → sink renderuje prawdziwy `Reports.vue` z propsami z fixture,** jak `/dev/ui/auth` (ADR-0056:37).
   - Każdy scenariusz fixture dostaje pełne `ReportsPageProps`, w tym `budgetYear` i `budgetPhase`, i dochodzi scenariusz „draft + last-12”.
   - Kliknięcie przełącznika albo miesiąca w sinku nawiguje na prawdziwe `/reports`; to akceptowane.
   - Ręczne kopie z `ReportsKitchenSink.vue:150-437` znikają. Zostają macierz stanów i ramka telefonu.
   - Odrzucone: wydzielenie ciała strony do nowego komponentu (więcej przenoszenia, tytuł musiałby zamieszkać w środku) i pozostawienie kopii.
3. **C2 + C4 → dokładna wysokość z `@/lib/report-chart`.**
   - Jedna czysta funkcja wysokości, np. `reportChartHeightRem(bars, compared)`, z testem jednostkowym.
   - Używają jej wykres i placeholder: `Skeleton` w `ListSurface` o tej samej wysokości, z `loadingComponent` przy `defineAsyncComponent`.
   - Na danych demo 2026: wydatki 20rem, przychody 8rem, z porównaniem 29rem. Przy 0 słupkach nie ma ani wykresu, ani placeholdera.
   - Trend dostaje placeholder `h-64`.
   - Stałe z `ReportBarChart.vue:75-79` przechodzą do tej funkcji (C4 rozwiązany razem z C2).
   - Wiersz `loading` w macierzy sinka pokazuje stan „wykres w drodze” zamiast N/A.
   - Odrzucone: tokeny w `app.css` liczone przez `calc()` (szerokość nazw 110 px i tak zostaje w JS dla Unovis) i stała minimalna wysokość (przeskok o różnicę).
4. **C5 → poniżej 36rem szerokości karty Udział schodzi do drugiej linii wiersza** („udział 7,2%” pod kwotą).
   - Ten sam mechanizm `@container` na `.report-table` co porównanie (ADR-0074; `app.css:1480-1551`), bez zmian w `CategoryChip`. Od 36rem wiersz zostaje w jednej linii.
   - Plan dopisuje zdanie do `.ai/rules/reports.md:28` i sprawdza, czy zmiana układu kolumn wymaga ADR.
   - Odrzucone: łamanie nazwy (nowy prop we współdzielonym `CategoryChip`) i odroczenie.

## Open Questions

- Brak pytań produktowych. Do planu należą: forma zapisu decyzji 1 i 4 (dopisek do ADR-0081 / ADR-0074 albo nowy ADR) oraz to, jak sink podaje `Reports.vue` propsy, które strona dziś bierze z Inertia (`defineProps<ReportsPageProps>`, `Reports.vue:22`).

## Weryfikacja i środowisko

- Zrzuty i pomiary: `context/changes/reports-view/screenshots/audit-*.png` i `audit-measurements.json` (Chromium, viewport 1440×1000 i 352×900, locale pl-PL).
  - Fragment opóźniałem przez `page.route` na `ReportBarChart-*.js` / `ReportTrendChart-*.js`.
  - Przesunięcie to różnica `boundingBox().y` nagłówka tabeli przed i po pojawieniu się wykresu. CLS to suma wpisów `layout-shift` bez `hadRecentInput`.
- Podgląd: baza `preview_reports_view` (worktree `.env` ma ją w `DB_DATABASE`), `composer install` i `artisan migrate --seed` w `sail-8.5/app`, `npm ci` i `npm run build` z Linux Node i shimem `php` do obrazu Sail. Hostowego PHP nie używałem.
- Bazy deweloperskiej `household_treasurer` nie dotykałem: wszystkie zapisy (draft 2027, użytkownik `nobudget@example.com`) poszły do `preview_reports_view`.
