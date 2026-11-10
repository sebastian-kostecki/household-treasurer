<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Raport okresu po grupach (S-28)

- **Plan**: context/changes/period-report-by-group/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-10
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | FAIL |

## Success criteria — uruchomione

| Polecenie | Wynik |
|---|---|
| `composer ci:check` (PostgreSQL, sail-8.5) | ✅ exit 0: lint, PHPStan, vitest, check:ui, 1187 passed / 2 skipped |
| `vendor/bin/sloppy ci` (w obrazie sail-8.5) | ✅ 100/100, 0 findings |
| `npm run test -- resources/js/lib/format-share.test.ts` | ✅ 3 passed |
| `artisan test --group=nfr --configuration=phpunit.nfr.xml` (PostgreSQL) | ❌ 3 passed, 1 failed: `NfrPerf03ImportPreviewTest` 5,04 s / 3,73 s / 2,05 s przy limicie 2 s (3 z 3 przebiegów). `NfrPerfReportTest` i `NfrPerf01Test` zielone |
| `npx playwright test tests/e2e/reports.spec.ts` | ✅ 5 passed (4,1 min) |

Uwagi do środowiska: host CLI ma tu PHP 8.3 i windowsowy `npm`, więc Sloppy szedł w obrazie sail-8.5, `npm run build` w E2E przez shim `php` → sail-8.5, a dane logowania E2E z `.env` głównego checkoutu (w `.env` worktree są puste). Kod się przez to nie zmienił.

Sprawdzone bez ustaleń: autoryzacja (`auth`+`verified`, `authorize('view')`, rok z sesji tylko z budżetów użytkownika, grupy po `user_id`), kitchen sink 404 poza `local`, brak `v-html` i surowego SQL, stała liczba zapytań (test 2 vs 24 kategorie), pieniądze tylko w `int`, formuła udziału i przykłady z planu, parsowanie `?month`, lustro propsów PHP ↔ `types/reports.ts`, nic z „What We're NOT Doing”. Poza zakresem zmiany (istniało wcześniej): `RestoreUserData` nie sprawdza zgodności typu operacji z typem kategorii, a od tej zgodności zależy równość raportu z Wynikiem miesiąca.

## Findings

### F1 — Grupa `nfr` nie jest zielona: podgląd importu > 2 s

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: tests/Feature/Nfr/NfrPerf03ImportPreviewTest.php:118
- **Detail**: Progress 2.2 jest odhaczony, a plan (Faza 2 §6) wymaga zielonej całej grupy `nfr`. Na tej maszynie `NfrPerf03ImportPreviewTest` przekracza 2 s w 3 z 3 przebiegów (5,04 s pod obciążeniem E2E, potem 3,73 s i 2,05 s osobno). Kod tej zmiany nie dotyka importu: jedyna zmiana w tym teście to `category_id` → `budget_line_id` (:158). Przed nią test padał na nieistniejącej kolumnie, więc wolny podgląd importu był po prostu niewidoczny. Zmiana go odsłoniła, nie spowodowała. Nowy `NfrPerfReportTest` (raport roku i miesiąca) przechodzi.
- **Fix A ⭐ Recommended**: Zapisać w Progress 2.2 faktyczny stan (NfrPerf01 i NfrPerfReport zielone, NfrPerf03 2,05–3,7 s) i założyć osobne zgłoszenie na wydajność podglądu importu z pomiarem.
  - Strength: Uczciwy stan w planie i zakres S-28 bez zmian. Przyczyna leży w imporcie, którego ten diff nie dotyka.
  - Tradeoff: Grupa `nfr` zostaje czerwona do czasu tamtej poprawki.
  - Confidence: HIGH — `git diff 8501070..HEAD` nie zmienia żadnego pliku importu.
  - Blind spot: Nie wiadomo, czy w CI (inny sprzęt) test mieści się w 2 s.
- **Fix B**: Zdiagnozować i przyspieszyć podgląd importu w tej zmianie.
  - Strength: Cała grupa `nfr` zielona przed zamknięciem S-28.
  - Tradeoff: Rozszerza zakres o import, przyczyna nieznana (zapytania, parsowanie CSV, sesja).
  - Confidence: LOW — brak pomiaru, gdzie idzie czas.
  - Blind spot: Może to być wyłącznie wolne środowisko WSL/Docker.
- **Decision**: FIXED (Fix A) — adnotacja pod Progress 2.2, GitHub #223, wpis w follow-ups/review-fixes.md

