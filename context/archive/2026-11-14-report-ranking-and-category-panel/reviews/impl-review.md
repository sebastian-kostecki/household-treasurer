<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Ranking kategorii i panel kategorii

- **Plan**: context/changes/report-ranking-and-category-panel/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 7 observations

## Verification run (2026-11-14)

- `composer ci:check` w obrazie `sail-8.5/app` na PostgreSQL (`testing_report_ranking`): exit 0. Vitest 710/710, Pest 1731 passed, 2 skipped.
- `artisan test --group=nfr tests/Feature/Nfr/NfrPerfReportTest.php` (PostgreSQL): 2 passed.
- `vendor/bin/sloppy ci` (w obrazie Sail, host ma PHP 8.3): Score 100, exit 0. Jedno znalezisko MEDIUM jest w `MonthSheetReader.php`, którego ta zmiana nie dotyka.
- `npx playwright test` dla `reports.spec.ts`, `reports-view.spec.ts` i `reports-category-panel.spec.ts` (port 8137, `e2e_report_ranking`): 28 passed.
- Wiersze Manual 1.6, 2.8, 2.9 i 3.6–3.9 odhaczył commit 1fec354 („po przeglądzie użytkownika na podglądzie”). Wiersz 3.8 przeczy kodowi (F1).
- Poza zakresem: `context/foundation/roadmap.md` ma niezacommitowaną zmianę (S-35 `proposed` → `in-progress`).

## Triage (2026-11-14)

Użytkownik: „napraw samodzielnie według rekomendacji”. Wszystkie 10 ustaleń poprawione. Po poprawkach: `composer ci:check` na PostgreSQL exit 0 (Vitest 715/715, Pest 1731 passed, 2 skipped), Sloppy 100 bez nowego znaleziska, E2E Raportów i paska bocznego 31/31.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — Zamknięcie arkusza gubi fokus (wiersz Manual 3.8 odhaczony)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/pages/Reports.vue:516-547
- **Detail**: Arkusz jest sterowany (`:open="sheetOpen"`) i nie ma `SheetTrigger`. Przy zamknięciu reka-ui wywołuje `preventDefault()` i fokusuje `rootContext.triggerElement` (`node_modules/reka-ui/dist/Dialog/DialogContentModal.js:70-74`). To pole ustawia tylko `DialogTrigger`, więc tu jest puste i fokus spada na `<body>`. Wiersz 3.8 („fokus wraca na nazwę”) jest odhaczony, ale w kodzie nie ma niczego, co by fokus przywróciło. Żaden test E2E nie sprawdza fokusu po zamknięciu.
- **Fix**: W `selectCategory` zapamiętać kliknięty przycisk nazwy. W `@close-auto-focus` na `SheetContent` wywołać `preventDefault()` i sfokusować ten przycisk. Dopisać do testu E2E (c) asercję `toBeFocused()` i ponownie sprawdzić wiersz 3.8.
- **Decision**: FIXED — zamknięcie zapamiętuje pokazaną kategorię, `@close-auto-focus` fokusuje jej nazwę (`[data-report-select="<id>"]`); E2E (c) obu wariantów sprawdza `toBeFocused()` (czerwony → zielony).

### F2 — Zmiana roku pada z 422 przy `?category=0` albo zbyt długim id

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetYearSwitcher.vue:149-154
- **Detail**: Klient wysyła `report_category`, gdy wartość pasuje do `/^\d+$/`, przez `Number(category)`. Serwer waliduje `['sometimes', 'integer', 'min:1']` (`SelectBudgetYearRequest.php:28`). Dlatego `/reports?category=0` albo ciąg ponad 18 cyfr (staje się `1e20`) daje 422: menu pokazuje błąd, a rok się nie zmienia. Sama strona Raportów te wartości po cichu ignoruje. `BudgetTest` sprawdza tylko `'abc'`.
- **Fix**: W kliencie wysyłać `report_category` tylko dla `/^[1-9]\d{0,17}$/` (ten sam zakres co `ReportCategoryPanel::requestedCategoryId`) i dopisać test dla `category=0`.
- **Decision**: FIXED — `reportYearSwitchParams` w `lib/budget-year-menu.ts` (do 18 cyfr, ≥ 1, wysyłane jako cyfry bez zer wiodących, bo `integer` odrzuca „007”, a Number zaokrągla długie id); Vitest czerwony → zielony.

