---
date: 2026-11-06T16:50:00+01:00
researcher: Claude (Sonnet 5.5), with three read-only Explore sub-agents
git_commit: 4d4cac7
branch: worktree-dashboard-design-system-page
repository: household-treasurer
topic: "Two-way audit of Dashboard.vue against the PageDashboard card and ADR-0045"
tags: [research, ui, dashboard, atrament, adr-0045, 10x-ui]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Sonnet 5.5)
---

# Research: Dashboard against `PageDashboard` and ADR-0045

**Date**: 2026-11-06 · **Git Commit**: 4d4cac7 · **Branch**: `worktree-dashboard-design-system-page` (from `development` 55b0585)

## Research Question

`resources/js/pages/Dashboard.vue` — pattern: the `PageDashboard` card in https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, per ADR-0045 (three measures, a "Budżet <rok>" panel with two tables and the Switch "Postęp roku", a notice card with a kind label and an amount on the right). New ADR number: 0050. `AccountMarkCompact` stays as it is. Output: `## Charges` (file, line, user impact), as the `/10x-ui` contract asks.

## Summary

- **Contract variant: existing design system (Atrament).** Tokens live in `resources/css/app.css`; the six Dashboard view files have 0 hits in the hard-coded value scan (grep with the `/10x-ui` pattern over `Dashboard.vue`, `DashboardStatPanel.vue`, `DashboardHoldings.vue`, `DashboardExecutionPanel.vue`, `DashboardNotices.vue`, `BudgetPlanSummaryStrip.vue`; also no `<style>` and no inline `style`). The drift is structural, not literal: the view still implements ADR-0043 where ADR-0045 (2026-11-06) replaced it.
- **The docs are already ahead of the code.** Commit `0d071b4` rewrote `context/foundation/design-system.md` (:77 notices, :83 components, :90 no tabs, :98 sink), the glossary Year Elapsed entry (:59-60) and `.ai/rules/js.md:39`. The code was not touched, so five places in the view contradict a rule that is already written.
- **No backend change is needed.** On the inspected path (`$budget !== null`) the controller already sends `planSummary`, `incomeTotals`, `expenseTotals`, `year_elapsed_as_of` and `year_elapsed_percent` (`app/Http/Controllers/DashboardController.php:73-89,140-144`). The payload, kinds, severities, order, links and the six-card limit stay (ADR-0045 §Dashboard, notices).
- **Five charges** (below), all in "accidental architecture" or "missing shared component"; none is a missing token.
- **Eight decisions are not settled by ADR-0045 or the artifact** and must be settled in ADR-0050 or the plan (see Open Questions). The most visible: the right-hand value for the two amount-less notice kinds, the kind labels for five of seven kinds, and the mobile layout of the two tables.

## Detailed Findings

### Source → views (where the values and components live, who reads them)

- Values: `resources/css/app.css`. Named styles used by the view: `page-title`, `section-title`, `table-header`, `meta`, `kpi-lg`/`kpi`, `amount`, `amount-sum` (`.ai/rules/css.md`). Dashboard classes: `.dashboard-grid` (:1151-1161), `.month-days*` (:1163-1173), `.dashboard-notice*` (:1176-1208), `.stat-panel*` (:1210-1248), `.category-meter-*` (:1057-1148).
- `Switch` (`components/ui/switch/Switch.vue`, reka-ui, `v-model`) is used by 2 pages on the inspected path: `pages/Accounts.vue:16` and `pages/Budget.vue:249`. `Dashboard.vue` and its 5 components import none.
- `StatPanel` is imported by 4 consumers on the inspected path: `Accounts.vue`, `BudgetPlanSummaryStrip.vue`, `DashboardStatPanel.vue`, `MonthPlanStatPanel.vue` (it is also scanned by `ui-contract.test.ts:24`). It is itself a `Card` (`StatPanel.vue:~30`), so it cannot be nested in another card without a card-in-card.
- `ExecutionMeter` is imported by 4 components: `BudgetCategoryRow`, `BudgetCategoryList`, `CategoryMeterRow`, `DashboardExecutionPanel`. `CategoryMeterRow` is imported only by `DashboardExecutionPanel.vue:3`, so changing it cannot regress another page. `ExecutionMeter` hides the Year Elapsed mark when `yearElapsedPercent` is `null` (`ExecutionMeter.vue:~269`), so the Switch needs no change inside it.
- The only browser-storage use in `resources/js` is `composables/useAppearance.ts` (grep: 1 file), without try/catch. There is no helper to copy for ADR-0045's "reads and writes inside try/catch". The earlier Dashboard checkbox used the same key `dashboard-year-elapsed` and was removed in `dashboard-atrament-ui` (`context/archive/2026-11-05-dashboard-atrament-ui/research.md:94`).
- Guards that name Dashboard files: `scripts/check-ui-literals.mjs` (explicit `FILES` list) and `tests/Unit/DashboardHardcodedUiTest.php` (11 files; a missing file fails). A new or renamed file must be added to both; a deleted file must be removed from both.

