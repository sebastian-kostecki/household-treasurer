---
date: 2026-11-05T22:52:38+01:00
researcher: Claude (Opus 5.5) dla Sebastiana Kostecki
git_commit: c31b929f6f9d3b06cc6c4e69697aac639b7fccf1
branch: worktree-dashboard-atrament-ui
repository: household-treasurer
topic: "Dwukierunkowy audyt widoku Dashboard (Dashboard.vue) względem design systemu „C · Atrament i morski”"
tags: [research, codebase, dashboard, design-system, atrament, ui, stat-panel]
status: complete
last_updated: 2026-11-05
last_updated_by: Claude (Opus 5.5)
---

# Research: audyt Dashboardu względem systemu „C · Atrament i morski”

**Date**: 2026-11-05T22:52:38+01:00
**Git Commit**: c31b929 (gałąź z `development` f1bf41f + `change.md`; czyste drzewo)
**Branch**: worktree-dashboard-atrament-ui
**Wzorzec**: artefakt `https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA`, wersja 1793914821-7ae5. Przeczytane: `project/README.md`; README komponentów MockupCAtrament, StatPanel, Notice, DataTable, Tabs, Meter, Panel; z `bundle.css` reguły `ht-panel*`, `ht-stats`/`ht-stat*`, `ht-days`, `ht-row*`, `ht-meter*`, `ht-tabs*`, `ht-notice*` (`bundle.css:56-170`); z `MockupCAtrament/preview.html` widok Dashboard (`:671-711`), `monthReading` (`:406-416`), CSS `.grid2` (`:90`) i `.t-dash` (`:107`). Artefakt to wzorzec, nie źródło wartości: wartości żyją w `resources/css/app.css`.

## Research Question

Dwukierunkowy audyt `Dashboard.vue` i komponentów `DashboardNotices`, `DashboardCategorySection` (z `CategoryMeterRow`, `ExecutionMeter`), `DashboardHoldings`, `StatTile`, `BudgetPlanSummaryStrip` względem Atramentu (StatPanel, Notice, DataTable z Tabs, Meter, Panel; mockup MockupCAtrament → Dashboard). Potwierdzić 5 zarzutów z wstępnego audytu (plik:linia + wpływ) i zapisać je w `## Charges`. Rozstrzygnąć: (1) rozszerzenie `StatPanel` o sloty czy `StatTile`; (2) źródło propsa dla paska dni; (3) czy zakładki i usunięcie checkboxa wymagają ADR-0043; (4) los `BudgetPlanSummaryStrip` na Dashboardzie; (5) które komórki macierzy 7 stanów już są w `/dev/ui/dashboard`.

## Summary

- **Literały kolorów:** skan z `/10x-ui` na 9 plikach widoku (`Dashboard.vue`, `StatTile`, `DashboardNotices`, `DashboardCategorySection`, `DashboardHoldings`, `BudgetPlanSummaryStrip`, `CategoryMeterRow`, `ExecutionMeter`, `IndexEmptyState`) daje 0 trafień. Dryf leży w typografii, ramkach i przezroczystości sprzed Atramentu: 23 trafienia wymienione w C6 (22 w plikach widoku, 1 we współdzielonym `ListSurface`), których `check:ui` nie łapie (`scripts/check-ui-literals.mjs:114-117` szuka tylko hex/rgb, `-[Npx]`, `/[0.x]`, klas palety, `white`/`black`, `dark:`).
- **Zarzuty 1–5 z wstępnego audytu potwierdzone** z plikiem i linią (C1–C5). Research dokłada C6 (typografia i ramki w całym widoku) i C7 (kitchen sink i straże).
- **(1) StatPanel:** rozszerzyć `StatPanel` o opcjonalne sloty na miarę i zbudować wrapper `DashboardStatPanel` na wzór `MonthPlanStatPanel`. `StatPanel` nie ma dziś żadnego slotu (`StatPanel.vue:16-45`), a Badge „propozycja”, formularz „Plan miesiąca” i pasek dni nie mieszczą się w typie tekstowym `StatPanelMetric` (`:6-14`). `StatTile` zostaje, bo używają go `Accounts.vue:99` i `TermDeposits.vue:110`.
- **(2) Pasek dni:** nowy props `month_pace` (ten sam kształt co `monthPlan.month_pace` w Planie miesiąca) z `MonthPace::forMonth()` (`app/Support/MonthPace.php:19-56`). Kontroler ma już potrzebny odczyt `MonthToSpend` (`DashboardController.php:101-109`), a `DashboardNotices.php:222` robi dokładnie to samo wywołanie, więc koszt to zero nowych zapytań. Daje `elapsed_day` i `days_in_month` dla paska oraz `percent` dla podpisu „tempo miesiąca”. W mockupie to tempo wydatków, nie udział dni (`preview.html:415`). Nie potrzeba kopii TS.
- **(3) ADR-0043:** tak. Układ Dashboardu trzyma spec `.docs/specs/dashboard-hierarchy.md`, a nie ADR. Zmiany (a) jeden panel miar bez sum planu, (b) jeden panel z zakładkami, (c) stała kreska postępu roku bez checkboxa oraz (d) brak zwijania Kont i Lokat na telefonie przeczą historyjkom 19–22, 25, 28 i szczegółom `:52`, `:55`, `:56`, `:58` tego specu. Zmiana (c) dotyka też `CONTEXT.md:60`, a ta reguła AGENTS.md wymaga wpisu w CONTEXT i ADR przed kodem. Wiersz tytułu (e) mieści się w istniejących decyzjach (`.docs/design-system.md:83`).
- **(4) BudgetPlanSummaryStrip:** w realizacji zdjąć go z Dashboardu. Sumy planu przejmują wiersze grup tabeli (`ht-row--group`), a Saldo planowane zostaje na stronie Budżet (FR-REP-12, `.docs/prd.md:296`). Dla budżetu w szkicu (bez tabeli i bez Do wydania) to decyzja do planu (D3).
- **(5) Kitchen sink:** `/dev/ui/dashboard` renderuje cały `Dashboard.vue` w 7 scenariuszach (`resources/js/pages/dev/dashboard-fixtures.ts:282-294`). Pokazuje default, puste i błąd (tylko toast). Nie pokazuje hover, focus-visible ani disabled (odsyła do zrzutów działającej aplikacji). Brakuje stanów: zarchiwizowana kategoria, dochód ponad 100%, ponad 5 kategorii (rozwijanie), ponad 6 ostrzeżeń, 4 z 7 rodzajów ostrzeżeń, kreska postępu roku, konta puste przy istniejących lokatach.

