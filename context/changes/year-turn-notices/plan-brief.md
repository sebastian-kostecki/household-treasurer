# Year Turn Notices — Plan Brief

> Full plan: `context/changes/year-turn-notices/plan.md`

## What & Why

Roadmap slice S-41. The year-turn rehearsal showed that nothing in the app tells the user what is coming before 1 January:
- a month plan that is not approved by the last day of the month is lost;
- a next-year Budget still in draft makes Import refuse that year's rows;
- once started, next year's Budget looks like any other year.

This change adds the reminders.

## Starting Point

- „Wymaga uwagi” already raises `month_plan_not_approved` from day 3 of the month, without a deadline.
- The Dashboard reads only the current year's Budget. It has no notice for a draft year.
- The year menu marks only „bieżący” and „szkic”.
- The audit finding MPL-1 (a silent December refusal after midnight) was already closed by commit `1659a87`.

## Desired End State

- In every month, the „Plan niezatwierdzony” card says „zatwierdź do 30.11, 23:59”.
- Through December, a last warning card says that Budget 2027 is a draft (or missing) and that Import will refuse its rows. It links to that Budget, or to „Dodaj rok”. The same card shows in January if the current year's Budget was never started.
- After „Rozpocznij realizację” of 2027 on 30.12, the year menu shows „od 1.01” at 2027. The Dashboard line reads „… · budżet 2027 od 1 stycznia”. Both disappear at 00:00 Polish time.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Past-month rule | Unchanged, warn only | Small scope, matches README and ADR-0103; approving after midnight would touch Proposal, To Spend and Collected |
| Deadline in the card | Same `month_plan_not_approved` card, value „zatwierdź do DD.MM, 23:59” | One card per matter, no crowding of the 6 visible |
| Months with a deadline | Every month, not only December | The code refuses every past month (`MonthPlanProposal::isPast`), so one true rule beats a December exception |
| Next-year Budget card | One kind `budget_not_in_execution`, states draft / missing, all of December | Simple "in December" rule; a missing Budget is visible early enough to plan it |
| Forgotten start | The same card whenever the current year's Budget is a draft | One rule for December and January; closes rehearsal gap 1 |
| "Waits for 1 January" | Year-menu mark „od 1.01” plus a Dashboard status-line note, no notice | A correct state should not look like a warning |
| January approval in December | Out of scope, README warning stays, candidate in directions | Keeps S-41 to reminders; an early plan can be changed later |
| Order of the new card | Last among warnings | Never pushes existing month-plan warnings past the limit (roadmap risk) |

## Scope

**In scope:**
- Glossary, ADR-0108 and amendments to 0032/0050/0098, design-system, rules.
- `approve_by` on the month-plan notice.
- The `budget_not_in_execution` notice with its links.
- The „od 1.01” year-menu mark.
- The `waiting_budget_year` Dashboard prop and status line.
- Kitchen sinks, Feature tests at the Polish midnight boundaries and Vitest.

**Out of scope:**
- Approving a past month after midnight; blocking or hinting at January approval in December.
- A notice for the waiting year, a new severity or token.
- A card for a missing current-year Budget.
- S-40, S-43, MPL-2…MPL-10.

## Architecture / Approach

The server decides every notice:
- `DashboardController` loads the next-year Budget with one query.
- `DashboardNotices` appends the new kind last and adds `approve_by`.
- A new `waiting_budget_year` prop feeds the status line.

The PHP shapes and TS types change in one commit with `assertInertia`. Copy goes through `dashboard-notice-presentation`. The year menu derives `waiting` in `budgetYearMenu` from `shell.budgetYears` and `shell.today`, like the ADR-0098 marks.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Glossary and ADR-0108 | Rules written down, user confirms before code | Wording that later code contradicts |
| 2. Notices in „Wymaga uwagi” | Deadline in the plan card, the draft/missing Budget card, tests at 22:59:59 / 23:00:00 UTC | Order contract and the 6-card limit |
| 3. „Od 1 stycznia” | Year-menu mark, Dashboard status line, full gate, rehearsal-clock check | ADR-0098 accessibility contract (marks only in `aria-describedby`) |

**Prerequisites:** S-39 (`AppDay`, ADR-0103) merged in `development`, which is true. Test database `testing_year_turn_notices` created on the Sail PostgreSQL.
**Estimated effort:** ~2–3 sessions across 3 phases.

## Open Risks & Assumptions

- The task description comes from roadmap slice S-41, because the change was opened without an intent.
- A December with a draft current year and a draft next year shows two cards. This is accepted, in year order.
- The „od 1.01” pill reuses the outline style of „bieżący”. If it reads too much like „bieżący” in the kitchen sink, the styling (not the rule) is revisited in Phase 3.

## Success Criteria (Summary)

- On 29.12 the user sees the December deadline and the state of Budget 2027 in „Wymaga uwagi”, with one click to act on each.
- On 30.12 after starting 2027, the menu and the Dashboard say that 2027 starts on 1 January. At 00:00 Polish time the app shows 2027 as „bieżący”.
- Feature tests at the Polish midnight boundaries and the full gate are green on PostgreSQL.