### View → source (the `PageDashboard` card against the code)

The artifact card is `project/components/PageDashboard/preview.html` (version 1793965592-5e9c). What it shows, and what the view does:

| Part | Card | View today |
|---|---|---|
| Metrics | 3 measures: "Listopad — do wydania" with "Plan miesiąca →", "Płynność dysponowalna", "Łączne środki" | 3 plus a 4th "Postęp roku" (`DashboardStatPanel.vue:116-123`) |
| Budget block | heading "Budżet 2026" with Switch "Postęp roku" and button "Otwórz budżet" at the right; below it, as sibling panels: a 3-measure panel (Planowane dochody, Planowane wydatki, Bilans) and two tables | `DashboardExecutionPanel` with tabs and group rows; "Otwórz budżet" in the page title row (`Dashboard.vue:131-155`); plan strip only in draft (`Dashboard.vue:259`) |
| Tables | Kategoria \| Plan bieżący \| Realizacja \| Pozostało \| Wykonanie, `Suma dochodów` / `Suma wydatków` row (with a meter, no mark), "przekroczono" Badge next to the category name, footer "Kwoty w zł · morska kreska = postęp roku (84%) · kliknij wiersz…" | 6 cells incl. Status, group rows, `Plan` / `Zrealizowano` labels (`DashboardExecutionPanel.vue:91-145`, `lang/pl/ui.php:120-121`) |
| Notice card | icon tile, kind label (`ht-notice__kind`), identity in bold (`<b>`), value at the right (`ht-notice__value`, mono, severity colour, `white-space: nowrap`) | icon tile + title + sentence only (`DashboardNotices.vue:363-388`) |
| Pills | "Przekroczone · 1" (`--over`), "Ostrzeżenia · 1" (`--warn`) | present and correct (`DashboardNotices.vue:319-339`) |

The card shows the "Budżet 2026" block as a heading plus three **sibling** panels (stats, Dochody, Wydatki), not as one bordered container (`PageDashboard/preview.html`, `.ht-zone__head` followed by `.ht-panel`s). ADR-0045 says "one panel". The two readings differ in whether `BudgetPlanSummaryStrip` (a `Card`) can be reused as is — see Open Questions Q4.

Artifact divergences from ADR-0045, verified by reading the files in the artifact:
- `Notice/README.md` describes the card as kind label + identity + "jedno zdanie z liczbami"; its `preview.html` has a sentence and no right-hand value. `PageDashboard/preview.html` and ADR-0045 have the right-hand value. ADR-0045 and `design-system.md:77` win (rule in `design-system.md:3`: where the page and an ADR differ, the ADR is written before departing from the page).
- `StatPanel/README.md` still lists "Do wydania, Płynność dysponowalna, Łączne środki, Postęp roku" and "Nie więcej niż cztery miary". `PageDashboard/README.md` says three. ADR-0045 wins.
- `Notice/preview.html` shows a "Blisko limitu" label, but `Meter/README.md` and ADR-0037 say there is no "near the limit" state because the glossary defines no threshold. Do not adopt "Blisko limitu".
- `bundle.css` contains 0 `@media` rules (grep), so the card defines no mobile layout. The identity is `<b>` with `font-weight: 600` (`bundle.css:170`), while ADR-0045 says "medium weight" and `.dashboard-notice-title` is `font-medium`.

### Notice kinds and what the right-hand value can be

