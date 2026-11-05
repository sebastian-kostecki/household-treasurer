---
date: 2026-11-05T12:24:11+01:00
researcher: Claude (Sonnet 5.5) dla Sebastiana Kostecki
git_commit: daf375cfe15e62c45332e2f9d97c341d154eb488
branch: development
repository: household-treasurer
topic: "Dwukierunkowy audyt widoku /budget względem design systemu „C · Atrament i morski”"
tags: [research, codebase, budget, design-system, atrament, ui]
status: complete
last_updated: 2026-11-05
last_updated_by: Claude (Sonnet 5.5)
---

# Research: audyt widoku /budget względem systemu „C · Atrament i morski”

**Date**: 2026-11-05T12:24:11+01:00
**Git Commit**: daf375c (czyste drzewo poza `context/`; `roadmap.md` zmodyfikowany, `context/changes/budget-atrament-ui/` nieśledzony)
**Branch**: development
**Wzorzec**: artefakt `https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA`, wersja 1793873124-2686. Przeczytane w całości: `project/README.md`, `tokens.json`, README komponentów DataTable, PageTitle, Panel, Badge, Switch, StatPanel, Meter, Amount, Button, MonthStrip, MockupCAtrament; z `MockupCAtrament/preview.html` widok Budżet (linie 713-746) i funkcja `status` (295-300). Artefakt to podgląd, nie źródło wartości (`change.md`).

## Research Question

Dwukierunkowy audyt `/budget` względem systemu (kod → wzorzec i wzorzec → kod): zapisać zarzuty C1–C5, decyzje, oraz sprawdzić: konsumentów propsów miesiąca w `BudgetController`, współdzielenie `BudgetMonthPicker`, źródło postępu roku, wpływ globalnych zmian Card / `column-caption` / Button, `formatMoney` bez jednostki, dodanie Switch, dokumenty do aktualizacji, rozszerzenie `check-ui-literals`.

## Summary

- Pięć zarzutów C1–C5 potwierdza się w kodzie (sekcja Charges). Wszystkie mają kotwice `plik:linia`.
- **Dwa zarzuty brzegowe z briefu wymagają korekty**: (1) w repo **nie ma jeszcze żadnego** progu „blisko limitu” dla kategorii, więc pigułka z trzema słowami z README (`w normie` / `blisko limitu` / `przekroczono`) nie ma reguły domenowej poza `przekroczono`; (2) „archiwum w osobnym Collapsible” (część C5) jest **wymogiem domenowym** (CONTEXT.md:36, PRD FR-REP-15), a nie przypadkową architekturą.
- **Ryzyko decyzji „BudgetMonthStrip usuwamy”**: pasek jest jedyną powierzchnią na stronie Budżet, która pokazuje Month Result wybranego miesiąca (FR-REP-14, PRD `.docs/prd.md:117`) i „do wydania” z linkiem do planu. Raport okresu z `period-report-by-category` nie jest na `main`. Do rozstrzygnięcia przed planem (Open Questions 1).
- Usunięcie paska czyni martwymi sześć propsów czytanych wyłącznie przez pasek (`monthIncomeExecutionMinorUnits`, `monthExpenseExecutionMinorUnits`, `monthResultMinorUnits`, `currentMonth`, `viewedMonthTiming`, `monthToSpend`), a `viewedMonth` zostaje tylko w URL przełącznika archiwum. Testy `BudgetMonthRemainingTest` i `BudgetMonthResultTest` asercjami o nich się rozsypią. Część propsów jest martwa już dziś.
- `BudgetMonthPicker` zostaje: używa go `MonthPlan.vue:539`.
- Postęp roku: brak propa w Budżecie. Gotowy odczyt to `YearElapsed` (`app/Support/YearElapsed.php`). CONTEXT.md:60 już definiuje jego as-of dla Budżetu wybranego roku (przeszły → 31 grudnia, przyszły → 1 stycznia). Procent wykonania wydatków jest już w `expenseTotals.percent_complete`.
- Kitchen sink `/dev/kitchen-sink/budget` **już istnieje** (`routes/web.php:33-35`), więc nie trzeba go tworzyć, tylko przebudować.
- ADR: najwyższy istniejący to 0035; 0036 zarezerwowany przez `period-report-by-category`; 0037 jest wolny (brak innych rezerwacji w `context/`).

## Charges

### C1. Typografia kwot i miar: kroje, style, jednostka w każdej komórce

