<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kategorie w zakładkach z filtrami

- **Plan**: context/changes/categories-tabs-and-filters/plan.md
- **Scope**: Full plan (phases 1-5 of 5; Manual steps 1.3, 4.6, 5.4, 5.5 still pending)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-14
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verification run

| Check | Result |
|-------|--------|
| 1.1 `ls context/foundation/adr \| grep -c '^0097-'` | 1 — pass |
| 1.2 `grep 'Archiwum (n)' design-system.md` | no output — pass |
| 4.4 `grep -rn CategoryArchiveSection resources tests scripts` | no output — pass |
| `npx vitest run` | 61 files, 695 tests — pass |
| `npm run check:ui` | exit 0 — pass |
| `npm run types:check` | exit 0 — pass |
| `vp check` (format + lint, by review agent) | pass |
| PostgreSQL: CategoriesPageTest, CategoriesKitchenSinkTest, CategoriesHardcodedUiTest, CategoryGroupTest | 37 passed |
| `composer ci:check` (PostgreSQL, `testing_categories_tabs_and_filters`, container) | exit 0 — 1710 passed, 2 skipped, PHPStan no errors |
| `vendor/bin/sloppy ci` (host) | pass; 1 MEDIUM in untouched `MonthSheetReader.php` |
| E2E `tests/e2e/categories.spec.ts` | not re-run (needs running app; CI skips it) |

Manual (Progress): 1.3, 4.6, 5.4, 5.5 are unchecked — treated as pending; none is ticked without evidence.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Scope notes: changes to the `collected-year-start`, `irregular-collected`, `month-plan-groups` and `reports` E2E specs are required by the `data-section` → `data-tab` rename and the single "Dodaj kategorię" button, not extra scope. The uncommitted `roadmap.md` change (S-37 `ready` → `in-progress`) is outside the reviewed commits. Data-loss path checked: `saveTree`/`moveGroup` read only the full `trees`, `draggable` is disabled and handles/move items are hidden under an active filter — no gap found.

## Findings

### F1 — Group actions in the Income tab redirect to Expense

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/CategoryGroupController.php:115
- **Detail**: `done()` returns `to_route('categories.index')` without `tab`. It is used by `store`, `update`, `destroy` and `assign` (lines 37-70). The server then returns `tab = 'expense'` and the watch on `props.tab` in `Categories.vue` switches the view. In the Income tab "Dodaj grupę", rename, delete and "Zmień grupę…" throw the user onto Expenses, and a freshly added income group disappears from view. Other writes on the page (archive, restore, merge, `CategoryListController` store/update/destroy) use `back()` and keep the tab. Neither the Feature tests nor E2E cover a group action outside Expenses. The redirect existed before, but the tab made it a regression for this view (ADR-0097: the tab is the only state in the address).
- **Fix**: Make `done()` return `back()` like the neighbouring controllers, and add a Feature test (group created from `?tab=income` redirects back to it) plus an E2E step for an income group.
  - Strength: Matches `CategoryListController`; one line in one place covers all four actions.
  - Tradeoff: `back()` depends on the Referer; a direct non-browser call falls back to the previous URL — acceptable.
  - Confidence: HIGH — behaviour read in code; `back()` pattern already used on the same page.
  - Blind spot: Group forms opened from other pages were not checked (a group can only be created here).
- **Decision**: FIXED — `done()` returns `back(fallback: route("categories.index"))`; new test `test_a_group_write_returns_to_the_tab_it_came_from` in CategoryGroupTest (E2E step for an income group not added)

### F2 — `changeTab` sets local state before the visit and never reconciles it

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Categories.vue:152-168
- **Detail**: `activeTab.value = value` is set immediately and `router.get(..., { replace, only: ['tab'] })` has no `onCancel`/`onError`/`onNetworkError`. The watch on `props.tab` fires only when the prop changes. If the visit is cancelled (e.g. `router.reload` from `saveTree` right after a drop, `Categories.vue:450`) or fails, the visible tab and the URL/`props.tab` diverge until the next change. Review-agent confidence: low (narrow window). Related, unverified: the same `changeTab` runs in the `/dev/ui/categories` sink and appears to navigate to the real `/categories?tab=…`, leaving the sink; the plan does not cover it.
- **Fix**: In `onCancel`/`onError` set `activeTab` back from `props.tab`; if the sink really navigates away, skip the visit when the page is rendered by the sink (e.g. an `interactive`/preview prop) and keep the local state only.
  - Strength: Keeps instant switching and removes the only state that can drift from the URL.
  - Tradeoff: A guard for the sink adds a prop to the page.
  - Confidence: MED — race reasoned from Inertia semantics, not reproduced; sink navigation not clicked.
  - Blind spot: Neither the race nor the sink behaviour was reproduced in a browser (manual step 5.4 would show the second).
