<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Sidebar UI contract pass — Implementation Plan

- **Plan**: context/changes/sidebar-ui-contract/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-03
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Verification run (this review)

- `git merge-base --is-ancestor development HEAD` — OK
- `npm run check` — pass (239 files formatted, 178 files, no warnings)
- `npm test` — 15 files, 124 tests pass
- `npm run types:check` — pass
- `npm run check:ui` — exit 0; `node scripts/check-ui-literals.mjs resources/js/pages/Welcome.vue` — exit 1, `text-white` at :109 and :324
- `grep '^\s*--sidebar:'`, `grep 'var(--sidebar)'`, `NavFooter`, `AppLogoIcon … fill-current` — no hits; `NavFooter.vue` does not exist
- Sail image (PostgreSQL): `AppShellTest`, `SidebarKitchenSinkTest`, `BudgetTest` — 67 passed (573 assertions)
- `vendor/bin/sloppy ci` (host) — 0 findings
- `composer ci:check` in the Sail image: see the line at the end of this file

## Findings

### F1 — Dialog opened from the menu relies on a single `nextTick`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetYearSwitcher.vue:51-55
- **Detail**: `openCreateDialog()` sets `createDialogOpen = true` in `nextTick` after `@select`. The modal `DropdownMenu` (reka) restores `pointer-events` on `body` and returns focus to the trigger when its content unmounts, which is not guaranteed to happen within one tick. The plan (Critical Implementation Details) names exactly this risk (`pointer-events: none` left on `body`, focus in the wrong place) and suggests `nextTick` as an example. Verification was manual only (3.9, human-confirmed); there is no regression test. Not reproduced in a browser during this review.
- **Fix**: Open the dialog from the menu's close event instead of a timer: set a `openDialogAfterClose` flag in `@select`, bind `@update:open` on `DropdownMenu`, and when it reports `false` with the flag set, open the dialog and clear the flag.
  - Strength: Ordering is guaranteed by the menu itself, so it does not depend on tick timing in reka internals.
  - Tradeoff: A flag and one extra handler (~8 lines); behaviour must be re-checked by hand (Tab after closing the dialog, click on the page).
  - Confidence: MED — standard remedy for Radix/reka dialog-from-menu; not reproduced here.
  - Blind spot: Whether the current `nextTick` already behaves correctly in the installed reka version (the human check in 3.9 says it does).