Stan: w żadnym z 15 plików Budżetu (lista z `ui-contract.test.ts:13-29`) nie ma `font-mono`; w całym `resources/js` jest on tylko w czterech plikach `dev/*KitchenSink.vue` i `TwoFactorRecoveryCodes.vue`. Style `page-title`, `section-title`, `table-header`, `kpi`, `amount` **nie istnieją** w `resources/css` ani `resources/js`; nie ma też `--control-height` ani `--row-height` (istnieją tylko `--radius*`, `app.css:16-18,152`). `--font-mono` jest zdefiniowane (`app.css:14`), a ADR-0035 mówi, że kwoty przechodzą na ten krój „widok po widoku”.

- `pages/Budget.vue:190` tytuł `text-lg font-semibold` (ma być `page-title` 18/24, 600, -0.01em).
- `BudgetCategoryList.vue:194` nagłówek panelu `text-base font-semibold` (ma być `section-title` 13/18).
- `BudgetPlanSummaryStrip.vue:29,37,46` miary `text-lg font-semibold tabular-nums` (ma być `kpi` mono 22/28; pierwsza `kpi-lg` 26/32).
- `BudgetAmountCell.vue:250-259` kwota `inline-flex … justify-end` bez kroju mono, a `:258` woła `formatMoney`, który zawsze dopisuje „zł” (`resources/js/lib/format.ts:4-13`). Efekt: „zł” w każdej komórce tabeli (do 5 kwot × wiersz, jedna wspólna jednostka powinna stać w stopce „Kwoty w zł”).
- `BudgetCategoryRow.vue:109` procent `text-xs tabular-nums`; `BudgetCategoryList.vue:295` to samo w sumie.

Wpływ: bez stylów w `app.css` nie da się napisać widoku bez literałów (`ui-contract.test.ts:11` zabrania `-[12px]`); style trzeba dodać do `app.css` przed Vue. `formatMoney` nie ma wariantu bez jednostki, a `format.test.ts` (30 linii) istnieje. Nowy `formatMoneyAmount` (bez „zł”), z `formatMoney` jako nakładką, nie zmienia żadnego z 24 konsumentów.

### C2. Globalne przepisy panelu i tabeli

- Card: `ui/card/Card.vue:15` `rounded-2xl border border-border/60 py-6` → `radius-lg` (10 px, `--radius` już 10 px, `app.css:152`) i pełny `border`. `ListSurface.vue` i `StatTile.vue` owijają Card i dziedziczą zmianę.
- Nagłówek kolumn: `app.css:231-233` `@utility column-caption` = 11 px, wersaliki, `tracking-wider` → `table-header` (12/16, 600, zwykła wielkość liter, jedna linia). Przez `@apply` dziedziczą: `.column-header` (`app.css:265`), `.list-surface-table thead th` (299-301), `.budget-category-header-row` (407-409), `.operations-header-row` (536-538), `.import-preview-header-row` (671-673) oraz element `category-meter-header-row column-caption` (`DashboardCategorySection.vue:86`).
- Wiersz sumy: `app.css:417-419` `.budget-category-totals-row` = `bg-foreground/[0.02]` → `muted`. Wariant sticky `app.css:476-485` ręcznie miksuje te same 2% przez `color-mix`, więc zmienia się razem z tłem.
- Hover: `hover:bg-foreground/[0.03]` w sześciu regułach (`app.css:304, 404, 524, 529, 541, 682`) → `secondary`. Uwaga: `secondary` w jasnym motywie to `#f3f5f8` na `card` `#fff` (`app.css:124`), czyli bardzo niski kontrast; to wartość z tokenów systemu, ale warto ją zobaczyć na zrzucie.
- Button: `ui/button/index.ts:7` baza `rounded-xl`, rozmiary `:24-29`: default `h-9`, sm `h-8`, lg `h-10`, icon `size-9`. Po zmianie default na 32 px (`h-8`) zderza się z `sm` (`h-8`) i `icon-sm`; tokeny systemu mówią „26 px w wariancie małym” (`tokens.json`, `control-height`), więc `sm` i `icon-sm` trzeba rozstrzygnąć razem z default. Z tego samego powodu spójność wymaga zmiany `Input.vue:27` (`h-9 … rounded-xl`), `SelectTrigger.vue:24` i `.native-select` (`app.css:237`); zakres kontrolek poza przyciskami jest decyzją do ADR-0037.
- Poza Card, na ręcznych literałach, zostają niespójne: `rounded-2xl` w `OperationsFilters.vue:389`, `MonthPlanEditBar.vue:31`, `ManagePasskeys.vue`, `DashboardNotices.vue`, `ui/dialog/DialogContent.vue` (README podaje promień dialogu `radius-xl` 12 px, czyli to osobna decyzja), a `h-9` w `AppHeader.vue:89,171,196,215`, `AuthCardLayout.vue:28-29`, `AuthSimpleLayout.vue:24-27`.

