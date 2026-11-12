<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Z raportu do operacji — przejście z kategorii do jej operacji w okresie

- **Plan**: context/changes/report-to-operations/plan.md
- **Scope**: Full plan (Phase 1 and 2 of 2)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-12
- **Verdict**: APPROVED
- **Findings**: 0 critical 2 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Verification run: Vitest (funkcja celu 8/8; całość 53 pliki, 601 testów) OK; `npm run types:check` OK; `npm run check:ui` OK; PostgreSQL (`testing_report_ops`, dropnięta po teście): `ReportToOperationsTest`, `PeriodReportTest`, `ReportsPageTest`, `ReportsHardcodedUiTest` — 53 testy, 838 asercji OK. `npm run build` nie przeszedł na hoście (PHP 8.3 < wymagane 8.4.1 dla `wayfinder:generate`) — to ograniczenie środowiska, nie kodu; pełna bramka `composer ci:check` i `sloppy ci` nie były uruchamiane w tym przeglądzie (zaznaczone w planie jako wykonane w 825ad6c).

## Findings

### F1 — Podstawianie `:name`/`:period` przez `String.replace` jest niebezpieczne dla nazw użytkownika

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ReportCategoryRow.vue:55-58, resources/js/components/ReportTrendTable.vue:63-67
- **Detail**: Nazwa kategorii jest argumentem zastępującym w `.replace(':name', name)`, więc `$&`, `$'`, `` $` ``, `$$` w nazwie są interpretowane (nazwa `A$&B` daje „A:nameB”). Drugie `.replace(':period', …)` biegnie po łańcuchu zawierającym już nazwę, więc nazwa z tekstem `:period` zabiera podstawienie. Skutek ogranicza się do dostępnej nazwy linku (nie XSS — Vue escapuje). Ten sam wzorzec jest w repo w ~63 miejscach, więc to nie regresja, ale nowy kod go kopiuje i duplikuje logikę etykiety w dwóch komponentach.
- **Fix**: Wydziel jeden helper (np. `reportOperationsLinkLabel(template, name, period)` w `report-category-operations.ts`), użyj funkcji zastępujących `() => value` i podstawiaj `:period` przed `:name`; dodaj test z nazwą `$&` i `:period`.
- **Decision**: FIXED — helper `reportOperationsLinkLabel` (jedno przejście, funkcja zastępująca) w `report-category-operations.ts` z testem dla `$&` i `:period`; użyty w `ReportCategoryRow.vue` i `ReportTrendTable.vue`

### F2 — `categoryHref` zwraca pusty `href` dla `null` w Trendzie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ReportTrendTable.vue:50-58
- **Detail**: Gdy `reportCategoryOperationsQuery` zwróci `null`, funkcja zwraca `''`, a `<Link>` nie ma `v-if` — powstałby link do bieżącej strony (ciche przeładowanie). Dziś gałąź jest nieosiągalna (`ReportTrendCategory` nie ma `has_operations`, `ReportTrend::rows()` pomija kategorie bez operacji w roku), ale odstaje od wzorca `ReportCategoryRow` (`v-if` + chip bez linku) i ukrywa ewentualną przyszłą regresję.
- **Fix**: Renderuj `<Link>` tylko gdy query nie jest `null` (jak w `ReportCategoryRow`), z gołym `CategoryChip` w `v-else`.
- **Decision**: FIXED — `ReportTrendTable.vue` renderuje `<Link>` tylko przy niepustym query, w przeciwnym razie goły `CategoryChip` (jak w `ReportCategoryRow`)

### F3 — `aria-label` nadpisuje nazwę nagłówka wiersza w Trendzie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ReportTrendTable.vue (oba linki w slocie `rowheader`)
- **Detail**: „Label in name” jest spełnione (widoczna nazwa zawiera się w etykiecie), ale nazwa `rowheader` liczy się z zawartości, więc czytnik ogłasza „Operacje kategorii Jedzenie, 2026” jako nagłówek wiersza przy każdej komórce. Plan i ręczna kontrola 2.8 tego wprost oczekują, więc to świadomy kompromis.
- **Fix**: Opcjonalnie zostaw widoczną nazwę jako nazwę linku, a cel i okres dodaj przez `sr-only`/`aria-describedby`. Albo zaakceptuj.
- **Decision**: ACCEPTED — plan i punkt 2.8 oczekują etykiety „Operacje kategorii …”; label-in-name spełnione

### F4 — Ręczne punkty 2.5–2.8 odhaczone bez śladu w diffie; ring fokusu w przewijanym Trendzie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/report-to-operations/plan.md (Progress 2.5–2.8), resources/css/app.css:1586-1593
- **Detail**: Punkty ręczne są `[x]` z sha 825ad6c, ale diff nie zawiera dowodu (screenshot/notatka), a przegląd nie może ich zweryfikować. `.report-name-link` ma zewnętrzny `outline-offset-2`, w odróżnieniu od wewnętrznego `-outline-offset-2` w `.category-meter-row-link`; w Trendzie link leży w `overflow-x-auto` i przypiętej komórce, więc zewnętrzna obwódka może się przyciąć przy skrajnych wierszach.
- **Fix**: Potwierdź wizualnie w `/dev/ui/reports` fokus Tab na pierwszym i ostatnim wierszu Trendu (jasny i ciemny motyw); w razie przycięcia przejdź na `-outline-offset-2`.
- **Decision**: SKIPPED — wymaga wizualnej kontroli Tab w `/dev/ui/reports` (pierwszy i ostatni wiersz Trendu, oba motywy); do wykonania ręcznie, w razie przycięcia przejść na `-outline-offset-2`