Verified in `app/Support/DashboardNotices.php` (reported by the backend audit; TS type in `DashboardNotices.vue:22-76` matches). Order of emission is the contract.

| kind | severity | identity field | amount field |
|---|---|---|---|
| `negative_account` | exceeded | `name` | `amount_minor_units` (balance) |
| `negative_term_deposit` | exceeded | `name` | `amount_minor_units` (balance) |
| `month_to_spend_negative` | exceeded | `month` | `amount_minor_units` (negative to spend) |
| `month_item_over_plan` | exceeded | `name`, `category_name` | `amount_minor_units` (negative) |
| `one_off_over_limit` | exceeded | `name`, `category_name` | `amount_minor_units` (negative) |
| `month_plan_not_approved` | warning | `month` | none |
| `month_pace_ahead` | warning | `month` | none (`percent`, `elapsed_day`, `days_in_month`) |

5 of 7 kinds carry an amount; 2 do not. In the artifact, `month_pace_ahead` has a right-hand value "31% · upływ dni 17%", which maps to `percent` and `monthElapsedPercent(elapsed_day, days_in_month)` (both already used at `DashboardNotices.vue:210-222`). The artifact has no example for `month_plan_not_approved`. The 6-card limit is applied in the front end (`DashboardNotices.vue:79`, test `tests/Feature/DashboardTest.php` asserts 7 notices are sent).

### Tests and guards touched

- PHP: `tests/Feature/DashboardTest.php` asserts props via `assertInertia` and does not assert copy; `tests/Unit/DashboardHardcodedUiTest.php` is the structure guard; `tests/Feature/DashboardKitchenSinkTest.php` checks only the route and component name. If the props stay as they are, no PHP test changes.
- TS: `pages/dev/dashboard-fixtures.test.ts` asserts scenario keys and 7 distinct notice kinds; it asserts data, not markup. No `.test.ts` asserts tabs, group rows or the Year Elapsed measure.
- No Playwright spec covers the Dashboard (`tests/e2e` holds 3 files: `account-edit-keeps-bank.spec.ts`, `auth.setup.ts`, `seed.spec.ts`). The visual gate is the kitchen sink `/dev/ui/dashboard`.
- Kitchen sink today: 16 scenarios in `dashboard-fixtures.ts:554-586`. They already contain the data for exceeded rows (`overrun`), draft, no budget, no categories, many categories and all 7 notice kinds (`all-notice-kinds`), so the new layout renders them without new data. Not covered: the Switch in both states — its state lives in the browser, not in props, so several `<Dashboard>` instances on one page would share one stored value. The state matrix text at `DashboardKitchenSink.vue:~15` mentions "Status 'przekroczono'" and will go stale.

## Charges

Format: file:line · what the user sees · category · where it goes in the plan. All five are to be fixed; none is deferred. Related smaller findings are listed after the charges.

**C1 — Four measures instead of three** (accidental architecture)
- `resources/js/components/DashboardStatPanel.vue:116-123` pushes a fourth measure "Postęp roku"; `:71-87` builds its caption (reusing `monthPace` text); `:24-25` and `Dashboard.vue:224-225` pass `yearElapsed*` only for it. `resources/css/app.css:1229-1233` sets the first of 4 columns to `1.5fr`.
- User impact: a percentage sits at the same weight as three money readings, although the glossary defines Year Elapsed as "a mark on a Category meter, not a money reading" (`glossary.md:60`). With one measure fewer, the 4-column grid template leaves an unbalanced layout.
- Target: `PageDashboard` metrics panel (3 measures); ADR-0045 §metrics panel.
- Phase: view (top of page).