Wpływ: patrz sekcja „Wpływ globalnych zmian”.

### C3. Switch zamiast Checkbox; warianty Badge

- `pages/Budget.vue:299-307`: `Checkbox id="show-archived-budget"` + `Label` „Pokaż zarchiwizowane”; stan w `ref` z watcherem (`Budget.vue:93-106`) i `toggleShowArchived` (`:150-167`). Zamiana jest lokalna dla tych linii; `v-model` zostaje. Klucz `budget.show_archived` (`lang/pl/ui.php:191`) wystarcza. `ui/switch` nie istnieje, a prymityw jest w `reka-ui` (`SwitchRoot.js`).
- `pages/Budget.vue:193`: `Badge variant="secondary"` dla fazy. Wzorzec: `Realizacja` = `brand`, `Szkic` = `warn`. Badge ma dziś warianty `default`, `secondary`, `destructive`, `outline`, `warning`, `destructive-soft` (`ui/badge/index.ts:13-30`); **brak `ok`, `brand`, `neutral`**. `warning` i `destructive-soft` nie są użyte na żadnym Badge w repo (użycia dotyczą `Alert`), więc są martwe, ale pasują do `--warn` i `--over`.
- Inne `secondary`: `BudgetCategoryRow.vue:67` („Zarchiwizowane” → `neutral`), `BudgetCategoryList.vue:339` (licznik archiwum), `BudgetMonthStrip.vue` (2 Badge, usuwany).

Wpływ: dodanie `ok`/`brand`/`neutral` nie zmienia 11 pozostałych plików z Badge, o ile nie zmienia się wyglądu `secondary`/`outline`/`default`.

### C4. Panel miar, Meter, kreska roku

- `Budget.vue:267-274` renderuje `BudgetPlanSummaryStrip` (`Card` z trzema miarami w `grid sm:grid-cols-3`, `BudgetPlanSummaryStrip.vue:18-53`), a nie panel z miarami `StatTile`/`StatPanel` rozdzielonymi pionowymi liniami. Podpis „Plan roczny” używa `column-caption` (`:21`), czyli wersalików, a README dopuszcza `label-caps` tylko nad miarami.
- Mockup (`preview.html:723`) pokazuje czwartą miarę „Wykonanie wydatków” wyłącznie poza szkicem, z wartością `Σwykonanie/Σplan` w % i podpisem „postęp roku N%”. Procent jest **już w propsach**: `expenseTotals.percent_complete` (`BudgetPlanSummary.php:94-97`, `CategoryExecutionMetrics.php:12-19`, typ `DashboardTypeTotals` w `dashboard-categories.ts:16-22`). Brakuje tylko postępu roku (patrz niżej).
- Wiersze Budżetu nie mają paska: tylko tekst `{{ percent_complete }}%` (`BudgetCategoryRow.vue:107-121`, suma `BudgetCategoryList.vue:293-307`). Mockup ma kolumnę `meter` (`t-budget`: `1fr 120px 112px 112px 124px 112px 26px`) z paskiem, kreską roku i procentem. Dashboard ma już pasek z kreską w `CategoryMeterRow.vue:80-110` (`showYearElapsedMark`, `yearElapsedPercent`), ale kreska jest tam `bg-foreground`, a README wymaga `brand-text`, wypełnienie w normie `primary` zmienia się na `foreground`, i komponent jest używany tylko w `DashboardCategorySection.vue`. Reguła, które kategorie mają kreskę, jest w `showsYearElapsedMark` (`dashboard-categories.ts:53-62`: dochody wszystkie, wydatki tylko równomierne), zgodna z CONTEXT.md:60.
- Siatka `.budget-category-grid` (`app.css:362-401`, md: `2.5rem 1fr repeat(4, 8rem) 4.5rem`) nie ma kolumny paska, a ma uchwyt przeciągania (`.drag-handle`, `BudgetCategoryRow.vue:50-58`), którego mockup nie ma. Uchwyt to funkcja (reorder: `can_reorder`), więc zostaje.

### C5. Układ widoku roku