### F2 — Wykres bez dodatnich kwot: pusta ramka z etykietami „0,0.5 zł”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ReportBarChart.vue:40-42, 59-61, 65
- **Detail**: Wykres renderuje się przy `bars.length > 0`. Gdy wszystkie kwoty typu są ≤ 0 (np. miesiąc z samymi zwrotami), `maxValue = 1`, więc domena osi to `[0, 1]` grosza. Unovis daje wtedy ticki 0; 0,5; 1, a `formatMoneyAmount(0.5)` zwraca `"0,0.5"` (`gr = 0.5`, `padStart` nic nie robi). Użytkownik widzi pustą ramkę z błędnymi etykietami. Scenariusz `non-positive-expenses` w kitchen sinku (`reports-fixtures.ts:109`) trafia dokładnie w ten przypadek, a plan mówi „kwoty ≤ 0 nie dostają słupka”, nie „pusty wykres”.
- **Fix**: Pokazywać wykres tylko, gdy co najmniej jeden słupek > 0 (`bars.some((bar) => bar.value > 0)`), najpierw z testem odtwarzającym (np. helper w `lib/` z `*.test.ts` albo asercja w E2E na scenariuszu).
- **Decision**: FIXED — `reportChartBars` w `resources/js/lib/report-chart.ts` (+ `report-chart.test.ts`, czerwony → zielony), `ReportBarChart.vue` z niego korzysta; ścieżka dopisana do `.ai/rules/reports.md`, indeks przegenerowany

### F3 — Nieplanowana zależność `cn` z zakresem `^`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: package.json (`"cn": "^0.5.2"`); resources/js/components/ui/chart/ChartContainer.vue:4, ChartLegendContent.vue:3, ChartTooltipContent.vue:4
- **Detail**: Plan (Faza 3 §2) dodaje tylko `@unovis/vue` i `@unovis/ts` w dokładnych wersjach. CLI dołożyło pakiet `cn` (0.x, `^`) i trzy pliki `ui/chart` importują `cn` z `"cn"`. Wszystkie pozostałe prymitywy `ui/*` biorą `cn` z `@/lib/utils`, a `components.json` ma alias `"utils": "@/lib/utils"`. W aplikacji są więc dwie implementacje łączenia klas. ADR-0071:11 odnotowuje pakiet, ale go nie przypina.
- **Fix A ⭐ Recommended**: Przepiąć trzy importy na `@/lib/utils`, usunąć `cn` (`npm uninstall cn`) i poprawić zdanie w ADR-0071.
  - Strength: Jedna implementacja `cn`, zgodna z aliasem z `components.json`, czyli tym, co CLI miało wygenerować. Jedna zależność mniej.
  - Tradeoff: Jednorazowa edycja wygenerowanych plików `ui/chart` (podmiana aliasu, nie zmiana pod jeden ekran). Ponowne `shadcn add chart` znów ją nadpisze.
  - Confidence: HIGH — 78 importów w `ui/*` używa `@/lib/utils`.
  - Blind spot: Nie sprawdziłem, czy `cn` z pakietu różni się zachowaniem od `twMerge(clsx())`.
- **Fix B**: Zostawić pakiet, przypiąć `"cn": "0.5.2"` i dopisać w ADR-0071 uzasadnienie.
  - Strength: Pliki `ui/chart` zostają dokładnie takie, jak z CLI.
  - Tradeoff: Dwie implementacje tej samej funkcji i zależność 0.x z `engines: node >=22`.
  - Confidence: MED — działa dziś, ale utrwala niespójność.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — trzy importy w `ui/chart` na `@/lib/utils`, `npm uninstall cn` (lockfile: tylko wpis `cn`), ADR-0071:13 opisuje podmianę; build, vue-tsc i check zielone

### F4 — Linie siatki nie używają tokenu `track`; w trybie ciemnym jasne

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/ReportBarChart.vue:91-98; context/foundation/design-system.md:159
- **Detail**: Sekcja „Wykresy” design systemu mówi: `track` to „tło pasa i linie siatki”. `VisAxis :grid-line="true"` rysuje siatkę zmienną Unovis `--vis-axis-grid-color: #e8e9ef`, której nic nie mapuje na `--track`. Ciemne zmienne Unovis włączają się tylko pod `body.theme-dark`, a aplikacja przełącza motyw klasą `.dark`, więc w trybie ciemnym siatka zostaje jasnoszara na ciemnym `card`. Ręczne 3.7 sprawdziło kontrast słupków, nie siatki.
- **Fix**: W `ReportBarChart.vue` (nie w `ui/chart`) ustawić na kontenerze `--vis-axis-grid-color: var(--track)` i sprawdzić kitchen sink w obu trybach.
- **Decision**: FIXED (inaczej, po korekcie diagnozy) — diagnoza była błędna: linie siatki Unovis to `g.tick line` w grupie siatki, a wygenerowany `ChartContainer` maluje je `!stroke-border/50`, więc siatka już jest tokenem i działa w trybie ciemnym (zmierzone na `/dev/ui/reports`: jasny `#d7dde6`/50, ciemny `#223047`/50; zrzuty czytelne). Zmiana `var(--track)` nic nie dawała (przegrywa z `!important`) i została wycofana. Poprawiony dokument: `design-system.md` „Wykresy” — `track` = tło pasa, `border` w 50% = linie siatki z `ChartContainer`.