## Charges

Każdy zarzut: dowód (plik:linia), wpływ na użytkownika, token lub komponent, który powinien go pokryć.

### C1. Brakujący `StatPanel`: trzy karty `StatTile` i drugi panel miar

- **Dowód:** `Dashboard.vue:191-241` renderuje trzy osobne `StatTile` (każda to własny `Card`, `StatTile.vue:22-38`) z wartością `CardTitle text-3xl` (`StatTile.vue:29`) zamiast `kpi-lg`/`kpi` (`app.css:267`, `:271`). Pod nimi `Dashboard.vue:341-348` renderuje drugi panel miar `BudgetPlanSummaryStrip` (Dochody planowane, Wydatki planowane, Saldo planowane), który już stoi na `StatPanel` (`BudgetPlanSummaryStrip.vue:59`). Na Dashboardzie dostaje 3 miary, bo nie dostaje `yearElapsedPercent` (`Dashboard.vue:341-348`, a w `Budget.vue:245` dostaje).
- **Wpływ:** użytkownik widzi dwie strefy liczb w różnych rozmiarach (30 px w kartach, 26/22 px w panelu) i musi szukać, która odpowiada na pytanie „ile mogę wydać”. Postępu roku nie ma wśród miar: jest tylko w etykiecie checkboxa (`Dashboard.vue:71-77`).
- **Wzorzec:** jeden `ht-stats` z czterema miarami: „Do wydania · <miesiąc>” (`kpi-lg`, pasek dni `ht-days`, podpis „upływ dni d/n · Plan miesiąca →”), Płynność dysponowalna („n konta, bez lokat”), Łączne środki („z lokatami”), Postęp roku (`N%`, podpis „tempo miesiąca N%”) (`preview.html:689-693`; `StatPanel/README.md`).
- **Poprawka:** `StatPanel` z opcjonalnymi slotami na miarę i wrapper `DashboardStatPanel` (D1). Zachować to, co wymaga spec: nazwę miesiąca w etykiecie, ton ujemny (`amountToneClass`), Badge `outline` „propozycja” tylko dla propozycji, link do Planu miesiąca przez POST `budget.select-year` z `destination=month-plan` (`dashboard-hierarchy.md:52`; powód POST: `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:93`) i układ bez miary Do wydania, gdy budżet nie jest w realizacji (historyjka 16, `dashboard-hierarchy.md:30`). Przenieść `role="group"` + `aria-labelledby` z `StatTile.vue:23` (poprawka F6 z `2026-11-03-dashboard-ui-contract`) na każdą miarę `StatPanel`.

### C2. Brakujący `DataTable` z `Tabs`: dwie sekcje zamiast jednego panelu realizacji

- **Dowód:**
  - `Dashboard.vue:359-377` renderuje dwa osobne `DashboardCategorySection` (każdy to `ListSurface`, `DashboardCategorySection.vue:80`).
  - Suma stoi na dole sekcji, a nie jako wiersz grupy nad pozycjami (`:115-181`).
  - Pasek w wierszu sumy jest zbudowany ręcznie (`:147-165`: tor `bg-muted h-1.5`, wypełnienie z `sectionBarClass`), a nie przez `ExecutionMeter`. `sectionBarClass` daje `bg-primary` w normie i przekroczenie dopiero przy `percent > 100` (`resources/js/lib/dashboard-categories.ts:132-149`). Wiersze dają `bg-foreground` i przekroczenie przy `isOverLimit` (`meterBarClass`, `:81-99`). To dwie reguły koloru dla tego samego odczytu.
  - Kwoty w wierszach i sumie idą przez `formatMoney` z `text-sm` (`CategoryMeterRow.vue:48-60`, `DashboardCategorySection.vue:121-145`), a nie przez `formatMoneyAmount` + `amount`/`amount-sum` ze stopką „Kwoty w zł” (`.ai/rules/js.md:16`).
  - Wiersz kategorii nie ma kolumny Status: jest tylko Badge „zarchiwizowana” (`CategoryMeterRow.vue:38-44`), bez „przekroczono”, który Budżet pokazuje (`BudgetCategoryRow.vue:90-95`).
  - Zarchiwizowany wiersz ma `opacity-70` (`CategoryMeterRow.vue:28`) i zachowuje `text-success` na dochodzie (`:57`), wbrew `.docs/design-system.md:17,54` i `.ai/rules/js.md:43` („cały wiersz `muted-foreground`, bez przezroczystości”).
  - Wiersz nie prowadzi do Operacji (brak `Link` w `CategoryMeterRow.vue`).