- `BudgetMonthStrip` stoi w widoku roku (`Budget.vue:276-297`) między miarami a tabelami; wzorzec nie ma go na Budżecie (mockup: tytuł → miary → panele Dochody i Wydatki). Komponent nie jest używany przez żaden inny widok (importują go tylko `Budget.vue:11` i `dev/BudgetKitchenSink.vue:8`).
- Archiwum: `BudgetCategoryList.vue:324-365` to `Collapsible` w tym samym `ListSurface` pod sumą. Mockup nie ma ani przełącznika, ani sekcji archiwum (dane demo bez zarchiwizowanych), a `DataTable` README opisuje archiwalny wiersz jako wiersz tabeli w całości `muted-foreground`. **Sprzeczność z domeną**: CONTEXT.md:36 („Budget lists them in an archived section”) i FR-REP-15. Patrz Decisions 4.
- Akcje: „Dodaj …” siedzi w nagłówku każdego z dwóch paneli (`BudgetCategoryList.vue:204-211`, outline sm); akcja główna „Rozpocznij realizację” w tytule (`Budget.vue:196-243`, `Button` domyślny, czyli primary), usuwanie w menu `…` (`:244-263`). Mockup ma „Dodaj kategorię” jako jedną akcję zarysowaną w wierszu tytułu i „Rozpocznij realizację” jako primary, a w nagłówku panelu `section-title` + meta „X z Y” po prawej (`preview.html:739`). README PageTitle: najwyżej jedna akcja główna.
- Mockup nagłówek panelu pokazuje sumę wykonania „z” planu (`fmt(Σexec) z fmt(Σplan)`), czego Budżet dziś nie ma; dane są w `totals`.

## Decisions

Zapisane z `/10x-ui` (2026-11-05, `change.md:20`) i potwierdzone przez użytkownika w briefie:

1. **Punkty 3 i 5 README globalnie w tej zmianie**: panel `radius-lg` + pełny `border` zamiast `rounded-2xl` + `border/60`, kontrolki 32 px, `column-caption` → `table-header`. Ten zakres ADR-0035 wprost zostawił na osobny ADR (`0035-…md:9`).
2. **Nowy ADR-0037** (`.docs/adr/0037-…md`). 0036 trzyma `period-report-by-category` (`change.md:13`, `plan.md:258`). Format ADR: nagłówek + proza, wzajemne „Superseded in part by” w starszym i „This supersedes in part” w nowym.
3. **`BudgetMonthStrip` usuwamy z Budżetu** (brief + `change.md:20`). Konsekwencje zbadane niżej, a jedno ryzyko wymaga potwierdzenia (Open Questions 1).

Dodatkowe, wynikłe z audytu, jeszcze nie podjęte przez użytkownika (rekomendacje do `/10x-plan`):

4. **Archiwum zostaje osobną sekcją** dopóki CONTEXT.md:36 i FR-REP-15 nie zostaną zmienione. Zmiana wyglądu (muted wiersze, Switch) mieści się w tym; przeniesienie wierszy do głównej tabeli to zmiana domeny i wymaga wpisu w CONTEXT.md oraz ADR przed kodem (AGENTS.md, „Domain changes come before code”).
5. **Słowa statusu**: zacząć od tego, co domena już rozstrzyga (`is_over_limit` → „przekroczono”, reszta „w normie”). „blisko limitu” wymaga reguły (mockup: wydatek wyprzedza postęp roku o ponad 5 pkt proc., ale jego README mówi, że ta reguła jest uproszczona, a ostrzeżenia nadaje `DashboardNotices`, w którym kategoria ma dziś tylko `month_item_over_plan` i `one_off_over_limit`, `DashboardNotices.php:153,201`). `.ai/rules/support.md` wymaga dla nowego rodzaju ostrzeżenia terminu i progów w CONTEXT.md przed kodem.

## Detailed Findings

### Konsumenci propsów miesiąca w `BudgetController`

Obserwacja z odczytu kodu (`BudgetController.php:33-133`, grep `resources/js`):

| Prop | Konsument we froncie | Po usunięciu paska |
|---|---|---|
| `viewedMonth` (`:121`) | `Budget.vue:74`; `:155` i `:169-170` dokładają `month` do URL przełącznika archiwum; pasek `:278-285` | martwy poza URL przełącznika |
| `monthIncomeExecutionMinorUnits`, `monthExpenseExecutionMinorUnits`, `monthResultMinorUnits` (`:123-125`) | tylko pasek → `BudgetMonthResultSummary` | martwe |
| `currentMonth`, `viewedMonthTiming`, `monthToSpend` (`:126-130`) | tylko pasek | martwe |
| `pacedPoolMonthRemainingMinorUnits` (`:122`) | zadeklarowany `Budget.vue:75`, **nigdzie nieczytany** | martwy już dziś |
| `expenseCategories[].month_remaining_minor_units`, `month_execution_minor_units` (`:277-280`) | **żaden**: brak w typie `BudgetCategoryRow` (`BudgetCategoryList.vue:29-51`); grep w `resources/js` trafia wyłącznie w biblioteki kalkulatora i ich testy | martwe już dziś |

