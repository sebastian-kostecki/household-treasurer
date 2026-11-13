# v1-4-0-fixes — Plan Brief

> Full plan: `context/changes/v1-4-0-fixes/plan.md`
> Research: `context/changes/v1-4-0-fixes/research.md`

## What & Why

Five notes from using 1.4.0: the year segments in the sidebar take three rows for rarely used years; after a write on Operations the filters, tab and page are gone; every save jumps to the top of the page; „Zakończ” misnames Align; the Category description is missing on Budget. Planning added a sixth: 89 server messages (toasts and import errors) have no Polish translation and show in English.

## Starting Point

List state lives only in the URL, and every Operations write redirects to a bare `/operations`; Accounts already return their list state (`redirectToIndex`). Inertia 3.11 resets scroll on every visit without `preserveScroll`, and the app sets no default. The year segments (ADR-0055), the „Zakończ” copy (PRD FR-CAT-17) and „description only on Kategorie” (ADR-0067) are written decisions.

## Desired End State

Every write on Operations comes back to the same filters, tab and page, and the window stays put; the same holds for Budget's „Pokaż zarchiwizowane” and for any write that returns to its own page. The Budget row says „Wyrównaj”, all toasts and errors are Polish and a test keeps them so. The sidebar shows one „2026 ▾” button with a menu of years newest first and „Dodaj rok 2028”. A Budget row shows its Category description under the name, as on „Kategorie”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Year switcher form | `DropdownMenu`, years newest first, separator, „Dodaj rok N” | Always one line, separates choosing from creating, `NavUser` precedent | Plan |
| Filter scope | Create, edit, conversion, delete of all 4 operation types + Budget `show_archived`; no memory across sidebar navigation | The note says „ogólne”; one pattern fixes every write with the bug | Plan |
| How list state travels | Under one `list[...]` query key, server allow-list | Filter names (`category_id`, `account_id`) collide with form fields that Laravel merges from the query | Plan (found in code) |
| Page past the end | Index redirects to the last page of the active section | Deleting the last row of a page must not show an empty list | Plan |
| Scroll | One `visitOptions` default: non-GET writes keep scroll when the response path equals the current path; explicit options win | Covers ~25 writes and every future dialog in one tested place | Plan |
| Align copy | „Wyrównaj”, „Wyrównać plan do wykonania?”, glossary entry **Align**, ADR-0086 | Align levels the plan to Execution; „Zakończ” belongs to Year Close's _Avoid_ list | Research + Plan |
| Translations | All 89 missing keys + a guard test over `__()`/`trans()` literals in `app/` | Whole app in Polish, regressions caught by CI | Plan |
| Description on Budget | Like the „Kategorie” row: `lg` chip, one truncated `meta` line, full text in `title`, joined with „zebrane startowe” by „ · ”; read-only | The User asked for the „Kategorie” form; ADR-0088 narrows ADR-0067 | Plan (User's answer) |

## Scope

**In scope:**
- Return of filters/tab/page after Operations writes; `show_archived` after Budget Category writes
- App-wide scroll default for same-page writes
- „Wyrównaj” copy with glossary, ADR-0086, PRD update
- Polish translations for every literal key in `app/` and a guard test
- Year menu with ADR-0087, rules, kitchen sink, landing preview, e2e
- Read-only description on Budget with ADR-0088, prop + `assertInertia`, kitchen sink

**Out of scope:**
- Remembering Operations filters across sidebar navigation
- CSV Import commit redirect
- Editing the description on Budget; showing it on Dashboard, Month Plan, Reports or selectors
- Year Close, a separate Align action class, a `popover` primitive, a hybrid year switcher
- CHANGELOG entry (written at release)

## Architecture / Approach

Server: one trait `RedirectsToOperationsList` used by the four operation controllers; `OperationsController` clamps the page. Client: `operationsReturnQuery` in `lib/operations-list-query.ts`, provided by `Operations.vue` and injected into the dialogs and row menus as `.form(…, { query })`. Scroll: pure `lib/visit-scroll.ts` wired once in `app.ts`. Year menu: `lib/budget-year-menu.ts` (also feeds the palette's "next year") behind a `DropdownMenu` in `BudgetYearSwitcher.vue`. Description: one more field in `BudgetController::categoryPayload` and the subtitle of `BudgetCategoryRow`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Writes return to the same list | Filters, tab and page after every Operations write; Budget `show_archived` | A filter key leaking into form input if not namespaced |
| 2. Scroll stays after a write | One tested default; year change resets | `visitOptions` overriding explicit options |
| 3. „Zakończ” → „Wyrównaj” | Glossary entry, ADR-0086, PRD, copy, toast | Missing a hardcoded copy (kitchen sink) |
| 4. Polish server messages | Guard test + 88 translations | Wording the User does not accept |
| 5. Budget year in a menu | ADR-0087, menu, palette rule kept, 3 e2e specs | E2E environment needed to prove the specs |
| 6. Description on Budget | ADR-0088, prop both sides, row line, kitchen sink states | Row height and truncation on narrow widths |

**Prerequisites:** Sail stack up (PostgreSQL tests, e2e server `scripts/e2e-server.sh`); `npm run build` before `types:check` after route-dependent edits.
**Estimated effort:** ~4–5 sessions across 6 phases (Phases 1 and 5 are the largest).

## Open Risks & Assumptions

- The Inertia v3 config key for `visitOptions` (`defaults.visitOptions`) is confirmed with `search-docs` before Phase 2.
- After a create under a filter the new operation may not be on the list; the toast confirms the save. Accepted.
- The guard test reads only literal keys; a key built from a variable is not covered.
- Research expected ~50 redirect assertions to change; with the `list` contract they stay valid, and new tests cover the new path.

## Success Criteria (Summary)

- Editing, adding or deleting on a filtered, scrolled Operations list leaves the User on the same list, same place.
- Every toast and error the server sends is in Polish, and a test fails on a new untranslated one.
- The sidebar takes one line for the year, and Budget rows show the Category description.