- **Wpływ:** żeby porównać dochody z wydatkami, użytkownik przewija dwie karty z sumą na dole każdej. Przekroczenie kategorii widać tylko po kolorze paska, bez słowa. Sumaryczny pasek wydatków może być w normie, gdy wiersz obok jest już czerwony.
- **Wzorzec:** jeden `ht-panel` „Realizacja budżetu <rok>” z `ht-tabs` Wszystko / Dochody / Wydatki w nagłówku. Kolumny Kategoria / Plan / Zrealizowano / Pozostało / Wykonanie / Status (`preview.html:698`). Wiersz grupy `ht-row--group` (`muted`, półgruby) nad pozycjami. Stopka „Kwoty w zł · kreska = postęp roku (N%) · kliknij wiersz, aby zobaczyć operacje” (`preview.html:700`). Klikalny wiersz `ht-row--click` (`DataTable/README.md`).
- **Poprawka:**
  - `SegmentedControl` `tone="neutral"` z klasą `segmented-control-tabs`, jak w `OperationsListPanel.vue:190-196` (Tabs/README: „W kodzie: SegmentedControl”).
  - Wiersz grupy na siatce `.category-meter-row` z `ExecutionMeter` i `amount-sum`, wg przepisu wiersza sumy Budżetu (`BudgetCategoryList.vue:161-165, 317-322, 372-374`).
  - Zarchiwizowany wiersz wg `BudgetCategoryRow.vue:69,153` (`text-muted-foreground`, `:muted` na `ExecutionMeter`).
  - Uwaga z przeglądu Budżetu (F2, `2026-11-05-budget-atrament-ui/reviews`): pusty tor paska na tle `muted` ma ok. 1.1:1. To dotyczy wiersza grupy.
  - `.budget-category-grid` nie nadaje się do ponownego użycia: ma obszary uchwytu, planu początkowego i akcji (`app.css:627-662`).

### C3. `Notice`: karty ostrzeżeń sprzed Atramentu

- **Dowód:**
  - Karta to `Link` z `rounded-2xl border p-4 … disabled:opacity-60` (`DashboardNotices.vue:331`).
  - Tło karty barwi waga: `border-destructive/25 bg-destructive/5 hover:bg-destructive/10` i `border-warning/30 bg-warning/5 hover:bg-warning/10` (`:136-140`).
  - Nazwa i kwota mają `text-base font-semibold` (`:393`, `:404`).
  - Ostrzeżenie miesiąca ma ręczny kafelek ikony `bg-muted text-muted-foreground size-8 rounded-lg` (`:375-381`).
  - Nagłówek strefy to `h2 text-sm font-semibold` (`:287`) zamiast `section-title`.
- **Wpływ:** sześć jaskrawych, dużych kart konkuruje z panelem miar. Przyciemnienie `opacity-60` przy wysyłanym POST obniża kontrast tekstu poniżej czytelności.
- **Wzorzec:** `ht-notice` (`bundle.css:160-170`) to karta `card` z neutralną ramką `border` i `radius-md`, hover `border-color: muted-foreground`. Wagę niesie tylko kafelek ikony 24 px (`warning`/`warning-soft` albo `destructive`/`destructive-soft`) i etykieta rodzaju (11/14, kolor wagi). Temat jest półgruby, zdanie z liczbami w `muted-foreground`. Siatka trzech kolumn (`Notice/README.md`).
- **Poprawka:** przebudować kartę w `DashboardNotices.vue` (nazwana klasa w `app.css`, np. `.dashboard-notice`). Bez nowego komponentu `Notice`: ADR-0042:9 odrzucił „osobny komponent Notice (dwa komponenty ostrzeżeń)”. Reguły ADR-0032 zostają: waga z serwera, jedna pigułka na wagę, płaska siatka, 6 kart i „Pokaż wszystkie (n)” (`dashboard-hierarchy.md:51`, `.ai/rules/support.md:10`). Do planu: czy karta pokazuje zdanie z liczbami, czy jak dziś nazwę i kwotę. Payload ma kwotę ze znakiem i pola wyświetlania (`dashboard-hierarchy.md:49`), a zdanie byłoby nowym tekstem w `lang/pl/ui.php`.

### C4. `Panel` i przypadkowa architektura w `DashboardHoldings`

- **Dowód:**
  - Nagłówek „Konta i lokaty” z przyciskiem `outline` jest tylko na desktopie (`DashboardHoldings.vue:73-82`).
  - Na telefonie drugi przycisk-akordeon `lg:hidden` (`:83-100`) z ikoną `opacity-60` (`:97`), bez `aria-controls`. Stan trzyma `localStorage` `dashboard-holdings-expanded` (`:16`, `:26-38`), zapisywany tylko przy `matchMedia('(max-width:1023.98px)')`, bez try/catch.
  - Wiersze sum mają `border-border/60 … border-t pt-3 text-sm font-semibold` (`:173`, `:241`) z kwotą przez `formatMoney`. Kwoty wierszy mają `text-sm font-semibold` (`:156`, `:224`), a separatory `divide-border/60` (`:138`, `:199`).
  - Sumy liczy `reduce` po stronie klienta (`:52-64`), a nie biorą ich z propsów `spendable_liquidity_minor_units` / `total_funds_minor_units` (`DashboardController.php:55-56`).
  - Kolumna to `lg:grid-cols-3` z `lg:col-span-2` / `lg:col-span-1` (`Dashboard.vue:250-251,381`).