Wniosek: odczyt Month Remaining na stronie Budżet (FR-REP-13) nie ma dziś żadnej powierzchni w UI, mimo że kontroler go liczy. To niezależne od tej zmiany znalezisko.

Wywołujący klas wspierających: `BudgetViewedMonth` także `MonthPlanController.php:57`; `BudgetMonthResult` także `MonthSummary.php:39`; `MonthToSpend` także `DashboardController.php:101` i `MonthPlanController.php:159`; `BudgetMonthExecutionIndex` także import CSV i wpis ręczny. **Tylko `BudgetMonthReadings` jest używany wyłącznie przez `BudgetController`** (`:14,75`) i byłby osierocony. `BudgetMonthResultSummary.vue` importuje wyłącznie pasek.

Testy, które łamią się przy usunięciu lub zmianie nazw propsów (z grep, nie z uruchomienia):
- `tests/Feature/BudgetMonthRemainingTest.php`: całość o propsach Budżetu (`viewedMonth` l.31-315, `pacedPoolMonthRemainingMinorUnits` l.151-251, `month_remaining_minor_units` l.152-252, `currentMonth`/`viewedMonthTiming` l.268-316; test `…drops_month_query` l.206-235).
- `tests/Feature/BudgetMonthResultTest.php`: `monthIncomeExecutionMinorUnits` itd. l.74-169, `monthToSpend` l.186-267.
- Przetrwają: `BudgetTest.php:198-206` (`budget.*`, `incomeCategories.*`), `BudgetViewedMonthTest`, `MonthPlanPageTest`, `DashboardTest`.
- Reguła repo: nie usuwać testów bez zgody (AGENTS.md / pest rules), więc usunięcie tych dwóch plików wymaga zgody użytkownika. AGENTS.md wymaga też zmiany propsów po obu stronach i `assertInertia` w Feature test.

### `BudgetMonthPicker`

Współdzielony: importują go `BudgetMonthStrip.vue:4` i `MonthPlan.vue:8` (użycie `:539`, z `monthUrl` budującym `monthPlanRoute({query:{month}})`, `MonthPlan.vue:343-345`). Po usunięciu paska zostaje wyłącznie MonthPlan. Domyślna ścieżka (route Budżetu, prop `showArchived`) staje się nieosiągalna, ale usuwanie jej jest opcjonalne. Pilnuje go `tests/Unit/MonthPlanHardcodedUiTest.php:15` oraz `ui-contract.test.ts:23`. Mockup ma osobny `MonthStrip` (pasek 12 miesięcy: wybrany `primary`, kropka stanu), którego README wskazuje jako kod `BudgetMonthStrip.vue`; to rozjazd nazw: opis wzorca „W kodzie” odnosi się do komponentu, który usuwamy z Budżetu, a realnie będzie dotyczył MonthPlan i `BudgetMonthPicker`.

### Skąd postęp roku

- `App\Support\YearElapsed` (`app/Support/YearElapsed.php`): `asOfDate($budgetYear, $today)` (rok bieżący → dziś, przeszły → 31 XII, przyszły → 1 I) i `percent($asOf)` = `100 × dayOfYear / daysInYear` (float; przyszły rok daje ≈0,27, a nie 0). Testy: `tests/Unit/YearElapsedTest.php`.
- Dashboard liczy to w `DashboardController.php:81-83` i wysyła `year_elapsed_as_of`, `year_elapsed_percent` (`:139-140`), typ `types/dashboard.ts:52-53`.
- CONTEXT.md:60 już zakłada Budżet wybranego roku jako powierzchnię inną niż Dashboard (as-of dla przeszłego/przyszłego roku). Najczystsze: w `BudgetController` wywołać `YearElapsed::percent(YearElapsed::asOfDate($budget->year, $today))` (`$today` jest w `:69`) i dodać prop `year_elapsed_percent` (null w gałęzi bez budżetu `:49-56`), z asercją `assertInertia` w Feature teście. Nowa klasa niepotrzebna. Spec `dashboard-hierarchy.md:47` wprost zostawia `YearElapsed::asOfDate` bez zmian dla innych powierzchni.
- Wartość dla rocznego budżetu w szkicu: czwarta miara i kreski są poza szkicem w mockupie, więc prop może być null w szkicu.
- Procent „Wykonanie wydatków” zależy od `show_archived` (sumy liczone z `$categories` po filtrze, `BudgetController.php:82-88`), tak samo jak plan: zgodne z FR-REP-15.