- **Decision**: FIXED — `changeTab` reconciles `activeTab` with `props.tab` in onCancel/onError/onNetworkError (only while that click is still the latest); new `localTabs` prop, set by the kitchen sink, skips the visit

### F3 — Search term inserted with `String.replace` string replacement

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/Categories.vue:291
- **Detail**: `no_results_query.replace(':query', query.value.trim())` interprets `$&`, `$1`, `` $` `` in the user's text, so a phrase like `$&x` corrupts the message. Display only; not XSS (`{{ }}` escapes). The same pattern exists in older aria-labels (`CategoryListRowActions.vue:75`).
- **Fix**: Use a replacer function: `.replace(':query', () => query.value.trim())`.
- **Decision**: FIXED — replacer function in `noResultsText`

### F4 — Editorial leftovers from the rewrite

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/CategoriesPageTest.php:281-284; context/foundation/design-system.md:120; lang/pl/ui.php:672; tests/e2e/month-plan-groups.spec.ts:~101, tests/e2e/reports.spec.ts:~199,~356; resources/js/lib/categories-fixtures.ts:608
- **Detail**: (a) a copied docblock about deleting a group sits above `test_the_tab_defaults_to_expense`; the original stays above `test_a_group_counts_its_archived_categories`. (b) design-system.md: the removed sentences about years and the collapsible archive left "`CategoryChip` niesie tożsamość kategorii, Dialog `CategoryListFormDialog`…" glued with a comma. (c) `categories.add_label` is now unused (`grep -rn add_label app resources tests lang` finds only its definition). (d) removing `.locator('[data-section="expense"]')` left an empty line inside three `page\n\n.getByRole(...)` chains. (e) the `archive-empty` scenario exists in fixtures and the sink matrix but is not listed in design-system.md.
- **Fix**: Delete the stray docblock and `add_label`, split the design-system sentence at the right place, close the three blank lines, and add `archive-empty` to the scenario list in design-system.md.
- **Decision**: FIXED — stray docblock, `add_label`, design-system sentence, blank lines in 3 E2E chains, `archive-empty` listed

### F5 — Rhythm filter leaks across tabs and skips archived income

- **Severity**: 👁 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/Categories.vue (state `rhythm`), resources/js/lib/category-list-filter.ts (`categoryMatches`, `tabCounts`)
- **Detail**: A rhythm picked in Expenses stays in state after switching to Income, where the chips are hidden (`v-if="activeTab !== 'income'"`); the Expense counter keeps using it with no visible cue. In Archive the rhythm filter narrows only expenses, so archived income always passes — with a rhythm set and only archived income present, "Brak kategorii o wybranym rytmie." never appears. Both follow the plan and ADR-0097 literally; nothing is lost.
- **Fix**: Accept as designed and note it in ADR-0097, or show the active filters in the toolbar of every tab.
  - Strength: Accepting costs nothing and matches the decided behaviour.
  - Tradeoff: Showing the filter in every tab adds UI the card `PageCategories` does not have.
  - Confidence: MED — behaviour read in code, not seen in the browser.
  - Blind spot: How this feels with real data (manual step 4.6).
- **Decision**: ACCEPTED — behaviour kept as designed and written into ADR-0097 (Rhythm paragraph)

### F6 — Years strip may not fit on a long axis

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css (`--categories-years-width: 14rem`), resources/js/components/CategoryYearsStrip.vue
- **Detail**: Cells are `flex-1 min-w-0` in a fixed width; with about ten or more budget years the two-digit label stops fitting, and the axis grows with every new Budget year. Fixtures use 2020–2027 (8 years). Not checked visually.
- **Fix**: Add a fixture with a 12-16 year axis to the sink and check both themes and 375 px as part of manual step 5.4.
- **Decision**: FIXED — scenario `long-axis` (2012–2027) added to fixtures, sink and design-system.md; visual check still part of manual step 5.4

### F7 — Every tab switch re-reads the whole list on the server

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/CategoryController.php:41
- **Detail**: `...CategoryListReading::forUser()` is evaluated eagerly, so `only: ['tab']` still runs all queries (categories, rhythms, years, groups) per click. Negligible at household scale; no N+1 introduced.
- **Fix**: Leave as is; wrap the readings in closures/`Inertia::optional` only if the page ever gets slow.
- **Decision**: SKIPPED — not worth changing at household scale