**C2 — Tabs, group rows and a Status column instead of two tables** (missing shared component / accidental architecture)
- `components/DashboardExecutionPanel.vue:28-34,81-88` (3-tab `SegmentedControl`); `:112-145` (group rows with totals, meter and Badge); `:95-97,107-109` (columns `Plan`, `Status`; `lang/pl/ui.php:120,150`); `:66-73` footer. CSS: `app.css:1100-1102` (`.category-meter-group-row` is the sum row today), `:1129-1133` (hides Plan and Execution below `md`).
- User impact: to compare income with expenses the user has to switch tabs; sums sit above the rows instead of in a sum row; there is a Status column the card does not have. On a phone the Plan and Execution columns disappear (`app.css:1129-1133`), while the new header asks for Plan bieżący and Realizacja.
- Target: two `ListSurface` tables Dochody / Wydatki, columns Kategoria \| Plan bieżący \| Realizacja \| Pozostało \| Wykonanie, sum row "Suma dochodów" / "Suma wydatków", "przekroczono" next to the percentage (ADR-0037, ADR-0045). `.ai/rules/js.md:39` already requires "two `ListSurface` tables… not a second mobile tree".
- Reusable parts: `ListSurface`, `CategoryMeterRow`, `ExecutionMeter`, `Badge destructive-soft`. Copy to add: "Suma dochodów", "Suma wydatków"; reusable: `budget.current_plan` ("Plan bieżący"), `budget.category`, `dashboard.remaining`, `dashboard.percent_complete`, `budget.over_limit_badge`, `budget.amounts_in_zl`.
- Phase: execution panel.

**C3 — The Year Elapsed mark is always on and has no Switch** (accidental architecture)
- `Dashboard.vue:283` and `DashboardExecutionPanel.vue:160` pass `year_elapsed_percent` to every row unconditionally; nothing reads or writes `dashboard-year-elapsed` (`grep` of `resources/js`: `localStorage` appears only in `composables/useAppearance.ts`).
- User impact: the user cannot hide the vertical mark from the meters; the footer text `execution_footer` always mentions it (`DashboardExecutionPanel.vue:66-73`).
- Target: Switch "Postęp roku" (`components/ui/switch`, `v-model`, per `.ai/rules/js.md`: "a setting saved at once is a `Switch`") in the "Budżet <rok>" heading; shows or hides the mark on the meters that may show it (income and Paced expense via `showsYearElapsedMark`, `lib/dashboard-categories.ts:51-60`); remembered under `dashboard-year-elapsed`, reads and writes in try/catch (ADR-0045). Hidden mark → pass `null` to `CategoryMeterRow`; the existing `execution_footer_no_mark` copy covers the footer.
- Phase: pure logic first (a storage helper with Vitest tests written first), then view.

**C4 — "Otwórz budżet" and the plan measures are in the wrong place** (accidental architecture)
- `Dashboard.vue:131-155`: "Otwórz budżet" (a `Form` posting `BudgetController.selectYear` or a `CreateBudgetDialog` trigger) sits in the page title row, away from the block it opens. `Dashboard.vue:259-264`: `BudgetPlanSummaryStrip` renders only when `budget.phase === 'draft'`, although `planSummary` is sent in both phases (`DashboardController.php:87-89`).
- User impact: in execution there are no plan measures (income, expenses, Planned Balance) on the Dashboard at all, and the action to open the budget is not next to the budget. The card has both in the block.
- Target: heading "Budżet <rok>" with Switch and "Otwórz budżet" at the right; plan measures above the tables. `planned_balance_minor_units` is Income Current Plan minus expense Current Plan (`app/Support/BudgetPlanSummary.php:57-61`), UI copy "Bilans" (`glossary.md:103-105`), archived Categories excluded (`glossary.md:36`). Keep the select-year failure listener (`Dashboard.vue:43-55`) with the button.
- Phase: view (budget block).

**C5 — Notice card has no kind label and no right-hand value** (missing shared component / accidental architecture)
- `components/DashboardNotices.vue:363-388` renders the icon tile and two lines (`.dashboard-notice-title`, `.dashboard-notice-text`); `:155-224` build the title and sentence per kind; `resources/css/app.css:1176-1208` has no class for a kind label or a right-hand value and none that colours a value by severity.
- User impact: the cause of each card is a sentence that has to be read; the amount, the one number the user scans for, is buried in it. Cards cannot be compared at a glance.
- Target: ADR-0045 §notices; `design-system.md:77`: icon tile in severity colour, kind label on top, identity in medium weight, value at the right in severity colour. Colour must come from `severity` only (`.ai/rules/support.md`, ADR-0032). The link target, order, 6-card limit and the pills stay.
- Phase: notices.

### Related findings that are not separate charges