- **Wpływ:** na telefonie konta są schowane za przyciskiem, który wygląda jak nagłówek, a na desktopie ten sam blok ma inny nagłówek. Dwa drzewa łamią regułę „jeden markup na każdą szerokość” (`.ai/rules/js.md:37`). Kolumna 1/3 rośnie z szerokością okna, więc nazwy kont i kwoty rozjeżdżają się na szerokim ekranie.
- **Wzorzec:** dwa `ht-panel`, „Konta” i „Lokaty”. Każdy ma akcję `ghost` „Wszystkie →” w nagłówku, wiersze (znak banku, nazwa, bank, kwota) i stopkę z sumą „Płynność dysponowalna” / „Łączne środki” (`preview.html:702-709`). Prawa kolumna ma 300 px (`.grid2 { grid-template-columns: 1fr 300px }`, `preview.html:90`; README „Układ”: `aside-width`).
- **Poprawka:** `ListSurface` nie ma slotu akcji w nagłówku, więc tytuł z akcją idzie przez `#header`, jak w `BudgetCategoryList.vue:198-214`. Stopka przez slot `footer` (`ListSurface.vue:19-64`). Token `aside-width` nie istnieje w `app.css`, trzeba go dodać jako nazwaną klasę siatki strony. Usunięcie akordeonu przeczy historyjce 28 i `dashboard-hierarchy.md:58` („on a phone … collapsed … remembered on this device”), więc wymaga ADR-0043 (D2).

### C5. Przypadkowa architektura: brak tytułu, nagłówek sekcji i checkbox postępu roku

- **Dowód:**
  - Widok nie ma wiersza tytułu. Treść zaczyna się kartą checklisty albo kartami miar (`Dashboard.vue:131-191`).
  - Nagłówek obszaru budżetu to `h2 text-sm font-semibold` (`:254`) zamiast `section-title` (`app.css:250`).
  - Kreskę postępu roku włącza `Checkbox` w nagłówku obszaru budżetu (`:269-283`), domyślnie wyłączony (`:60`). Stan trzyma `localStorage` `dashboard-year-elapsed` (`:38`, `:62-69`), bez try/catch wokół odczytu i zapisu.
  - Kolejność w kodzie (miary `:191` przed ostrzeżeniami `:243`) różni się od specu: „First Run checklist, an attention zone …, headline tiles” (`dashboard-hierarchy.md:9`). Mockup ma miary przed ostrzeżeniami (`preview.html:689-694`).
- **Wpływ:** domyślnie użytkownik nie widzi kreski postępu roku, więc pasek realizacji nie mówi, czy kategoria wyprzedza rok. Żeby to zobaczyć, musi znaleźć checkbox. Bez tytułu nic nie mówi, jaki to widok i dla jakiego dnia są odczyty.
- **Wzorzec:**
  - `PageTitle` „Dashboard” z linią meta („<data> · budżet <rok> w realizacji”) i akcjami (`preview.html:687`; `.docs/design-system.md:83`: h1 + Badge z lewej, akcje z prawej, najwyżej jedna `primary`).
  - Postęp roku jest miarą w panelu (C1).
  - Kreska stoi zawsze tam, gdzie pozwala `showsYearElapsedMark` (`dashboard-categories.ts:53-62`: dochód zawsze, wydatek tylko `is_paced`), opisana w stopce tabeli.
- **Poprawka:**
  - Wiersz tytułu z rokiem i „Otwórz budżet” przez `select-year` (ADR-0031:3) oraz Badge stanu budżetu.
  - Usunąć checkbox, klucz `localStorage` i osierocone klucze tłumaczeń. Przykład `Checkbox v-model` w `.ai/rules/js.md:28` wskazuje Dashboard.vue, więc trzeba go przepiąć na inny plik.
  - Datę „na dzień” (`CONTEXT.md:60` wymaga daty obok tekstu) przenieść do stopki tabeli albo podpisu miary Postęp roku. To wymaga wpisu w CONTEXT i ADR-0043 (D2).
  - Akcje „Dodaj operację” / „Importuj wyciąg” są odroczone (D6).

### C6. Brakujące tokeny: typografia, ramki i przezroczystość sprzed Atramentu (research)

Nazwane style istnieją w `app.css:245-283` (`page-title`, `section-title`, `table-header`, `label-caps`, `meta`, `kpi-lg`, `kpi`, `amount`, `amount-sum`), a linie między wierszami to `border-soft` (`.docs/design-system.md:18`). W 9 plikach widoku plus `ListSurface` zostają 23 trafienia (pozycje poniżej), które przepuszcza `check:ui`:

- `Dashboard.vue:139` (`CardTitle text-base`, checklista), `:254` (`text-sm font-semibold`)
- `StatTile.vue:29` (`text-3xl`)
- `DashboardNotices.vue:287` (`text-sm font-semibold`), `:331` (`rounded-2xl`, `disabled:opacity-60`), `:393`, `:404` (`text-base font-semibold`), `:138-139` (tinty `/25 /5 /10 /30`)
- `DashboardCategorySection.vue:101` (`divide-border/60`), `:116`, `:186` (`border-border/60`)
- `CategoryMeterRow.vue:28` (`opacity-70`)
- `DashboardHoldings.vue:74`, `:86` (`text-sm font-semibold`), `:97` (`opacity-60`), `:138`, `:199` (`divide-border/60`), `:156`, `:224` (`text-sm font-semibold` na kwotach), `:173`, `:241` (`border-border/60 … text-sm font-semibold`)
- `IndexEmptyState.vue:13` (`h2 text-base font-medium`)
- `ListSurface.vue:46` (`CardTitle text-base`, współdzielony z Budżetem i Operacjami: poza zakresem tego widoku, ale nagłówki paneli Dashboardu przez niego przechodzą)

**Wpływ:** Dashboard wygląda jak inna aplikacja niż Budżet i Plan miesiąca, które już przeszły na Atrament. Rozmiary nagłówków i kwot nie zgadzają się między widokami.

### C7. Kitchen sink i straże nie pilnują Dashboardu (research)