### F3 — Arkusz ma dwa przyciski zamykania, wbudowany z angielskim „Close”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/ui/sheet/SheetContent.vue:53-59, resources/js/pages/Reports.vue:541-545
- **Detail**: Plan (Faza 3 §3) chciał własnego `SheetClose` z polską etykietą. Ten przycisk jest („Zamknij panel kategorii”), ale `SheetContent` zawsze dodaje swój `DialogClose` X z sr-only „Close”. W polskim UI arkusz ma więc dwie kontrolki zamykania, jedną z angielską nazwą. `Reports.vue` jest jedynym miejscem poza `components/ui`, które używa `SheetContent`.
- **Fix A ⭐ Recommended**: Dodać do `ui/sheet/SheetContent.vue` ogólny prop (np. `showCloseButton`, domyślnie `true`, jak w nowszym shadcn) i wyłączyć wbudowany X w Raportach.
  - Strength: Ogólny prop, a nie edycja pod jeden ekran, więc mieści się w zasadzie „components/ui never edited for a single screen”. Jest odporny na zmiany struktury DOM.
  - Tradeoff: Dotyka komponentu współdzielonego; trzeba zaktualizować kitchen sink lub notatkę w design-system.md.
  - Confidence: MED — wzorzec znany z upstream shadcn, ale nie sprawdziłem wersji shadcn-vue w repo.
  - Blind spot: Czy `ui/sidebar` (mobilny Sheet) korzysta z tego samego X i czy też potrzebuje polskiej etykiety.
- **Fix B**: Ukryć wbudowany X selektorem w `app.css` (`.report-panel-sheet > button:last-child` albo podobnie).
  - Strength: Zero zmian w `components/ui`.
  - Tradeoff: Selektor zależy od struktury DOM komponentu i pęknie po aktualizacji shadcn-vue.
  - Confidence: MED — działa dziś, kruchy w przyszłości.
  - Blind spot: Reguła `css.md` może nie dopuszczać selektorów strukturalnych.
- **Decision**: FIXED via Fix A — `ui/sheet/SheetContent.vue` dostał ogólny prop `showCloseButton` (domyślnie true, wzorem `ui/dialog/DialogContent.vue`), Raporty go wyłączają; E2E (c): zero przycisków „Close”, jeden „zamknij”. Mobilny Sidebar dalej ma angielskie sr-only „Close” (poza zakresem).

### F4 — Podczas zamykania arkusz pokazuje inną kategorię

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Reports.vue:226-233
- **Detail**: `onSheetOpenChange(false)` od razu wywołuje `setRequestedCategory(null)`, więc `selected` wraca do pierwszego wydatku. `Sheet` zostaje zamontowany (`v-if` dalej true), więc przez ~300 ms animacji wysuwania widać kategorię domyślną. Wniosek z kodu, nie z przeglądarki.
- **Fix**: Zamrozić wyświetlaną kategorię na czas zamykania (np. kopia `selected` przy otwarciu) albo zdjąć `category` z adresu po animacji.
- **Decision**: FIXED — `closingSheetCategory` zamraża kategorię arkusza na czas wysuwania; E2E (c) sprawdza treść zamykanego arkusza, jeśli jest jeszcze w DOM.

### F5 — `aria-controls` wskazuje nieistniejący element przy zamkniętym arkuszu

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ReportRankingRow.vue:129
- **Detail**: W układzie `sheet` każdy przycisk nazwy ma `aria-controls="report-category-panel"`, ale element o tym id istnieje tylko przy otwartym arkuszu (Reports.vue:514-547).
- **Fix**: Wiązać `aria-controls` tylko wtedy, gdy panel jest w DOM (układ `aside` albo otwarty arkusz).
- **Decision**: FIXED — `panel-id` (`aria-controls`) tylko gdy panel jest w stronie (`panelInPage`); E2E (c) czerwony → zielony.