- `.ai/rules/js.md:47` still describes the warning card from ADR-0043 ("severity only in the icon tile"); `design-system.md:77` is already current. Update the rule in the docs phase.
- `design-system.md:3` already says: where the page and an ADR differ and a newer ADR does not settle it, write the ADR first. This is the reason for ADR-0050.
- Orphaned copy after the change (not verified for other users): `dashboard.execution_tab_all`, `execution_tabs_aria`, `execution_status`, `planned`, `executed`, `year_elapsed_as_of`, `month_pace_percent`, `month_pace_none` (`lang/pl/ui.php`). No test fails on unused keys (grep of `tests/`: no reference to `lang/pl`).
- `.segmented-control-tabs` (`app.css:538-540`) may become unused by the Dashboard; the frontend audit reports it might also serve Operations, which was not resolved.
- No `.ai/rules/index.md` formatter pitfall: it is already in `fmt.ignorePatterns` (`vite.config.ts:89`).

## Code References

- `resources/js/pages/Dashboard.vue:131-155,217-229,259-264,277-284` — title-row "Otwórz budżet", stat panel props, draft-only strip, execution panel props
- `resources/js/components/DashboardStatPanel.vue:71-87,116-123` — year-elapsed caption and fourth measure
- `resources/js/components/DashboardExecutionPanel.vue:28-34,66-73,81-145` — tabs, footer, header row, group rows
- `resources/js/components/DashboardNotices.vue:22-76,155-224,343-389` — notice type, title/text per kind, grid, card markup
- `resources/js/components/CategoryMeterRow.vue` (importer: `DashboardExecutionPanel.vue:3` only) — row grid and "przekroczono" badge
- `resources/js/components/StatPanel.vue` — `Card`-based, `StatPanelMetric` type, slots `label-extra-<id>` / `footer-<id>`
- `resources/js/components/BudgetPlanSummaryStrip.vue:20-54` — the 3 plan measures (4th only with `yearElapsedPercent`)
- `resources/css/app.css:538-540,1057-1148,1151-1173,1176-1208,1210-1248` — classes to change or add
- `app/Http/Controllers/DashboardController.php:73-89,140-144` — props already sent
- `app/Support/BudgetPlanSummary.php:57-61` — Planned Balance uses Current Plan
- `app/Support/DashboardNotices.php` — kinds, severities, order (contract)
- `lang/pl/ui.php:103-172,186-212,315-317` — dashboard and budget copy
- `scripts/check-ui-literals.mjs`, `tests/Unit/DashboardHardcodedUiTest.php` — file lists to keep in sync
- `resources/js/pages/dev/DashboardKitchenSink.vue`, `dashboard-fixtures.ts:554-586` — sink and 16 scenarios
- `context/foundation/adr/0045-*.md`, `0047-*.md` (template for the follow-up ADR), `design-system.md:3,70-77,83,90,98`, `glossary.md:36,59-60,103-105`
- Artifact: `project/components/PageDashboard/{README.md,preview.html}`, `Notice/`, `StatPanel/`, `Meter/`, `Switch/` READMEs, `bundle.css:161-171,289-291`

## Architecture Insights

- The repo runs the same loop for every view: ADR (what the page left open) → docs → pure logic test-first → shared layer and guards → view → sink with states and screenshots (390/1280, light and dark) → rule. The closest precedent is `context/archive/2026-11-06-accounts-design-system-page` (3 phases, ADR-0047 recorded the values ADR-0045 left open and the departures from the card, and stated that the ADR wins over the artifact README).
- Per-row Switch state is a view concern, not a prop: the server does not need it. This keeps the Inertia contract unchanged and avoids the "both sides in one commit" rule (`.ai/rules/inertia-props.md`).
- `AccountIdentityBlock` in `DashboardHoldings` keeps the bank logo until the term-deposits/accounts change reaches it; `AccountMarkCompact` is out of scope here.

## Historical Context (from prior changes)