### Wpływ globalnych zmian (Card, `column-caption`, Button)

Liczby z regexu po `<Button …>` i grep, mogą różnić się o 1-2:
- Card/ListSurface/StatTile: 21 plików w szablonach. Strony: Dashboard, MonthPlan, Operations, OperationsImport(+Preview), Accounts, TermDeposits, układ auth, Landing, ustawienia bezpieczeństwa (`TwoFactorRecoveryCodes`).
- `column-caption`/`column-header` w JS: `MonthPlan.vue` (5), `ImportPreviewRow.vue` (6, z `xl:sr-only`), `DashboardHoldings.vue` (2), `DashboardCategorySection.vue` (1), `BudgetPlanSummaryStrip.vue` (1), plus pliki `dev/*`. Plus pośrednio Operacje, Budżet, Import przez `@apply`.
- Button bez `size`: 108 z 144 użyć poza `ui/`; zmienią wysokość z 36 na 32 px. Największe: `BudgetCategoryRowActions.vue` (6), `Budget.vue` (5), `OperationsPageActions.vue` (5), `TermDeposits.vue` (4), `Accounts.vue` (4).
- Żaden test PHP ani `*.test.ts` nie asercjonuje klas `rounded-2xl`, `column-caption`, `h-9`, `border-border/60` (grep w `tests/` i `resources/js`). Skany literałów (`ui-contract.test.ts`, `check-ui-literals.mjs`) łamią się tylko na nowych literałach w skanowanych plikach, nie na zmianie `app.css`.
- Rozjazdy tekstowe: `.docs/design-system.md` i `pages/dev/ImportPreviewSink.vue:74` („tłem foreground/3%”).
- Kitchen sinki istniejące: Budget, Dashboard, Accounts, Sidebar, TermDeposits (wzorzec A lub B w `routes/web.php:25-46`), MonthPlan, Operations, ImportPreview. Wszystkie skorzystają wizualnie na jednej zmianie globalnej, więc bramka zrzutu powinna objąć co najmniej Dashboard i Operacje obok Budżetu.

### `/dev/kitchen-sink/budget`

Istnieje: `routes/web.php:33-35` (`if (app()->isLocal())`, `Route::inertia(…, 'dev/BudgetKitchenSink')`), strona 500 linii, `tests/Feature/BudgetKitchenSinkTest.php` z jednym testem (404 poza local). Strona renderuje `BudgetMonthStrip` trzy razy (`BudgetKitchenSink.vue:413-450`) i używa `Checkbox` (`:10`). Po zmianie trzeba: usunąć paski, dodać Switch, czwartą miarę, wiersze z paskiem w stanach normalny / blisko / ponad / zarchiwizowany. Dodatkowych testów kontraktu nie wymaga reguła repo, ale brak testu pozytywnego (render w `local`) jest istniejącą luką (niepewne, czy da się go dodać, bo trasa rejestruje się przy starcie; inne sinki w wzorcu A robią to przez kontroler).

### Switch przez shadcn-vue CLI

`components.json`: `style: "new-york-v4"`, aliasy `@/components/ui`, `@/lib/utils`; `tailwind.config: "tailwind.config.js"` wskazuje plik, którego nie ma (Tailwind 4) i nie wiadomo, czy CLI to zniesie. `reka-ui ^2.11.0`, `class-variance-authority`, `clsx`, `tailwind-merge` są; CLI shadcn nie jest zależnością (jedyny precedens: `period-report-by-category/plan.md:277-280`, `npx shadcn-vue@2.9.0 add chart` po sprawdzeniu `npm view`). `vite.config.ts:71,94` wyłącza `resources/js/components/ui/*` z lint i fmt (generowany kod z podwójnymi cudzysłowami i bez średników nie zawiedzie `npm run check`). Ostrożności z AGENTS.md: tylko npm, nie dodawać `pnpm-workspace.yaml`, trzymać się `vp`, sprawdzić `git status` po komendzie (lockfile, nowe pliki). Generowany Switch ma własną wysokość (`h-[1.15rem]` w new-york-v4, niepotwierdzone w tym repo, bo CLI nie był uruchamiany); README wymaga toru `track`, włączonego `primary`, gałki `card`, więc wartości trzeba dopasować tokenami po wygenerowaniu. `ui-contract.test.ts:61-80` pilnuje martwych bindingów Checkbox, nie Switch.

### Dokumenty do aktualizacji