- **Dowód:**
  - `DashboardKitchenSink.vue` montuje cały `Dashboard.vue` dla każdego scenariusza (`:99-111`), co jest dobre (nie kopiuje markupu, por. month-plan F7). Jego tabela stanów (`:12-41`) odsyła hover, focus i disabled do „zrzutów działającej aplikacji”.
  - Straże:
    - `MonthPlanHardcodedUiTest.php:24-35` łapie typografię sprzed Atramentu, `rounded-xl/2xl`, `border-border/N` i `opacity-N`, ale tylko dla plików Planu miesiąca (`:7-20`).
    - Dashboard ma tylko `check:ui` (`check-ui-literals.mjs:10-18`), który tych wzorców nie łapie.
    - `ui-contract.test.ts:13-32` nie obejmuje żadnego pliku `Dashboard*` ani `CategoryMeterRow`.
  - `lib/dashboard-categories.test.ts` testuje tylko `meterBarClass` (4 przypadki). `showsYearElapsedMark`, przycinanie listy i `section*` są bez testów.
  - `change.md` podawał ścieżkę `resources/js/lib/dashboard-fixtures.ts`, a plik leży w `resources/js/pages/dev/dashboard-fixtures.ts`. Poprawione w Notes.
- **Wpływ:** bez straży kolejny agent może przywrócić `rounded-2xl` czy `text-3xl` i nic tego nie zatrzyma. Stany zarchiwizowane, przekroczenie i rozwijanie nie mają gdzie się pokazać przed przeglądem.
- **Poprawka:** `DashboardHardcodedUiTest.php` na wzór `MonthPlanHardcodedUiTest`, nowe scenariusze w fixtures (lista w „Macierz 7 stanów”) i nowe pliki dopisane do `check-ui-literals.mjs`.

## Decisions to carry into the plan

### D1. StatPanel: sloty w `StatPanel` + wrapper `DashboardStatPanel` (rozstrzygnięte)

- **Konsumenci `StatTile`:**
  - `Dashboard.vue:192-240` (3 kafle; sloty `label-extra` i `footer`)
  - `Accounts.vue:99` (bez slotów)
  - `TermDeposits.vue:110` (`#footer` w `:116`)
- **Konsumenci `StatPanel`:** wyłącznie wrappery `MonthPlanStatPanel.vue:92` i `BudgetPlanSummaryStrip.vue:59`. Oba budują `computed` z samymi tekstami. `MonthPlanStatPanel` stawia `Alert note` pod panelem (`:93-98`), a nie w nim.
- **Propozycja:**
  - `StatPanelMetric` dostaje opcjonalne `id`, używane jako klucz `v-for` (dziś kluczem jest `metric.label`, `StatPanel.vue:27`).
  - `StatPanel` wystawia opcjonalne sloty `label-extra-<id>` i `footer-<id>`. Nazwy pochodzą z API `StatTile` (`StatTile.vue:26,35`), więc odpowiedniość z artefaktem („StatPanel → StatTile”) zostaje czytelna.
  - Miara dostaje `role="group"` i `aria-labelledby`.
  - Sloty są opcjonalne, więc Budżet i Plan miesiąca nie zmieniają się. `StatPanel` jest na listach `check-ui-literals.mjs:82`, `ui-contract.test.ts:24` i `MonthPlanHardcodedUiTest.php:16`.
- **`StatTile`** zostaje dla Kont i Lokat. Ich restyle to osobna zmiana (jeden widok na zmianę).
- **Pasek dni** (`ht-days`, `bundle.css:68-70`) to nowa nazwana klasa w `app.css` (np. `.month-days`, `.month-days-day`, `-past`) z tokenami `track` / `foreground`.

### D2. Pasek dni i tempo: props `month_pace` z serwera (rozstrzygnięte)

- **Gdzie dziś są dni:** `month_to_spend` niesie tylko `{state, to_spend_minor_units}` (`types/dashboard.ts:55-58`, `DashboardController.php:104-107`). Żaden props Dashboardu nie niesie dnia miesiąca.
- **Źródło na serwerze:** `MonthPace::forMonth($toSpend, $year, $month, $today)` (`app/Support/MonthPace.php:19-56`) zwraca `{as_of, elapsed_day, days_in_month, pool_plan_minor_units, pool_actual_minor_units, percent, is_ahead}`. Dni pochodzą z `MonthElapsed::asOfDate` (`app/Support/MonthElapsed.php:12-26`). Plan miesiąca dostaje to samo jako `monthPlan.month_pace` (`MonthPlanController.php:219-221`; typ `MonthPace` w `resources/js/types/month-plan.ts:5-13`).
- **Koszt:** kontroler ma już `$monthToSpendReading` (`DashboardController.php:101`), a `DashboardNotices.php:222` wykonuje to samo wywołanie wewnątrz budowy ostrzeżeń. Nowy props nie dodaje zapytań.
- **„Tempo miesiąca”** w mockupie to `runAct / runPlan` (`preview.html:415`), czyli `MonthPaceCalculator::percent` (`app/Support/MonthPaceCalculator.php:42-49`; null, gdy plan puli ≤ 0). Udział dni mockup liczy osobno (`days`, `:415`) i pokazuje jako „upływ dni d/n”. Pasek i podpis renderują liczby całkowite z serwera. Nie potrzeba `monthElapsedPercent` (TS, `month-pace-calculator.ts:70-76`, bez bliźniaka w PHP) ani nowej kopii TS. To zgodne z AGENTS.md („Compute new month readings on the server”).
- **Kiedy null:** `MonthPace` jest null, gdy stan planu to `none` (`MonthPace.php:21`). Wtedy null jest też `month_to_spend` (`DashboardController.php:101-109`), więc miary Do wydania i tak nie ma.
- **Kontrakt propsów** (AGENTS.md, w jednym commicie):
  - `DashboardController` i `DashboardPageProps` (`types/dashboard.ts:41-61`)
  - `assertInertia` w `tests/Feature/DashboardTest.php`, na wzór `MonthPlanPageTest.php:60-66`
  - każdy fixture w `pages/dev/dashboard-fixtures.ts`, bo inaczej `types:check` padnie