### F6 — `String.replace` ze stringiem psuje nazwy zawierające `$`

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ReportCategoryPanel.vue:159, resources/js/lib/report-category-panel.ts:177
- **Detail**: `.replace(':name', props.category.name)` i `labels.group.replace(':name', groupName)` interpretują `$&`, `$'` i `` $` `` w nazwie kategorii lub grupy. Istniejący kod robi to bezpiecznie funkcją: `lib/report-category-operations.ts:80`, `Categories.vue:310`.
- **Fix**: Przekazać funkcję: `.replace(':name', () => name)`.
- **Decision**: FIXED — `replace` z funkcją w `reportPanelCaption` i nowym `reportPanelOperationsLabel`; Vitest z `$&` czerwony → zielony.

### F7 — Kliknięcia w kitchen sinku przechodzą na prawdziwe `/reports`

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/Reports.vue:77-91 (`replaceInPlace`), resources/js/pages/dev/ReportsKitchenSink.vue
- **Detail**: Sink renderuje kilka instancji `Reports.vue`. `setRankingMode` i `setRequestedCategory` robią `router.replace` na trasę `reports`, więc klik w sinku zmienia adres na `/reports?...` i scala propsy ze stroną sinka. Instancja na fixtures się nie zmienia, a odświeżenie opuszcza sink. Wszystkie instancje dzielą też `id="report-category-panel"`. Dotyczy tylko środowiska dev.
- **Fix**: Zapisać w `stateMatrix` sinka, że scenariusze Rankingu i panelu są statyczne, albo wstrzykiwać aktualizację adresu (prop, który sink nadpisuje).
- **Decision**: FIXED — `stateMatrix` sinka opisuje, że scenariusze są statyczne, a klik robi wizytę na /reports; wspólne id odnotowane.

### F8 — Testy E2E (a), (b), (e) słabsze niż ich opis w planie

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/reports-category-panel.spec.ts:134, 168-187, 210-212
- **Detail**:
  - (b) porównuje sumę Operacji z kwotą panelu tylko bez paginacji i na wartościach bezwzględnych.
  - (e) dowodzi „arkusz ani razu się nie pojawia” przez `waitForTimeout(500)`.
  - (a) ma komentarz „bez przeładowania”, ale go nie sprawdza.
  - Wszystkie przechodzą (28/28). Kod zapobiega (e) strukturalnie, bo `Sheet` renderuje się tylko w układzie `sheet`.
- **Fix**: W (a) ustawić znacznik na `window` przed klikiem i sprawdzić, że przetrwał. W (b) wybrać kategorię mieszczącą się na jednej stronie albo czytać sumę z nagłówka listy.
- **Decision**: FIXED — (a) sprawdza znacznik na `window` (bez przeładowania); (b) przy paginacji robi jawny `test.skip` z powodem zamiast cichego przejścia; (e) bez zmian (kod wyklucza arkusz strukturalnie).

### F9 — Drobne odejścia od słów planu

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/Reports.vue:167-181, resources/css/app.css:1989-2000, resources/js/components/ReportCategoryPanel.vue:130-140
- **Detail**:
  - (1) Arkusz otwiera się automatycznie przy każdym przejściu w `sheet` z `requested_category_id`, także przy zwężeniu okna z `aside`. Plan mówił tylko o przejściu z `unknown`.
  - (2) Gdy miesiąc w toku jest też najwyższy, jego kreskowanie dostaje `foreground` zamiast koloru typu. Plan nie rozstrzyga, który stan wygrywa.
  - (3) „Najwięcej” pokazuje miesiąc i kwotę; plan wymienia tylko miesiąc.
  - Wszystkie trzy wyglądają na rozsądne.
- **Fix**: Dopisać do planu (albo do `.ai/rules/reports.md`) jednozdaniowy addendum, które zatwierdza te trzy zachowania, albo zawęzić (1) do przejścia z `unknown`.
- **Decision**: FIXED — addendum w planie (`## Addendum (impl-review 2026-11-14)`) zatwierdza trzy zachowania; kod bez zmian.

### F10 — Porządki: nieaktualny nagłówek reguły i niejawny `keepMonths`

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .ai/rules/reports.md:27, app/Support/PeriodReport.php (`build`, `count($months) === 12`)
- **Detail**:
  - Nagłówek sekcji reguły brzmi dalej „The Reports page is tables on the `.report-grid`”, choć treść opisuje już Ranking.
  - `PeriodReport::build` wnioskuje, czy zachować miesiące, z liczby miesięcy (`count($months) === 12`) zamiast z jawnej flagi z `forYear`.
- **Fix**: Zmienić nagłówek na „The Reports page is the Ranking on the `.report-grid`…”. Przekazać `keepMonths: true` jawnie z `forYear`.
- **Decision**: FIXED — nagłówek reguły „The Reports page is the Ranking on the `.report-grid`…”; `PeriodReport::build` dostaje `keepMonths` jawnie z `forMonth`/`forYear`.