- `.docs/design-system.md`: L3 (lista ADR), L14 (`rounded-2xl`, `border/60`), L15 (36 px `h-9`, `rounded-xl`), L16 (selecty `rounded-xl`), L17-19 (`column-caption`, `.column-header`, `.list-surface-table`), L24 (JetBrains Mono „wdrażany widok po widoku”: dopisać Budżet), L55-56 (jednostka „zł” w tabeli), L79 i L104-107 (kitchen sinki, `check-ui-literals`).
- `.ai/rules/js.md`: L14-16 (formatMoney z „zł” + `tabular-nums`), L20 (ADR-0003, `rounded-2xl`, `border/60`), L26-28 (zasada Checkbox: dodać odpowiednik Switch), L36 (sink), L41-43 i L57-66 (`column-caption` jako jedyny przepis podpisu). `.ai/rules/css.md` (10 linii) bez konfliktu; można dodać wiersz o `table-header`/`--control-height`. Indeks `.ai/rules/index.md` bez zmian.
- ADR: 0003 (`wallet-master-surface-recipe`) ma „Superseded in part by ADR-0035”; dopisać 0037 dla receptury karty. 0035:9 dopisać „supersedes in part by 0037” dla punktów 3 i 5. 0001 i 0023 dotyka tylko ADR-0035 (turkus, Geist), nie 0037. Uwaga: sam ADR-0003 nie zawiera `rounded-2xl`/`border/60`; te literały są w design-system.md L14 i js.md L20.
- Tłumaczenia: `lang/pl/ui.php` grupa `budget` od linii 159 plus typy w `resources/js/types/shell.ts`. Brakujące klucze (grep po „w normie”, „blisko limitu”, „przekroczono”, „Wykonanie wydatków” w `lang/`, `resources/js`, `app/` nie trafia): etykieta czwartej miary i trzy słowa statusu. Istniejące do ponownego użycia: `budget.show_archived`, `budget.execution`, `budget.archived_badge`, `dashboard.year_elapsed` („Postęp roku”).
- `CONTEXT.md` i PRD: patrz Open Questions 1 i 2.

### `scripts/check-ui-literals.mjs`

Lista `FILES` (49 jawnych ścieżek, nie glob; argumenty CLI zastępują listę) zawiera dziś `CreateBudgetDialog`, `BudgetPlanSummaryStrip`, `BudgetYearSwitcher`, `CategoryMeterRow`, `StatTile`; **brakuje**: `pages/Budget.vue`, `BudgetCategoryList`, `BudgetCategoryHeaderRow`, `BudgetCategoryRow`, `BudgetAmountCell`, `BudgetEmptyState`, `BudgetCategoryRowActions`, `BudgetCategoryAlignPlanAction`, `BudgetMonthPicker`, `BudgetMonthResultSummary`, `BudgetMonthStrip` i `pages/dev/BudgetKitchenSink.vue`. Uruchomienie skryptu na wszystkich tych plikach przed zmianami kończy się kodem 0 (zero trafień), więc dodanie ich do listy nic nie psuje. Skrypt łapie więcej niż regex w `ui-contract.test.ts:10-11` (także `dark:` i `/[0.NN]`). Przy usunięciu komponentu trzeba usunąć wpis z `FILES` (brak pliku daje kod 2) i z `BUDGET_VIEW_FILES`. Podłączony: `package.json:11`, `composer.json:70` (`ci:check`), `.github/workflows/tests.yml:101-102`. Nie ma testu samego skryptu.

## Code References

- `resources/js/pages/Budget.vue:190,193,267-307` – tytuł, Badge fazy, miary, pasek miesiąca, Checkbox.
- `resources/js/components/BudgetCategoryList.vue:194,204-211,293-307,324-365` – nagłówek panelu, akcja, procent w sumie, archiwum.
- `resources/js/components/BudgetCategoryRow.vue:50-58,67,107-121` – uchwyt, Badge archiwum, procent.
- `resources/js/components/BudgetAmountCell.vue:250-259` – kwota z `formatMoney`.
- `resources/js/components/BudgetPlanSummaryStrip.vue:18-53` – trzy miary w Card.
- `resources/js/components/ui/card/Card.vue:15`, `ui/button/index.ts:7,24-29`, `ui/badge/index.ts:13-30`.
- `resources/css/app.css:16-18,152,231-233,237,265,362-419,476-485` – promienie, `column-caption`, siatka Budżetu, wiersze, suma.
- `app/Http/Controllers/BudgetController.php:69-132,250-281` – propsy miesiąca i `categoryPayload`.
- `app/Support/YearElapsed.php:9-25`, `DashboardController.php:81-83,139-140`.
- `resources/js/ui-contract.test.ts:13-29`, `scripts/check-ui-literals.mjs:9-58,65-68`.

