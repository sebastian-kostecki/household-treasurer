<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Funkcjonalny sidebar i powłoka bez belki

- **Plan**: context/changes/sidebar-action/plan.md
- **Scope**: Full plan (Phase 1–4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-07
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 6 observations

## Verification run

| Check | Result |
|-------|--------|
| `npm test` | 36 files, 378 tests passed |
| `npm run check:ui` | passed |
| `npm run build && npm run types:check` | passed |
| Feature (SQLite, host CLI 8.4): CommandPaletteCategories, AppShell, SidebarKitchenSink, Budget | 76 passed |
| Feature (PostgreSQL, Docker `sail-8.5/app`): CommandPaletteCategories + `tests/Feature/Nfr` | 6 passed |
| `vendor/bin/sloppy ci` (host) | 100/100, 0 findings |
| `git diff` on `package.json`, `package-lock.json`, `ui/dialog`, `ui/sheet`, `ui/sidebar` | empty |
| `.ai/rules/index.md` diff | only the new globs of `js-sidebar.md` |
| `npx playwright test` (full suite) | 18 passed, 1 failed (F6); the failing spec passes alone |
| `composer ci:check` (Docker, PostgreSQL) | 941 passed, 2 skipped (7146 assertions), exit 0 |

Manual items 1.8–1.10, 2.5–2.8, 3.6, 3.8 and 4.9–4.13 carry a commit SHA. Item 3.7 is ticked without one (F8).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — Palette hides categories that load after the user starts typing

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ui/command/Command.vue:44-78, resources/js/components/ui/command/CommandItem.vue:40-45
- **Detail**: Filtering runs only in `watch(() => filterState.search, filterItems)`, while items register their text in `allItems` in `onMounted` and never trigger a re-filter. The palette fetches categories on every open. If the user types right after Ctrl+K and the response arrives later, the new category items have no entry in `filtered.items`, the group is not in `filtered.groups` and stays hidden, and `CommandEmpty` can show "Brak wyników" from a stale count. Categories stay invisible until the next keystroke. The e2e "a category goes to Operations…" waits for the option before `fill`, so it hides this race. Confirmed by reading the code; not reproduced in a browser.
- **Fix**: Re-run `filterItems` when `allItems` changes (watch `allItems.value.size`), or call it after item mount and unmount. Add an e2e that types before a delayed `page.route` response.
  - Strength: A one-place fix in the vendored `Command.vue` that also covers any later consumer.
  - Tradeoff: Touches `ui/command`, which the plan names in ADR-0054 as the place for a11y changes, so the ADR sentence should mention it.
  - Confidence: MEDIUM — the logic is clear in the source, the runtime effect is not reproduced.
  - Blind spot: How reka `ListboxRoot` highlights the first item after a late re-filter.
- **Decision**: FIXED — Fix now: `watch(() => allItems.value.size, filterItems)` in `Command.vue`, e2e "categories that load after the user has typed…" (red without the watch, green with it), deviation added to ADR-0054.

### F2 — "Dodaj rok budżetu" from the palette does nothing when the mobile sheet is closed

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/AppSidebar.vue:91, resources/js/composables/useCommandPalette.ts:97-101
- **Detail**: `CreateBudgetDialog` is mounted inside `<Sidebar>` (AppSidebar.vue:91). On mobile `ui/sidebar/Sidebar.vue` renders its slot in a `Sheet`, so the dialog exists only while the sheet is open. With Ctrl+K on a narrow window with a keyboard and the sheet closed, choosing "Dodaj rok budżetu" sets the shared `open` to true with no mounted dialog. Nothing appears, and the dialog pops up the next time the user opens the menu. The plan asks for one instance "dla powłoki", and the manual check 4.13 covers only desktop. No e2e covers palette → add year, nor palette → add operation while already on Operations.
- **Fix**: Mount `CreateBudgetDialog` once in `AppSidebarLayout`, outside `Sidebar`, keeping `useCreateBudgetDialog()` as the shared state.
  - Strength: Matches the plan's "one instance for the shell" and the way `CommandPalette` is already mounted.
  - Tradeoff: `AppSidebar` loses the dialog it owns now, so the kitchen sink frames that rely on it need a check.
  - Confidence: HIGH — the mount point and the sheet behaviour are both in the code.
  - Blind spot: The kitchen sink renders `AppSidebar` alone and may need its own dialog instance.
- **Decision**: FIXED — Fix now: `CreateBudgetDialog` moved from `AppSidebar` to `AppSidebarLayout` (state still in `useCreateBudgetDialog`), contract guard in `ui-contract.test.ts`, e2e palette → add year on desktop and on a 390 px phone with the sheet closed (phone spec red on the old code, green now), kitchen sink note and `js-sidebar.md` updated.

### F3 — E2E "a digit does nothing while the expense dialog is open" cannot fail

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/global-shortcuts.spec.ts:80
- **Detail**: `await expect(page).toHaveURL(/\/operations$/)` passes on its first poll because `router.visit` has not completed yet. The test stays green even if the shortcut fires, which is the failure it guards against. The other tests in the file assert an effect (menu, focus).
- **Fix**: Record `page.on('request')` before the key press and assert that no Inertia navigation request was sent after a short settle, or assert the dialog is still open and the URL unchanged after waiting for a known idle signal.
- **Decision**: FIXED — Fix now: the spec records Inertia requests (`x-inertia`), takes focus off the dialog field so only the open layer can silence the shortcut, then closes the dialog and presses the key again as a positive control; exactly one request must be recorded. Red with `hasOpenLayer` forced to false (2 requests), green on the real code.

### F4 — Dead code left behind

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/CommandPalette.vue:5, lang/pl/ui.php:15, resources/js/types/shell.ts:33
- **Detail**: `Search` is imported from `@lucide/vue` and never used in `CommandPalette.vue`; `noUnusedLocals` is off, so `types:check` does not catch it. The key `shell.add_budget_year` ("Dodaj rok") has no reader since the "+" segment uses `add_budget_for_year`; the plan removed `select_year` and `no_budget_year` but missed this one. Registry parts `CommandDialog`, `CommandSeparator` and `CommandShortcut` in `ui/command` have no consumers. Separately, `palette: Record<string, string>` leaves `t.palette.*` unchecked while `shell` is typed key by key, as the plan specified.
- **Fix**: Remove the `Search` import and the `shell.add_budget_year` key with its type line. Leave the unused registry parts, which keep the folder diffable against the registry.
- **Decision**: FIXED — Fix now: removed the unused `Search` import from `CommandPalette.vue` and the `shell.add_budget_year` key (PHP and `UiTranslations`); the unused registry parts of `ui/command` stay so the folder remains diffable against the registry, and `palette` stays `Record<string, string>` as the plan specified.

### F5 — Palette search matches the shortcut and the amount, not only the label

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ui/command/CommandItem.vue:45
- **Detail**: `CommandItem` registers `currentElement.textContent`, which includes the `<kbd>` shortcut ("N", "1"), the formatted amount ("2 988 000,00 zł") and the hidden diacritics-folded copy. Typing "1", "n" or "zł" can match unrelated rows. Found by reading; not reproduced.
- **Fix**: Register the search value from the item's `value` prop (already built by `paletteActionSearchValue` and `paletteCategorySearchValue`) instead of `textContent`.
  - Strength: The value helpers and their unit tests already exist in `command-palette-items.ts`.
  - Tradeoff: Another edit to the vendored `ui/command`, together with F1.
  - Confidence: MEDIUM — depends on how the registry handles `value` for the filter.
  - Blind spot: Whether the hidden folded span would still be needed.
- **Decision**: FIXED — Fix now, differently from the proposed `value` prop (the row `value` is the item object used by `select`, not text): `CommandItem` takes an optional `searchText` that replaces the rendered text in the filter, the palette passes `paletteSearchText(label)` (label plus its diacritics-free form), the hidden copy spans are gone. Unit tests for `paletteSearchText`, e2e "the search matches the label only" (red without the change, green with it), ADR-0054 updated.

### F6 — E2E suite is timing and state sensitive (import spec, `/` shortcut spec)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/import-other-format-without-header.spec.ts:54
- **Detail**: In the full `npx playwright test` run (`fullyParallel`, shared `household_treasurer_e2e` database) the spec fails at step 2 with the account error "The selected account id is invalid". Re-run alone it passes (3.4 s). Neither the spec nor the import backend was changed by this change, and the `OperationsImport.vue` diff only removes breadcrumbs, so the cause is state shared with `account-edit-keeps-bank.spec.ts`, which edits accounts. Plan item 4.8 ("npx playwright test") was ticked as green; in this run it was not.
- **Second case, found during triage**: `global-shortcuts.spec.ts:57` ("/ on the Dashboard focuses an empty search field on Operations") failed in later runs and 4 of 4 times with `--repeat-each=4`, on the committed code as well as after the F2 fix, so it is not caused by either. The helper `pressUntilUrl` (lines 10-15) repeats the key every second until the URL changes; when the visit takes longer than 1 s, the repeated `/` lands in the search field the intent has already focused, and `toHaveValue('')` sees "/". It passed in the first full run of this review, so it depends on timing. The same retry loop is used by the `N` and digit specs, where a repeated key is harmless.
- **Fix**: Have the import spec create or pick its own account, or run the account-editing spec in a serial project. For the `/` spec, press the key once after the shortcut listener is attached (for example wait for the sidebar hint or the palette button), or stop retrying once the URL has changed.
- **Root cause (found while fixing)**: all specs log in through one `storageState`, so parallel workers share one database session, and the session driver has no locking. A request of one worker saves the session without the import preview another one has just put there (the preview page then falls back to step 1), and the temporary "E2E …" account of the account spec, deleted mid-run, can be the account the import page had preselected. CI already ran one worker, so only local runs were hit.
- **Decision**: FIXED — Fix now: `workers: 1` in `playwright.config.ts` (with the reason), the `/` spec presses the key once after the layout has mounted instead of retrying, the import spec picks a non-"E2E " account by hand. Full `npx playwright test`: 23 passed, twice in a row; the `/` spec 5 of 5 on its own.

### F7 — Plan criterion 1.5 expects an empty grep that has three legitimate hits

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/sidebar-action/plan.md:123
- **Detail**: `grep -rn "AppSidebarHeader|Breadcrumb|breadcrumbs|sidebarOpen|sidebar_state" resources/js app bootstrap tests` returns the negative assertion in `ui-contract.test.ts:245`, `->missing('sidebarOpen')` in `AppShellTest.php:46` and the untouched `SIDEBAR_COOKIE_NAME` in `ui/sidebar/utils.ts:4` (the primitive still writes the cookie on Ctrl+B; ADR-0054 says nobody reads it). The criterion was written without these. The code is right; the plan sentence is imprecise.
- **Fix**: Accept; no code change. Optionally note the three expected hits in the plan.
- **Decision**: SKIPPED — the code is right and the plan is closed; the three expected hits are listed in this finding.

### F8 — Manual item 3.7 ticked with no commit and no automated evidence

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/sidebar-action/plan.md:443
- **Detail**: "`N` otwiera menu typów; wyłączone typy … nadal pokazują powód" is the only manual item without a SHA, and `global-shortcuts.spec.ts` checks that the menu opens but not the disabled reasons. `OperationsPageActions.vue` changed in 4 lines (the `menuOpen` model), so the risk is small.
- **Fix**: Confirm the item by hand (press `N` on Operations, read the reasons) and append the SHA, or add one assertion on a disabled item's reason to the existing spec.
- **Decision**: SKIPPED — low risk (`OperationsPageActions.vue` changed in 4 lines, the disabled-reason menu is untouched); item 3.7 stays ticked without a SHA.

### F9 — Palette composable: stale shell snapshot, cancelled request logged as error, duplicated type

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/composables/useCommandPalette.ts:42,63-73,124-130
- **Detail**: Three small points, none runtime-verified. `nextBudgetYear()` reads a `shell` object captured in the persistent layout's setup; if Inertia replaces `page.props.shell` on each visit, it could propose a year that already exists after one is created (the same snapshot pattern already exists in `AppSidebarLayout` and `CreateBudgetDialog`). A request cancelled by closing and reopening the palette is logged with `console.error` before the `requestId` check, which is noise but keeps the cause. `PaletteCategoriesState` duplicates `PaletteCategories` from `CommandPalette.vue`, and `lib/command-palette-fixtures.ts` imports a type from a `.vue` file.
- **Fix**: Read `usePage().props.shell` inside `nextBudgetYear`, check `requestId` before logging, and move one shared type to `lib/command-palette-items.ts`.
- **Decision**: FIXED — Fix now: `nextBudgetYear` reads `usePage().props.shell` at call time (Inertia replaces `page.value` on every visit, confirmed in the source); the catch skips only `HttpCancelledError` (matched by `error.name`, the class is not exported by `@inertiajs/vue3`), every other error is still logged with its cause; one shared `PaletteCategories` type in `lib/command-palette-items.ts`, no `.vue` import in the fixtures. Not reproduced in a browser: a stale `shell` after creating a year, and a cancelled request (no unit test, the composable needs Inertia). The same one-visit-old `useShell()` snapshot in `AppSidebarLayout` and `CreateBudgetDialog` was left as it is.

### F10 — Vendored `ui/command` extended for one screen; palette CSS uses raw literals outside the scanned files

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/ui/command/Command.vue:20-23, resources/css/app.css (`.command-palette*`)
- **Detail**: The files were written by hand from the registry JSON instead of `npx shadcn-vue add command`, and `Command.vue` gained `defineExpose({ highlightFirstItem })`. ADR-0054 states both openly with the reason (the CLI would also pull its own `dialog`), and the `package.json`, `package-lock.json` and `ui/dialog` diffs are empty, so the plan's intent holds. It still departs from the AGENTS.md rule that `components/ui/*` is never edited for a single screen. Also, `.command-palette` uses `!important`, `top-[20vh]` and a `calc(100% - 2rem)` width; `check:ui` scans listed Vue files only, so these are not flagged. Extra, harmless additions: `command-palette-fixtures.ts`, extra `ui-contract.test.ts` guards, `.sidebar-search` and `.command-palette-*` helper classes.
- **Fix**: Accept with the ADR as the record; if F1 or F5 touch `Command.vue`, add them to ADR-0054's "Rows in ui/command" paragraph so the list of deviations stays complete.
- **Decision**: SKIPPED — accepted, ADR-0054 ("Rows in `ui/command`") is the record and lists every deviation from the registry: `highlightFirstItem`, the re-filter on item count (F1) and `searchText` (F5). The raw `vh`/`rem` values and `!important` in `.command-palette` stay as they are.

## Triage

Triaged on 2026-11-07. The verdict above is the verdict at review time; the fixes below were made afterwards, each with its own commit.

| Finding | Decision | Commit |
|---------|----------|--------|
| F1 palette filter loses late categories | FIXED | 81b2965 |
| F2 new budget dialog unmounted on a phone | FIXED | 2f49827 |
| F3 e2e digit-in-dialog could not fail | FIXED | ad0b686 |
| F4 dead code | FIXED | 36d2811 |
| F5 search matched shortcut and amount | FIXED (differently: `searchText` prop) | 951d3ae |
| F6 e2e timing and shared state | FIXED (`workers: 1`, `/` spec, import spec) | 4df4eb1 |
| F7 plan criterion 1.5 | SKIPPED | — |
| F8 manual item 3.7 | SKIPPED | — |
| F9 palette composable details | FIXED (not reproduced in a browser, see the finding) | fc45ffa |
| F10 vendored `ui/command` and palette CSS | SKIPPED (ADR-0054 is the record) | — |

Fixed 7, skipped 3, none left pending. F1, F2, F3 and F5 each have a test that was red on the old code and is green now.

### State after triage

| Check | Result |
|-------|--------|
| `npm test` | 36 files, 382 tests passed |
| `npm run check:ui`, `npm run build`, `npm run types:check` | passed |
| `vendor/bin/sloppy ci` | 100/100, 0 findings |
| `npx playwright test` (full suite, now one worker) | 23 passed, also twice in a row before the last two fixes |
| `composer ci:check` (Docker, PostgreSQL) | 941 passed, 2 skipped (7146 assertions), exit 0 |

Not verified at runtime: a stale `shell` after creating a year and the cancelled palette request (F9); the layout-level `useShell()` snapshot in `AppSidebarLayout` and `CreateBudgetDialog` is unchanged.