### D3. ADR-0043 i dokumenty przed kodem (rozstrzygnięte: potrzebny)

- **Precedensy:** obie poprzednie zmiany Atramentu otwierały plan fazą „Decyzje i dokumenty” z ADR przed kodem: ADR-0037 dla Budżetu, ADR-0042 dla Planu miesiąca (`context/archive/2026-11-05-budget-atrament-ui/plan.md`, `2026-11-05-month-plan-atrament-ui/plan.md`). ADR-0038 pokazuje, że ADR powstaje także wtedy, gdy wcześniejszy ADR danej rzeczy nie trzymał.
- **Werdykty dla zmian (a)–(e):**
  - (a) jeden panel miar bez sum planu: przeczy historyjkom 22 i 25 oraz `:55` → **ADR**. Postęp roku jako miara stoi obok definicji „A mark on a Category meter, not a money reading” (`CONTEXT.md:60`), więc trzeba dopisać zdanie w CONTEXT.
  - (b) jeden panel z zakładkami i wierszem grupy na górze: przeczy historyjkom 19–21 i `:56` (dwie karty, „bold totals row below”) → **ADR**. `.docs/design-system.md:88` dopuszcza dziś ton `neutral` jako zakładki tylko dla sekcji Operacji, więc trzeba to rozszerzyć.
  - (c) kreska postępu roku bez checkboxa: przeczy `:56` i `CONTEXT.md:60` („the Dashboard checkbox always shows the as-of date”) → **CONTEXT + ADR**.
  - (d) dwa panele Konta i Lokaty ze stopką sumy: wizualne, zgodne z `:58`. Usunięcie akordeonu na telefonie przeczy historyjce 28 i `:58` → **ADR**. Stała kolumna 300 px zamiast „about 2/3 and 1/3” (`:53`) jest wizualna; wystarczy zapisać ją w ADR.
  - (e) wiersz tytułu: mieści się w `.docs/design-system.md:83` → bez ADR.
  - Kolejność miary → ostrzeżenia: kod już odbiega od `:9`, a ADR powinien zapisać obowiązującą kolejność.
- **Do aktualizacji:**
  - ADR-0043 z notami „Superseded by ADR-0043” w specu: historyjki 15–17, 19–22, 25, 28 i szczegóły `:52`, `:53`, `:55`, `:56`, `:58`, w stylu not S-07 już obecnych w specu.
  - `CONTEXT.md:60`.
  - `.docs/design-system.md`: `:3` (lista ADR), `:25` (widoki na kroju mono), `:68-75` (alerty), `:81` (Gotowe elementy: StatTile, StatPanel, ExecutionMeter), `:85-88` (SegmentedControl jako zakładki), `:92-96` i `:124` (kitchen sinki).
  - `.ai/rules/js.md`: `:28` (przykład Checkbox), `:37`, `:42`, `:44`.

### D4. `BudgetPlanSummaryStrip` na Dashboardzie (rozstrzygnięte dla realizacji, otwarte dla szkicu)

- **Realizacja:** zdjąć z Dashboardu.
  - Mockup nie ma sum planu w panelu miar (`preview.html:689-693`).
  - Plan i Zrealizowano każdego typu niosą wiersze grup tabeli (FR-REP-05/06, `.docs/prd.md:289-290`).
  - Saldo planowane zostaje na stronie Budżet (FR-REP-12, `:296`; `Budget.vue:245`).
  - Props `planSummary` jest asertowany w `DashboardTest.php:52,70,280,375`. Jeśli zostaje tylko dla szkicu, testy zostają. Jeśli znika, usunąć props z obu stron kontraktu.
  - `BudgetPlanSummaryStrip` zostaje w kodzie dla `Budget.vue:245` i `dev/BudgetKitchenSink.vue:8`.
  - Przegląd Budżetu F3 (PENDING) już zauważył, że wspólny pasek zmienił Dashboard poza zakresem.
- **Szkic:** historyjka 25 (`dashboard-hierarchy.md:39`) każe pokazać „only its plan totals with Planned Balance, a draft badge, and a link to Budget”. W szkicu nie ma tabeli realizacji ani Do wydania. Decyzja do planu (Open Questions Q3).

### D5. Macierz 7 stanów w `/dev/ui/dashboard` (stan obecny)

Scenariusze: `default`, `zero-amounts`, `negative`, `empty-new-user`, `no-budget`, `draft`, `no-categories` (`pages/dev/dashboard-fixtures.ts:282-294`). Checklista zależy od zalogowanego użytkownika, nie od fixture (`DashboardKitchenSink.vue:55-58`).

| Stan | Miary | Ostrzeżenia | Tabela realizacji | Konta / Lokaty | Checklista | Brak budżetu / szkic |
| --- | --- | --- | --- | --- | --- | --- |
| default | jest | jest (2: `month_to_spend_negative`, `month_plan_not_approved`) | jest (2 dochody, 3 wydatki) | jest (2 konta, 1 lokata) | nie (zależy od użytkownika) | jest |
| hover | brak | brak | brak | brak | brak | — |
| focus-visible | brak | brak | brak | brak | brak | — |
| disabled | brak (POST w toku tylko opisany) | brak | — | — | brak | brak |
| error | tylko przycisk toastu (`:89-96`) | — | — | — | — | — |
| empty | zera (`zero-amounts`) | sekcja ukryta (`v-if`) | „brak kategorii” (`no-categories`) | stan pusty (`empty-new-user`), lokaty ukryte (`zero-amounts`) | brak | jest (`no-budget`, `draft`) |
| loading | N/A z powodem (`:23`, brak deferred props, `dashboard-hierarchy.md:59`) | N/A | N/A | N/A | N/A | N/A |