## Architecture Insights

- Style typograficzne z systemu (`page-title`…`amount`) mają być tokenami/utilities w `app.css`, nie literałami; testy literałów wymuszają to w plikach Budżetu.
- Zasada AGENTS.md o bliźniaczych kopiach TS/PHP nie dotyczy tej zmiany, jeśli procent wykonania i postęp roku liczy serwer.
- Zmiana prop-ów miesiąca w Budżecie musi iść w jednym commicie po obu stronach z `assertInertia` (AGENTS.md, „Inertia props contract”).

## Historical Context (from prior changes)

- `context/archive/2026-11-05-design-tokens-atrament/` – wdrożenie tokenów (ADR-0035); punkty 3, 5, 7, 8 README wprost wyłączone (`0035-…md:9`). Status: poparte.
- `context/changes/period-report-by-category/` – rezerwuje ADR-0036 (`change.md:13`, `plan.md:258`), wprowadza Raporty; jego Wynik miesiąca = `BudgetMonthResult::forViewedMonth` (`plan.md:10,26`). Nie jest na `main`.
- ADR-0033 (`month-plan-beside-annual-budget`) – „do wydania” na stronie Budżet; dziś pasek to ta powierzchnia (FR-REP-13, `.docs/prd.md:297`).
- ADR-0031 i `.docs/specs/dashboard-hierarchy.md:47` – Dashboard zawsze rok bieżący; `YearElapsed::asOfDate` nie zmieniać.

## Related Research

`context/changes/period-report-by-category/` (plan, bez research.md w czasie badania). Brak innych.

## Open Questions

1. **Gdzie po usunięciu paska będzie Month Result wybranego miesiąca i „do wydania” z linkiem do planu?** `MonthSummary::forMonth` zwraca null bez zatwierdzonego planu (`MonthSummary.php:29-37`) i jest używany tylko dla miesięcy minionych (`MonthPlanController.php:222`), więc dla miesiąca bez zatwierdzonego planu jedynym odczytem Month Result jest dziś pasek. PRD ma to jako wymóg (FR-REP-14, `.docs/prd.md:298`; `:117`). Opcje: (a) uznać, że zastąpi go strona Raporty z `period-report-by-category` (kolejność zmian), (b) wstawić Month Result do `MonthPlan`, (c) zachować mały odczyt na Budżecie. To decyzja użytkownika; brief już mówi „usuwamy”, a badanie tylko ujawnia konsekwencję. Do rozstrzygnięcia przed `/10x-plan` (zakres usuwanych propsów i testów zależy od odpowiedzi).
2. **Archiwum**: zostaje osobna sekcja (zgodnie z CONTEXT.md:36 i FR-REP-15), czy wiersze archiwalne trafiają do tabeli jako `muted`? Druga opcja wymaga zmiany domeny + ADR.
3. **Reguła „blisko limitu”** i czy w Budżecie pokazujemy trzy słowa statusu, czy tylko „przekroczono”. Wymaga CONTEXT.md przed kodem, jeśli ma być nowy próg.
4. **Rozmiar `Button` `sm`/`icon-sm` i zakres 32 px** na `Input`, `SelectTrigger`, `.native-select`, oraz promień `Dialog` (README: `radius-xl`).
5. Draft Budżetu: mockup ma kolumny „Plan roczny” i „Typ” (dochód / rachunek / bieżąca) (`preview.html:731-733`), a aplikacja w szkicu ma tylko plan (`BudgetCategoryList.vue:268-274`, `month_item_kind` jest w payloadzie). Poza C1–C5; do decyzji czy wchodzi w zakres.
6. Martwe propsy `pacedPoolMonthRemainingMinorUnits` i `month_*` w wierszach istnieją niezależnie od tej zmiany; czy sprzątać je tu, czy zostawić do osobnej zmiany?
7. Czy CLI shadcn-vue działa przy brakującym `tailwind.config.js` (nie uruchamiano).

**Zakres sprawdzony**: kod `/budget` i kontroler, `app.css`, `ui/{card,button,badge}`, trasy i testy sinków, skrypt literałów, dokumentacja `.docs/` i `.ai/rules`, ADR 0001-0035 (tytuły i statusy), `lang/pl/ui.php`, README i mockup wzorca. Nie uruchamiano testów, buildów ani CLI. Negatywne twierdzenia („brak klucza”, „brak testu pozytywnego sinka”) dotyczą wymienionych katalogów.
