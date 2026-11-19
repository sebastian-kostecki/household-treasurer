<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Import wyciągu z przełomu przy szkicu roku

- **Plan**: context/changes/import-across-draft-year/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-19
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Success Criteria

- PostgreSQL (`testing_import_across_draft_year`): CsvImportTest, ImportIntegrityTest, YearTurnTest, SheetAccountTest, ImportPreviewHardcodedUiTest — 148 passed, 2 skipped (ImportIntegrityTest: prywatne fixture'y wyciągów poza gitem, sprzed tej zmiany).
- Nazwa `test_statement_across_the_year_turn_is_refused_as_a_whole_while_the_new_year_is_a_draft_known_gap` nie występuje w `tests/`.
- `npm run test` 749/749, `types:check`, `check`, `check:ui` — przechodzą.
- Pint `--test` na zmienionych plikach PHP — passed; `vendor/bin/sloppy ci` — passed (3 medium, nic od high).
- `composer ci:check` na `testing_import_across_draft_year` — exit 0: 1870 passed, 4 skipped (PHP), Vitest 749/749.
- Manual 3.5–3.9 — nieodhaczone, oczekują (brak pozornych odhaczeń).

## Findings

### F1 — Odnośnik „Przejdź do Budżetu :year” nie wygląda jak odnośnik Atramentu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ImportPreviewRow.vue:752
- **Detail**: `<Link>` ma tylko `cursor-pointer text-left underline`: bez `text-brand-text` (js.md: „brand-text = links”), bez widocznego focusu (`TextLink` ma `focus-visible:ring-2 ring-ring`, `.report-name-link` ma `focus-visible:outline-ring`) i bez wyglądu stanu `pending`. Zamiana na `TextLink` wyjęłaby plik spod strażnika `ui-contract.test.ts:589` (szuka literału `<Link`), więc odnośnik musi zostać `<Link>`.
- **Fix**: Zostaw `<Link>` i daj mu wygląd `TextLink` (`text-brand-text font-medium underline-offset-4 hover:underline rounded-xs outline-none focus-visible:ring-2 focus-visible:ring-ring`) albo nazwaną klasę w `app.css`; sprawdź w `/dev/import-preview` (3.5).
- **Decision**: FIXED — `<Link>` dostał wygląd `TextLink` (`text-brand-text`, `font-medium`, `hover:underline`, `focus-visible:ring-2 ring-ring`, `aria-busy:cursor-wait`); plik dalej pod strażnikiem ui-contract.

### F2 — Notatka roku i powód blokady pojawiają się, gdy to nie rok blokuje wiersz

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/import-preview-rows.ts:257-262 (i :244)
- **Detail**: Dla ruchu kapitału notatka liczy się, gdy `parsedInterestAmountMinorUnits(selection) !== null`, także dla odsetek 0, ujemnych, ≥ kwoty z pliku albo przy wierszu obciążenia. Serwer takiego podziału nie doprowadza do kontroli roku (`CapitalMovementInterestImportValidator` odrzuca go wcześniej), a klient uznaje go za niepoprawny w `interestSplitValid` (`:296-302`). W roku-szkicu pasek pokazuje wtedy `import_block_budget_year_not_in_execution` (ma pierwszeństwo przed `import_block_incomplete_rows`), więc użytkownik słyszy „rozpocznij realizację”, a po starcie realizacji wiersz dalej blokuje innym powodem. Podobnie `rowBudgetYearNotice` nie patrzy na `row.valid`: wiersz `FutureDate` z roku bez Budżetu dostaje „Utwórz Budżet :year”, choć utworzenie Budżetu go nie odblokuje (sam zapisu nie blokuje, bo niepoprawnego wiersza nie da się zaznaczyć).
- **Fix**: W `rowBudgetYearNotice` zwróć `null` dla `!row.valid` i licz odsetki ruchu kapitału tylko przy poprawnym podziale (`fileAmount > 0`, `0 < interest < fileAmount`, warunki `interestSplitValid` bez kategorii); dodaj dwa przypadki Vitest.
- **Decision**: FIXED — najpierw 3 czerwone przypadki Vitest (wiersz niepoprawny, odsetki odrzucane przed rokiem, niepoprawny podział → `import_block_incomplete_rows`), potem `rowBudgetYearNotice` zwraca `null` dla `!row.valid` i dla ruchu kapitału bez poprawnego podziału; warunek podziału wydzielony do `interestSplitsFileAmount`, wspólnego z `interestSplitValid`. Istniejące przypadki „z odsetkami” dostały wiersz uznania większy od odsetek (wcześniej obciążenie albo odsetki = kwota, czyli podział, który serwer odrzuca przed rokiem).

### F3 — Każdy wiersz rejestruje własny nasłuch błędu wyboru roku i własne `pending`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/ImportPreviewRow.vue:208-224
- **Detail**: `onUnmounted(listenForBudgetYearSelectionFailure(...))` i `selectYearPending` są w każdym wierszu, także bez notatki szkicu: N wierszy = 2N globalnych `router.on`. `ReportYearsTable.vue:71-83` ma jeden nasłuch i jedno `pending` dla wszystkich odnośników. Skutki są małe: toast pokaże się raz (predykat czyta `pending` swojego wiersza), a w locie odnośniki innych wierszy zostają aktywne, więc może pójść drugi POST `select-year` — na ten sam rok, czyli idempotentny. Wyniesienie nasłuchu na stronę wymaga zmiany strażnika `ui-contract.test.ts:589-612`, który wymaga `listenForBudgetYearSelectionFailure` w tym samym pliku co `<Link>`.
- **Fix A ⭐ Recommended**: Zostaw jak jest; dopisz w komentarzu przy `selectYearPending`, czemu wiersz ma własny nasłuch (strażnik per plik, POST idempotentny)
  - Strength: Zero ryzyka, strażnik zostaje prosty; skutek jest kosmetyczny.
  - Tradeoff: Rozjazd z wzorcem `ReportYearsTable` zostaje.
  - Confidence: HIGH — `select-year` z tym samym rokiem tylko ustawia sesję.
  - Blind spot: Nie mierzono podglądu z setkami wierszy (nasłuchy odpalają się tylko przy `httpException`/`networkError`).
- **Fix B**: Przenieś `pending` i nasłuch do `OperationsImportPreview.vue` (prop + emit do wiersza i sinka) i rozszerz strażnik, by przyjmował nasłuch w stronie renderującej wiersz
  - Strength: Jeden nasłuch, wszystkie odnośniki wyłączone w locie — jak `ReportYearsTable`.
  - Tradeoff: Nowy prop/emit przez stronę i `ImportPreviewSink.vue`, zmiana strażnika z wyjątkiem dla pary plików.
  - Confidence: MED — strażnik trzeba przebudować tak, by nie dało się go obejść.
  - Blind spot: Inne przyszłe komponenty z tym samym podziałem rodzic/dziecko.
- **Decision**: FIXED via Fix A — komentarz przy `selectYearPending` wyjaśnia, czemu wiersz ma własny nasłuch.

### F4 — Scenariusz paska `year-turn-draft` podaje 3 wiersze, sekcja wierszy ma 2 blokujące

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/ImportPreviewSink.vue:92
- **Detail**: `.replace(':count', '3')`, a w scenariuszu wierszy blokują tylko wydatek 2025 i wydatek 2027 (2026 jest w realizacji, przelew własny 2027 nie blokuje). Kitchen sink pokazuje sprzeczne liczby.
- **Fix**: Zmień `'3'` na `'2'`.
- **Decision**: FIXED — `:count` w scenariuszu paska `year-turn-draft` = 2.

### F5 — Luźne końce kontraktu typów: martwy warunek i niepokryta pusta mapa

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/import-preview-rows.ts:276-281; app/Support/CsvImport/ImportBudgetYearStates.php:33
- **Detail**: `selection.type !== undefined` w `rowNeedsBudgetInExecution` nigdy nie jest fałszem (`RowSelection.type` jest wymagane, `defaultRowType` zawsze daje dochód albo wydatek); testy dochodzą do „typu niewybranego” tylko przez `as never`. To wada planu (kontrakt fazy 2), nie implementacji. Pusta mapa idzie jako JSON `[]`; klient obsługuje to `Array.isArray`, ale żaden `assertInertia` nie przypina tego kształtu.
- **Fix**: Usuń martwy warunek i przypadki `as never`; dodaj `assertInertia` dla podglądu bez dat (`budgetYearStates` = `[]`) albo zwracaj `(object) []`.
- **Decision**: FIXED — usunięty martwy warunek `selection.type !== undefined` i przypadki `as never`; nowy test `test_empty_import_preview_sends_the_budget_year_states_as_an_empty_list` przypina `budgetYearStates` = `[]` (wiersz bez daty odpada przed podglądem w `isOperationShapedRow`, więc pusta mapa zdarza się tylko przy pustym podglądzie).
