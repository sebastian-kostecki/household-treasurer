# Production Smoke Tests (S-48) — Plan Brief

> Full plan: `context/changes/production-smoke-tests/plan.md`

## What & Why

After the M-5 deploy, the owner checks on production, on his own data, that the M-5 changes work, and records the result (roadmap S-48, MS-12). The M-5 changes are the year-turn fixes S-39–S-43 and the MCP server.

The year-turn changes show only on certain days (3.12, 30–31.12, 1–2.01), and the rehearsal clock refuses to run outside `local`. So the checks are spread over time and start with a rehearsal on a copy, while a fix can still ship before 31.12.

## Starting Point

- **Code on main.** All M-5 code is on `origin/main` (PR #279, 2026-11-20). Whether it is deployed is not recorded, and `deploy.sh` only prints "Check /up".
- **MCP already confirmed.** The S-47 plan confirmed the MCP part of S-48 on production on 2026-11-19.
- **Template.** The 2026-11-18 rehearsal (`year-turn-rehearsal/rehearsal.md`) is the template for the procedure and the step table.
- **README out of date.** The README year-turn checklist still describes „Lata” before S-43.

## Desired End State

`checks.md` in the change folder has four step tables: copy, after deploy, December, January. Each row has an expected result written up front and the actual result.

The file closes with an MS-12 verdict: either "M-5 works on production without manual fixes" or a list of failures, each with the id of its fix change. The README checklist matches the code, and no production data is left in the repo, the worktree or on the VPS.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Where to check the year turn | Copy now with the rehearsal clock, plus production on the live dates | A failure found in November can still be fixed before 31.12, and only the live dates prove the production environment |
| Scope | S-39, S-40, S-41, S-42, S-43 (not only the three in the roadmap) | Every M-5 change around the year turn gets evidence, including the north star at 00:00 |
| Where results go | `checks.md` with a step table like `rehearsal.md`; Progress ticks the blocks | Each check keeps its expected result next to what was seen, and the archived clock rows get one place with evidence |
| MCP part | Read check after each deploy (`/up` + To Spend equals Dashboard), no cash entry | Catches read regressions without leaving a trace in the budget; the write path already has S-47 evidence |
| Fixes | A failure opens a separate fix change; S-48 waits for its deploy | Keeps this change a record of evidence, not a mix of evidence and code |

## Scope

**In scope:**

- `checks.md` with expected results;
- README „Co sprawdzić 1–2 stycznia” aligned with S-43 and S-40;
- the S-48 Unknowns answered in the roadmap;
- the rehearsal on a copy in this worktree's own database, closing archived rows S-40 2.9/2.10 and S-41 2.6/3.7;
- production checks after the deploy, in December and on 1–2.01;
- the MS-12 verdict.

**Out of scope:**

- the MCP cash write and `MCP_WRITE_ENABLED` again;
- fixing what a check finds;
- editing archived plans;
- the kitchen-sink and wording rows from the archives;
- automating production checks.

## Architecture / Approach

There is no code. The checks are written first, with expected results taken from the code, so a check cannot pass on whatever the screen shows. Then the evidence comes in calendar order:

- the agent runs the copy rehearsal (`user:backup` from the owner, then `user:restore` and `app:rehearsal-clock` locally, with `playwright-cli`);
- the owner runs the production steps;
- the agent writes each result into `checks.md` and ticks Progress.

Only the owner touches production (`infrastructure.md:96`); the agent only calls `curl /up`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Checklist and README | `checks.md` with C/D/G/S steps and expected results; README and roadmap aligned | An expected result copied from a plan instead of the code |
| 2. Rehearsal on a copy (November) | C1–C7 on the owner's data with the clock; archived clock rows closed; cleanup incl. VPS snapshot | Choosing 2026 after 23:00 UTC hides the S-40 switch; production data left behind |
| 3. Production after deploy | `/up`, the deployed commit includes S-40, Dashboard and connector agree | The deploy may not have happened yet; Phase 3 waits for it |
| 4. Production in December | Draft-year card and deadline card from 3.12, December approved, 2027 started with „od 1.01”, 2026 chosen before midnight | A missed window cannot be repeated on production this year |
| 5. Production on 1–2 January | Day/year, session year (checked by 01:30 in the G3 tab), „Pełne lata”, YoY, Import across the turn, MS-12 verdict | A failure on 1.01 is found after December can no longer be approved |

**Prerequisites:**

- for Phase 2: the owner's `user:backup` from the VPS and a fresh mBank CSV export;
- for Phase 3: the M-5 deploy (`scripts/deploy.sh`).

**Estimated effort:**

- about one session for Phases 1–2;
- about 10–20 minutes of the owner's time on each of 3.12, 30–31.12 and 1–2.01 (and about 00:00–01:30 on 1.01 for S2);
- the change stays open until 2 January 2027.

## Open Risks & Assumptions

- **Assumption.** The owner starts 2027 on 30–31.12, so the S-42 draft-year path is checked only on the copy (C6). If he does not, G2 and S4 turn into the draft path.
- **Risk.** No error tracker on production (S-45 blocked); a failure there is diagnosed from the file log only.
- **Risk.** A fix deployed after Phase 3 needs D1, D3 and D4 repeated, and possibly a partial re-run of the copy.

## Success Criteria (Summary)

- Every C, D, G and S step in `checks.md` has a result, and every failure has a fix change id.
- The MS-12 verdict says the first real year turn passed without manual fixes, which is the M-5 „Done when”.
- No copy of production data is left anywhere outside production.