- **Decision**: ACCEPTED — keep `nextTick`. Triage correction: `@update:open` fires at state change, no later than `nextTick`; the correct hook in reka 2.11 would be `@close-auto-focus` on `DropdownMenuContent` with `preventDefault()` and a manual `focus()` on the trigger (reka's own handler otherwise refocuses the trigger in `setTimeout(0)`). Not implemented: not verifiable without a browser, and manual check 3.9 passed.

### F2 — Caption "Rok budżetu" fails 4.5:1 on hover and when the menu is open (light theme)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetYearSwitcher.vue:96 and :124
- **Detail**: The caption uses `text-muted-foreground text-xs`. The button background becomes `sidebar-accent` on hover, active and `data-[state=open]`. Computed from `app.css`: `--muted-foreground` hsl(0 0% 45.1%) on `--sidebar-accent` hsl(189 30% 94%) = 4.21:1 (on `--sidebar-background` it is 4.54:1; dark theme 5.7:1). Small text needs 4.5:1.
- **Fix**: Use a sidebar token for the caption: `text-sidebar-foreground/80` (computed 5.33:1 on `sidebar-accent` in light, above 5.7:1 in dark) at both lines.
- **Decision**: FIXED — `text-muted-foreground` replaced by `text-sidebar-foreground/80` in both captions of BudgetYearSwitcher.vue.

### F3 — `disabled` on the trigger drops keyboard focus after choosing a year

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetYearSwitcher.vue:112-113
- **Detail**: After a selection the menu returns focus to the trigger, but `onStart` sets `pending`, which makes the button `disabled`, so the focus call is ignored and focus falls to `body`. A disabled button also shows no tooltip in icon mode. `disabled` is what plan 4.2 asks for, so this is a plan flaw rather than drift; the success path navigates to Budget anyway, so the practical effect is brief.
- **Fix**: Replace `:disabled="pending"` with `:aria-disabled="pending"` (the button variants already style `aria-disabled:opacity-50 aria-disabled:pointer-events-none`); `selectYear` already ignores calls while `pending`.
  - Strength: Same visual, focus stays on the button, `aria-busy` still announces the state.
  - Tradeoff: The menu can still be opened with the keyboard while pending (harmless, selection is guarded).
  - Confidence: MED — behaviour of focus restore on a disabled element not tested in a browser.
  - Blind spot: Screenshot `disabled-switcher-*` would need retaking.
- **Decision**: FIXED via Fix — `:disabled="pending"` replaced by `:aria-disabled="pending"` in BudgetYearSwitcher.vue. Follow-up: re-take `disabled-switcher-*` screenshots and re-check focus after choosing a year (not done in this review).

### F4 — `isCurrentSection('/', …)` matches every path; edge cases untested

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/nav-active.ts:23, resources/js/lib/nav-active.test.ts
- **Detail**: With `path === '/'` the last line is `current.startsWith('/')`, true for every path. No nav item uses `/` today, so nothing breaks, but the contract says "path or its subpages by segment". Tests also lack query string and hash cases (`/operations?x=1`, `/operations#a`) that the code handles, and `new URL()` throws on a malformed absolute href.
- **Fix**: Return `current === path` when `path === '/'`, and add tests for `href '/'` vs `/dashboard`, and for query/hash on `currentPath`.
- **Decision**: FIXED — `path === '/'` now matches only the root; two tests added (root href, query/hash).

### F5 — `BudgetYearSwitcher.vue` has two root nodes

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/BudgetYearSwitcher.vue:83 and :168
- **Detail**: Template root is `<SidebarMenu>` plus `<CreateBudgetDialog>`. Project rule (inertia-vue/core): components must have a single root element. Nothing breaks now (parent passes only props), but any `class`/attr passed from `AppSidebar` would warn and be dropped.
- **Fix**: Move `<CreateBudgetDialog>` inside `<SidebarMenu>` (its root is a renderless `Dialog`, so no extra DOM).
- **Decision**: FIXED — `<CreateBudgetDialog>` moved inside `<SidebarMenu>`; the component now has a single root.

### F6 — Kitchen sink "404" test depends on the default `APP_ENV`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/SidebarKitchenSinkTest.php:20-25
- **Detail**: `DashboardKitchenSinkTest` sets `$this->app['env'] = 'production';` before the not-found case; the sidebar test does not and passes only because `phpunit.xml` runs as `testing`.
- **Fix**: Add `$this->app['env'] = 'production';` at the start of `test_it_is_not_found_outside_the_local_environment`.
- **Decision**: FIXED — explicit `production` env set in the 404 test.

### F7 — Two changes beyond the plan text: `check:ui` pattern and `DashboardKitchenSink` async import

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: scripts/check-ui-literals.mjs (commit bfba586), resources/js/pages/dev/DashboardKitchenSink.vue:3-9 (commit cc5a14b)
- **Detail**: (a) Phase 5 said to append files if the script exists; the commit also added `(white|black)` colours and `dark:` to `PATTERN`. Needed so plan 5.2 (exit 1 on `text-white`) can hold, and dashboard files stay at 0 hits, but the plan does not record it. (b) `Dashboard` became a `defineAsyncComponent` import in another change's page to fix `AppShellTest` (Vite manifest). Both are already consistent with `development` (no diff there for (b); (a) differs only by our additions).
- **Fix**: Add a short addendum to plan.md describing both decisions.
- **Decision**: FIXED — "Addendum (przegląd implementacji, 2026-11-03)" added to plan.md before `## Progress`.

- `composer ci:check` in the Sail image (PHP 8.5, PostgreSQL), exit 0: lint/format, Pint, PHPStan, `check:ui`, 828 PHP tests passed (2 skipped), 124 frontend tests passed