Inne pokryte stany: Badge propozycji (`zero-amounts`, fixtures `:162`); kwoty ujemne (konto −450, Do wydania −230, saldo planowane −500, wydatek ponad limit 2300/1800, `:176-232`).

**Brakuje** (do dopisania w fazie stanów):

- zarchiwizowana kategoria, konto i lokata
- dochód ponad 100%
- ponad 5 kategorii w grupie (rozwijanie)
- ponad 6 ostrzeżeń („Pokaż wszystkie”)
- rodzaje `negative_term_deposit`, `month_item_over_plan`, `month_pace_ahead`, `one_off_over_limit`
- kreska postępu roku (kategorie paced 3 i 5 kontra jednorazowa 4)
- konta puste przy istniejących lokatach
- każda zakładka (Wszystko / Dochody / Wydatki)
- pasek dni na początku, w środku i na końcu miesiąca
- tempo „—” (plan puli ≤ 0)
- POST w toku (disabled) na linku Planu miesiąca i na „Otwórz budżet”

Hover, focus-visible i disabled trzeba pokazać w zrzutach (przeglądy budget F7, design-tokens F1 i dashboard-ui-contract F4 wykazały pominięte zrzuty).

### D6. Odroczone (z powodem)

- **„Dodaj operację” / „Importuj wyciąg” w wierszu tytułu** (`preview.html:687`): nowa funkcja na Dashboardzie (dialogi operacji). Spec daje Dashboardowi „one job” (`dashboard-hierarchy.md:9`) i wyłącza takie rzeczy z zakresu (`:76`).
- **Paleta `⌘K` i skróty:** sam mockup wskazuje je jako osobną zmianę (`MockupCAtrament/README.md`, „Czego nie przenosić dosłownie”).
- **Status „blisko limitu” w wierszu kategorii:** mockup pokazuje trzy statusy (`preview.html:295-301`), ale aplikacja nie ma progu „blisko” dla kategorii rocznej (`meterBarClass` zna tylko normę i przekroczenie, `dashboard-categories.ts:81-99`). Nowy próg wymaga najpierw wpisu w `CONTEXT.md`. W tej zmianie Status pokazuje tylko „przekroczono” (`destructive-soft`), jak Budżet.
- **Restyle `StatTile` w Kontach i Lokatach, `ListSurface` `CardTitle text-base`:** inne widoki.

## Code References

- `resources/js/pages/Dashboard.vue:60-69` - checkbox postępu roku w `localStorage` (bez try/catch)
- `resources/js/pages/Dashboard.vue:131-189` - checklista First Run
- `resources/js/pages/Dashboard.vue:191-241` - trzy `StatTile`
- `resources/js/pages/Dashboard.vue:243-248` - `DashboardNotices`
- `resources/js/pages/Dashboard.vue:250-387` - siatka strony 2/3 + 1/3, obszar budżetu, `BudgetPlanSummaryStrip`, sekcje kategorii, `DashboardHoldings`
- `resources/js/components/StatTile.vue:22-38` - karta miary, `text-3xl`
- `resources/js/components/StatPanel.vue:6-45` - typ miary, brak slotów, klucz `label`
- `resources/js/components/MonthPlanStatPanel.vue:61-98` - wzorzec wrappera
- `resources/js/components/BudgetPlanSummaryStrip.vue:20-59` - sumy planu na `StatPanel`
- `resources/js/components/DashboardCategorySection.vue:80-205` - sekcja, ręczny wiersz sumy, rozwijanie
- `resources/js/components/CategoryMeterRow.vue:28-83` - `opacity-70`, Badge archiwum, kwoty `formatMoney`
- `resources/js/lib/dashboard-categories.ts:26-62,81-149` - limit 5, `showsYearElapsedMark`, `meterBarClass` kontra `sectionBarClass`
- `resources/js/components/DashboardNotices.vue:80,132-140,287-331,375-404` - limit 6, klasy wagi, karta
- `resources/js/components/DashboardHoldings.vue:16-38,52-64,73-252` - akordeon, sumy klienta, listy
- `resources/js/components/ListSurface.vue:19-64` - propsy i sloty (`header`, `footer`)
- `resources/js/components/SegmentedControl.vue:4-63` - API; `OperationsListPanel.vue:190-196` - zakładki
- `resources/js/components/BudgetCategoryList.vue:161-165,198-214,317-322,372-374` - przepis wiersza sumy i nagłówka z akcją
- `app/Http/Controllers/DashboardController.php:31,55-56,81-119,121-141` - „dziś”, propsy
- `app/Support/MonthPace.php:19-56`, `app/Support/MonthElapsed.php:12-26`, `app/Support/MonthPaceCalculator.php:42-49` - dni i tempo
- `app/Support/DashboardNotices.php:222` - istniejące wywołanie `MonthPace::forMonth`
- `resources/js/types/dashboard.ts:41-61` - `DashboardPageProps`
- `tests/Feature/DashboardTest.php:33-71,235-280,409-428,661-791` - asercje propsów
- `resources/js/pages/dev/DashboardKitchenSink.vue:12-41,99-111`, `resources/js/pages/dev/dashboard-fixtures.ts:114-294` - kitchen sink
- `scripts/check-ui-literals.mjs:10-18,114-117` - lista i wzorce `check:ui`
- `tests/Unit/MonthPlanHardcodedUiTest.php:7-35` - wzorzec testu literałów
- `resources/css/app.css:245-283` (style), `:978-1070` (siatka kategorii, `stat-panel`), `:515-554` (`segmented-control`), `:788` (`dashboard-holdings-row`)
- `.docs/specs/dashboard-hierarchy.md:9,29-43,48-59,76` - kontrakt układu Dashboardu
- `CONTEXT.md:60` - Year Elapsed i checkbox