- `context/archive/2026-11-05-dashboard-atrament-ui/` — implemented ADR-0043 in 7 phases; recorded pitfalls that still apply: link rows take a 2 px `outline` focus and a named hover class (not utilities), a props change goes with fixtures and `assertInertia`, `IndexEmptyState` is shared, the empty meter track on `muted` had ~1.1:1 contrast (re-check on the sink now that group rows are gone — inference).
- `context/archive/2026-11-06-accounts-design-system-page/reviews/impl-review.md` — findings F3 (grep the docs for stale wording after the change), F4 (a new named `@utility` goes into the list in `.ai/rules/css.md:14`), F5 (the superseded ADR gets a back-reference "Superseded in part by ADR-0050").
- ADR-0032 (severity from the server, 6 cards), ADR-0037 (unit in the panel footer; "przekroczono" next to an exceeded category; no near-limit state), ADR-0043 (superseded in part), ADR-0045 (the decision), ADR-0047 (template).
- ADR numbers: 0050 is free on every branch checked (`git ls-tree` of all local and remote branches); 0046, 0048, 0049, 0051 are reserved by parallel changes (0048 on `worktree-import-start-atrament-ui`; 0049 settings; 0051 term deposits, per the sub-agent's read of their change docs). Parallel changes will all edit `design-system.md` and `js.md`; expect merge conflicts there.

## Related Research

- `context/changes/term-deposits-atrament-ui/research.md` (main checkout, read only) — same template, charges C1–C5 for another view.

## Open Questions

Each needs a decision in ADR-0050 or the plan. The research proposes a default where the evidence supports one.

- **Q1 — Right-hand value for amount-less kinds.** `month_pace_ahead`: the card shows "31% · upływ dni 17%"; map to `percent` and `monthElapsedPercent(...)` (default). `month_plan_not_approved`: no value in the card, no field in the payload; options are an empty right slot or a state word. Evidence does not choose.
- **Q2 — Kind labels for 7 kinds.** The card names two ("Ponad limit", "Tempo przed upływem dni"). `one_off_over_limit` fits "Ponad limit". ADR-0032 mentions "Saldo ujemne" for negative balances. For `month_item_over_plan`, `month_to_spend_negative` and `month_plan_not_approved` no label exists in the repo or the card. The strings must be written in ADR-0050 and `lang/pl/ui.php` (no new kind is introduced, so no glossary change).
- **Q3 — Identity for `month_item_over_plan` and the sign of amounts.** `design-system.md:77` says identity is "Konto, Lokata albo Kategoria". The kind carries `name` (item) and `category_name`; which one to show is not stated. Amounts are negative in the payload; the card shows "−320,00 zł"; the current sentences use `Math.abs`. Default: show the signed value from `formatMoney` for exceeded kinds (inference from the card).
- **Q4 — "One panel" or heading + sibling panels.** The card is a heading plus three sibling panels; ADR-0045 says "one panel". Default (inference): follow the card — `h2` row with Switch and button, then `BudgetPlanSummaryStrip` (a `Card`) and two `ListSurface`s as siblings — which avoids card-in-card and reuses the strip. `BudgetPlanSummaryStrip` is also used by `Budget.vue:237`; do not change its defaults. The draft case (no category tables; today an `IndexEmptyState` card) is also unspecified: Switch likely hidden in draft because there are no meters.
- **Q5 — Mobile layout of the two tables.** The card has 0 `@media` rules. Today's grid keeps category, remaining, meter and status on phones and hides plan and execution (`app.css:1129-1133`). `.ai/rules/js.md` forbids a second mobile tree. The plan has to choose which of the 5 columns show on a phone and where "przekroczono" sits once the Status column is gone.
- **Q6 — Switch default and storage.** Default on (keeps today's behaviour and matches the card's `aria-checked="true"`). Reads belong in `onMounted` if SSR is active (`build:ssr` exists in `package.json`; whether SSR runs in production was not verified). A new helper (in `lib/` or `composables/`) with try/catch and Vitest tests; `useAppearance.ts` is not a model for the try/catch.
- **Q7 — Showing the Switch in both states on the sink.** State is browser-held, so the sink needs either an initial-state override or its own section; to be decided with the sink phase.
- **Q8 — Identity weight.** ADR-0045 says "medium", `design-system.md:77` says "półgrubo", the card uses 600, the existing class uses `font-medium`. Pick one in ADR-0050.

No glossary rule or _Avoid_ term is affected by the planned copy: "Bilans" is the Polish copy of Planned Balance (`glossary.md:105`), "Plan bieżący" is the annual Current Plan copy, "Pozostało" is used per Category (it is _Avoid_ only for To Spend and Year Result), "Wykonanie" is allowed for Execution (`glossary.md:47-49`).