### F5 — Brak testu, że „zarchiwizowana” to archiwum linii, nie kategorii

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/PeriodReportTest.php:124-125, 195
- **Detail**: Kod jest poprawny (`'is_archived' => $line->isArchived()`, `PeriodReport.php:166`), ale oba przypadki z archiwum archiwizują linię i kategorię naraz. Test przeszedłby także wtedy, gdyby kod czytał `archived_at` kategorii, a plan i `.ai/rules/categories.md` („Archive has two levels”) wymagają linii.
- **Fix**: Dodać przypadek w `PeriodReportTest`: kategoria aktywna z linią zarchiwizowaną → `is_archived: true`, oraz kategoria zarchiwizowana z aktywną linią roku → `false`.
- **Decision**: FIXED — test „the archived mark comes from the budget line of the period, not from the list category”; mutacja na `archived_at` kategorii daje 1 failed / 12 passed, po przywróceniu 13 passed na SQLite i PostgreSQL

### F6 — Pusta sekcja pokazuje nagłówek i „Razem 0,00”; `reports.section_empty` nieużywany

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ReportEntryTable.vue; lang/pl/ui.php:406
- **Detail**: Gdy jeden typ ma wiersze, a drugi nie (fixture `refundReport`: `income: []`), tabela przychodów ma nagłówek, zero wierszy i „Razem 0,00”. Klucz `reports.section_empty` („Brak pozycji w tym okresie.”) jest zdefiniowany, ale nic go nie czyta (jedyne użycie `section_empty` to `t.categories` w `Categories.vue:400`).
- **Fix**: Przy `entries.length === 0` pokazać `t.reports.section_empty` zamiast pustej tabeli, jak w `Categories.vue:400`.
- **Decision**: FIXED — `ReportEntryTable.vue`: tabela i stopka tylko przy wierszach, inaczej linia `t.reports.section_empty` (klasy jak `Categories.vue:393-401`); check:ui, vue-tsc, ReportsHardcodedUiTest zielone

### F7 — Licznik kategorii w wierszu grupy bez podpisu

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ReportGroupRow.vue:57
- **Detail**: `<Badge variant="neutral">{{ group.categories.length }}</Badge>` to goła liczba, nieopisana w planie. Wzorzec `BudgetCategoryGroupRow.vue:93` ma `:title="t.budget.group_category_count"`.
- **Fix**: Dodać `:title` (i ten sam tekst dla czytnika) z klucza w `lang/pl/ui.php`, jak w Budżecie.
- **Decision**: FIXED — `reports.group_category_count` („Liczba kategorii w grupie”) w `lang/pl/ui.php` i `:title` na Badge w `ReportGroupRow.vue`, jak `BudgetCategoryGroupRow.vue:93`; UiTranslationKeysTest i ReportsHardcodedUiTest zielone

### F8 — Kitchen sink nie pokazuje przycisku „Przejdź do budżetu” z pustych stanów

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/ReportsKitchenSink.vue:110-120; resources/js/pages/Reports.vue:72-91
- **Detail**: Strona ma w stanach „brak budżetu” i „budżet w przygotowaniu” przycisk „Przejdź do budżetu”. Kitchen sink odtwarza markup strony ręcznie i go pomija, więc te scenariusze nie są tym, co widzi użytkownik (AGENTS.md: każdy stan widoku w kitchen sinku).
- **Fix**: Wydzielić puste stany do jednego komponentu używanego przez stronę i kitchen sink (albo dodać przycisk w kitchen sinku).
- **Decision**: FIXED — nowy `ReportEmptyState.vue` (`kind: 'no-budget' | 'draft'`, z „Przejdź do budżetu”) w `Reports.vue` i `dev/ReportsKitchenSink.vue`; plik dopisany do `check-ui-literals.mjs` i `ReportsHardcodedUiTest`; E2E 5 passed

## Triage summary

| Decyzja | Ustalenia |
|---|---|
| Fixed | F1 (Fix A, GitHub #223), F2, F3 (Fix A), F4 (po korekcie diagnozy: wycofana zmiana kodu, poprawiony `design-system.md`), F5, F6, F7, F8 |
| Skipped | — |
| Accepted | — |

Weryfikacja po poprawkach: `report-chart.test.ts` 3 passed (czerwony przed poprawką), `PeriodReportTest` 13 passed na SQLite i PostgreSQL (mutacja → 1 failed), raporty na PostgreSQL 30 passed, E2E `reports.spec.ts` 5 passed, `npm run check`, `check:ui`, `vue-tsc` zielone, zrzuty `/dev/ui/reports` (siatka jasny/ciemny, pusta sekcja, puste stany, brak wykresu przy kwotach ≤ 0).