## Architecture Insights

- Atrament wchodzi do repo widok po widoku: tokeny (`2026-11-05-design-tokens-atrament`), Budżet (ADR-0037), Operacje (ADR-0038/0039), dialogi (ADR-0040/0041), Plan miesiąca (ADR-0042). Każda zmiana miała tę samą kolejność faz: Decyzje i dokumenty → Warstwa wspólna → Kontrakt propsów → Widok → Stany i bramka wizualna.
- Wspólne prymitywy to wrappery z `computed` nad prostym komponentem (`StatPanel` ← `MonthPlanStatPanel`, `BudgetPlanSummaryStrip`), a nie komponenty z wieloma wariantami. Sloty w `StatPanel` to pierwszy przypadek treści bogatszej niż tekst w miarze.
- Powtarzalny układ trafia do nazwanych klas w `@layer components` w `app.css`, a nie do utility w szablonie (`.ai/rules/js.md:56`). Komponent z `@layer components` nie nadpisze utility z wariantem hover (przegląd month-plan F1).
- Dashboard trzyma dwie reguły koloru paska (`meterBarClass` i `sectionBarClass`). Przejście wiersza sumy na `ExecutionMeter` zostawia jedną.

## Historical Context (from prior changes)

- `context/archive/2026-11-03-dashboard-ui-contract/` - wcześniejszy przebieg `/10x-ui` na Dashboardzie: kitchen sink, `check:ui`, `role="group"` na `StatTile` (F6), pending flagi `select-year` (F1/F2). Te decyzje zostają: zachować obsługę fałszywego toastu przy przenoszeniu linków.
- `context/archive/2026-11-05-budget-atrament-ui/` - ADR-0037; przepis wiersza sumy z `ExecutionMeter`; przegląd: F2 (pusty tor na `muted` ok. 1.1:1), F3 (pasek planu na Dashboardzie zmieniony poza zakresem, PENDING), F6 (`title` na nieaktywnym `div` niedostępny z klawiatury), F7 (brak zrzutów).
- `context/archive/2026-11-05-month-plan-atrament-ui/` - ADR-0042 (odrzucenie osobnego komponentu Notice, `Alert note`); `MonthPlanHardcodedUiTest`; przegląd: F2 (baza podglądu bez stanu), F6 (resztki po usunięciu), F7 (sink kopiował markup), F8 (dostępność siatek z `div`), F11 (przepełnienie przy 390 px widoczne dopiero na zrzucie).
- `context/archive/2026-11-02-to-spend-replaces-remaining/plan.md:93` - dlaczego link do Planu miesiąca to POST `select-year`.
- `.docs/adr/0032-*` - zasady strefy uwagi (waga z serwera, płaska siatka, 6 kart).
- `.docs/adr/0031-*` - Dashboard czyta bieżący rok; „Otwórz budżet” przełącza rok powłoki.

## Related Research

- `context/archive/2026-11-05-month-plan-atrament-ui/research.md` - ten sam audyt dla Planu miesiąca (StatPanel, `Alert note`, kitchen sink)
- `context/archive/2026-11-05-budget-atrament-ui/research.md` - audyt Budżetu (wiersz sumy, `ExecutionMeter`, zarchiwizowane wiersze)
- `context/archive/2026-11-03-dashboard-ui-contract/research.md` - poprzedni audyt Dashboardu

## Open Questions

Decyzje produktowe do rozstrzygnięcia w `/10x-plan` (rekomendacja w nawiasie):

- **Q1. Zakładki:** stan lokalny resetowany przy każdej wizycie czy w URL jak `?section=` w Operacjach (ADR-0038)? (Lokalny, domyślnie „Wszystko”: historyjka 29 każe resetować rozwinięcia przy każdej wizycie, a Dashboard nie ma filtrów do udostępniania.)
- **Q2. Limit 5 kategorii i rozwijanie w zakładkach:** mockup pokazuje wszystkie wiersze. (Zostawić limit 5 na grupę z rozwinięciem w miejscu. To rdzeń specu „progressive disclosure” i nie wymaga zmiany w ADR.)
- **Q3. Szkic budżetu:** co stoi w miejscu tabeli realizacji? (Zostawić `BudgetPlanSummaryStrip` tylko w szkicu, zgodnie z historyjką 25. W realizacji go nie ma.)
- **Q4. Klikalny wiersz kategorii → Operacje z filtrem:** mockup to pokazuje. Cel już istnieje w ostrzeżeniach (`DashboardNotices.vue:242-270`), ale omija `operationsListQuery` (`.ai/rules/js.md:63`). (Włączyć i oprzeć na `operationsListQuery`. Albo odroczyć, jeśli plan ma być czysto wizualny.)
- **Q5. Karta ostrzeżenia ze zdaniem z liczbami czy z nazwą i kwotą jak dziś?** (Nazwa i kwota: zdanie to nowy tekst dla 7 rodzajów i ryzyko rozjazdu z regułami ADR-0032.)
- **Q6. Sumy w stopkach Kont i Lokat:** propsy serwera (`spendable_liquidity_minor_units`, `total_funds_minor_units`) czy `reduce` jak dziś? Nie sprawdziłem, czy lista kont na Dashboardzie pomija zarchiwizowane konta, które `SpendableLiquidity::forUser` może liczyć (`DashboardController.php:35-56` używa osobno `$accounts` i `$allAccounts`). Jeśli tak, suma listy i Płynność dysponowalna mogą się różnić. Sprawdzić w planie, zanim etykieta stopki powie „Płynność dysponowalna”.
- **Q7. Kolejność:** miary przed ostrzeżeniami (mockup i obecny kod) czy ostrzeżenia przed miarami (spec `:9`)? (Miary pierwsze, zapisane w ADR-0043.)
