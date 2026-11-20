# Production Smoke Tests (S-48) Implementation Plan

## Overview

The owner checks that the M-5 changes work on production, on his own data, and records the result (roadmap S-48, MS-12). Every year-turn change of M-5 (S-39, S-40, S-41, S-42, S-43) shows only on certain calendar days, and the rehearsal clock runs only locally. So the checks come in four time windows:

1. a rehearsal on a local copy of the production data now, while a fix can still ship before 31.12;
2. a read check on production after the M-5 deploy;
3. production in December;
4. production on 1–2 January 2027.

Every step and its result go into one `checks.md` file. This change stays open until 2 January 2027.

## Current State Analysis

- **Code on main.** `origin/main` holds all of `development`, including S-40 (PR #279, merged 2026-11-20 06:18). Whether `scripts/deploy.sh` has already run with that commit is not recorded anywhere. `scripts/deploy.sh:90` ends with `echo "==> Done. Check https://budget.kostecki.dev/up"`; it never requests `/up` itself.
- **MCP part of S-48 already confirmed.** The S-47 plan confirmed it on production on 2026-11-19 (`context/archive/2026-11-19-mcp-read-only-server/plan.md`, rows 4.4–4.6 and 6.3–6.5): connector on claude.ai, Desktop and phone; To Spend equals Dashboard; one cash entry; `MCP_WRITE_ENABLED` switch; "Needs approval".
- **The rehearsal clock is local only.** Both guards check `isLocal()`:
  - `app:rehearsal-clock` (`app/Console/Commands/RehearsalClockCommand.php:24-28`);
  - `RehearsalClock::apply()` (`app/Support/RehearsalClock.php:24-35`, guard at `:26`), called at boot from `AppServiceProvider.php:86`, so in every process. The clock is the file `storage/app/private/rehearsal-clock` of the checkout that serves the app.
  - Since ADR-0103 the browser takes the day from `shell.today` (`app/Support/ShellState.php:45`), so faking the server clock is enough.
  - A clock jump of more than 2 h forward logs the user out (the session lifetime is 120 min).
- **The 2026-11-18 rehearsal is the template.** `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md`:
  - procedure: `user:backup` on the VPS, then over `ssh`, then `user:create` + `user:restore` locally;
  - step table: Krok / Zegar serwera / Co sprawdzić / Oczekiwany wynik / Wynik;
  - no personal data, counterparties or single amounts;
  - cleanup list at the end.
- **Manual rows left unchecked in the archives that only a clock run can close:**
  - S-40 `2.9` (clock 22:30 → 23:05 UTC, 2026 gives way to 2027 with no toast) and `2.10` (2023 chosen for browsing in a second session stays);
  - S-41 `2.6` (the missing-year card opens „Dodaj rok”, the draft-year card leads to Budget) and `3.7` (browser check with the rehearsal clock around the Polish midnight).
- **README is out of date for January.** The year-turn checklist (`README.md:342-349`) still says „Raport „Lata”: każdy rok liczony „do 1.01””. Since S-43 (ADR-0108), „Lata” opens on „Pełne lata” throughout January; the exception is the first year with a budget (`resources/js/lib/report-years.ts:18-24`, `app/Support/ReportYearsRange.php`).
- **Who may do what.** Only the human touches production (`context/foundation/infrastructure.md:96`): `deploy.sh`, `.env.prod`, `user:restore`, VPS shell. The agent may run `curl -I https://budget.kostecki.dev/up` and anything local.

## Desired End State

`context/changes/production-smoke-tests/checks.md` holds four step tables (copy, after deploy, December, January). Each step has an expected result written before the check and an actual result written after it. The file ends with a short MS-12 verdict: either "M-5 works on production without manual fixes" or a list of failures, each with the id of the change that fixes it.

The README year-turn checklist matches the code. The roadmap S-48 entry has its Unknowns answered. Nothing personal from the production data is in the repo.

### Key Discoveries:

- **Rehearsal procedure** and its known traps (logout on a >2 h jump, a year chosen after the forced relogin counts as a choice in N+1): `README.md:350`.
- **Choosing the session year.** To see the session year switch by itself, the year 2026 must be chosen before 23:00 UTC on 31.12 (`README.md:350`, ADR-0111).
- **Draft-year Import path.** It appears only while 2027 is still a draft. On production the owner starts 2027 on 30–31.12, so that path is checkable only on the copy, after `user:reset-data` + `user:restore` (rehearsal step T5).
- **Preview against a database override.** `artisan serve` drops a `-e DB_DATABASE` override and silently hits the dev database; serve the preview with `php -S` or through `.env`, then prove which database answers (`context/foundation/agents/worktree-preview.md`).

## What We're NOT Doing

- Re-running the MCP cash write or flipping `MCP_WRITE_ENABLED` on production; the S-47 evidence from 2026-11-19 stands.
- Fixing anything a check finds. A failure opens a separate fix change (`/10x-new`), and this change waits for its deploy before re-running the failed step.
- Editing archived plans to tick their Manual rows. `checks.md` names which archived rows a step covers.
- Kitchen-sink and wording rows from the archives (S-40 1.3/2.11, S-41 1.5/2.5/3.6, S-43 1.3/2.7–2.9): they are about the dev pages and documents, not about production.
- Automating production checks (`deploy.sh` calling `/up`, a smoke script, Playwright against production). Automatic deploy is parked in the roadmap.
- Repeating the rehearsal after every later deploy. Only the Phase 3 read check is repeated.

## Implementation Approach

A checklist first, then the evidence in calendar order.

1. **Phase 1** writes the expected results from the delivered behaviour of each slice, so a check cannot be "passed" by whatever the screen happens to show. It also brings the README checklist in line with S-43.
2. **Phase 2** replays the year turn on a copy of the owner's data in this worktree's own database. It closes the four archived clock rows and catches failures while there is time to deploy a fix before 31.12.
3. **Phases 3–5** are run by the owner on production on the named days. The agent records each result in `checks.md` and ticks Progress.

## Critical Implementation Details

**Timing & lifecycle.** Phases 4 and 5 are bound to the calendar:

- 3–5.12 for the draft-year card and the December deadline card (the deadline card appears only from day 3, `DashboardNotices.php:241`);
- 30–31.12 for approving December and starting 2027;
- 2026 chosen in the year menu at 23:00–23:59 Polish time on 31.12 (before 23:00 UTC), in a tab that stays open;
- S2 checked in that same tab by about 01:30 Polish time on 1.01, without logging out. The session year lives only in the session (`SelectedBudgetYear.php:37-45`), and the session expires after 120 min idle. A later check runs in a new session that defaults to 2027 whether or not S-40 works, so its result reads "nie dowodzi (nowa sesja)" and points to C4;
- 1–2.01 for the rest.

A missed window cannot be repeated on production that year; the step's result then reads "missed" and points to the copy evidence from Phase 2.

**Debug & observability.** Production has no error tracker yet (S-45 blocked). If a production step fails, the owner reads the file log `storage/logs/laravel.log` on the VPS for the minute of the check before anything else, and `checks.md` quotes the exception class and message, not the stack.

## Phase 1: Checklist and README

### Overview

Write `checks.md` with every step, its date or clock, what to look at and the expected result. Align the README year-turn checklist with S-43, and record the S-48 decisions in the roadmap.

### Changes Required:

#### 1. Checklist

**File**: `context/changes/production-smoke-tests/checks.md`

**Intent**: One place for every M-5 check and its result, in the format of `rehearsal.md`, so the owner can run each window without reading the plans of five slices.

**Contract**: These sections, in this order:

- **Header** with the privacy rule: no personal data, counterparties or single amounts; row counts and yes/no allowed.
- **„Przebieg”** listing the code commit, the snapshot time and version, and the deployed commit.
- **Four step tables**, columns Krok / Kiedy (data albo zegar UTC) / Co sprawdzić / Oczekiwany wynik / Pokrywa / Wynik:
  - `C1…` copy (Phase 2), `D1…` after deploy (Phase 3), `G1…` December (Phase 4), `S1…` January (Phase 5);
  - „Pokrywa” names the slice and any archived row, e.g. `S-40 2.9`.
- **„Sprzątanie”** list.
- **„Werdykt MS-12”** section, left to fill.

The expected results come from the delivered behaviour of each slice; quote the UI copy from the code (`lang/pl/ui.php`, `lang/pl/validation.php`), not from the plans. Several strings are templates (`'Do :day :month'`, `'Dodaj rok :year'`, `'zatwierdź do :date, 23:59'`); `checks.md` gives the rendered text:

- **S-39:** menu, Dashboard and the operation dialog's default date show the same day and year around the Polish midnight; a future date is refused with „Data nie może być w przyszłości.”. The Dashboard date includes the weekday („piątek, 1 stycznia 2027 · budżet 2027 w realizacji”).
- **S-40:** 2026 chosen in the menu during 2026 gives way to 2027 from 00:00 Polish time (23:00 UTC), with no message, in Budget and Month Plan. 2023 chosen for browsing stays.
- **S-41:**
  - Dashboard „Wymaga uwagi” card naming the approval deadline („zatwierdź do 31.12, 23:59”), only while December is a proposal and from 3.12 on;
  - in December the draft-year card („Budżet w szkicu · Budżet 2027 · Import nie przyjmie wierszy z 2027”, opens Budget 2027), or, with no Budget 2027, the „Brak budżetu · Budżet 2027” card, which opens „Dodaj rok 2027”. The missing variant exists only in December; in January there is no card for a missing year;
  - after starting 2027, the „od 1.01” mark in the year menu and „budżet 2026 w realizacji · budżet 2027 od 1 stycznia” on the Dashboard.
- **S-42:**
  - with 2027 a draft, the January rows carry the note „Budżet 2027 jest szkicem…” with „Przejdź do Budżetu 2027”, and a ticked January expense, income or refund blocks „Zatwierdź import”; unticking saves December;
  - with 2027 started, December operations land in 2026 Budget lines and January ones in 2027 lines.
- **S-43:** „Lata” and the „Lata” tab of the Category Panel open on „Pełne lata” with 2027 „w toku”, and „Do 1 stycznia” / „Do 2 stycznia” is one click away. Year-over-year on 1 January compares 1.01.2027 with 1.01.2026.
- **MCP (D-steps only):** `/up` answers 200, and the claude.ai connector's To Spend equals the Dashboard.

The C-steps follow the clock order below:

| Krok | Zegar (UTC) | Co sprawdzić |
| --- | --- | --- |
| C1 | 2026-12-03 09:00 | S-41 draft-year card (opens Budget 2027) and December deadline card |
| C2 | 2026-12-30 10:00 | approve December, start 2027, „od 1.01”, Dashboard still 2026 |
| C3 | 2026-12-31 22:30 | relogin after the jump, choose 2026; a second browser session chooses 2023 |
| C4 | 2026-12-31 23:05 | S-40 switch, S-39 day/year, S-41 3.7, „Lata” and YoY on 1.01 |
| C5 | 2027-01-02 09:00 | Import 29.12–02.01 with 2027 started |
| C6 | 2027-01-02 09:00 | after `user:reset-data` + `user:restore`: S-42 draft path and the current-year draft card |
| C7 | 2026-12-03 09:00 | after `user:reset-data` + `user:restore` and deleting the 2027 draft: „Brak budżetu · Budżet 2027” opens „Dodaj rok 2027” (S-41 2.6) |

The S1 steps mirror the README list „Co sprawdzić 1–2 stycznia”.

#### 2. README year-turn checklist

**File**: `README.md` (section „Przełom roku”, list „Co sprawdzić 1–2 stycznia”)

**Intent**: The list is the owner's own year-turn checklist. Its „Lata” line contradicts S-43, and it lacks the S-40 and S-41 checks, so it must match the code before the S-steps quote it.

**Contract**: Replace the „Lata” line with the ADR-0108 behaviour (opens on „Pełne lata”, 2027 „w toku”, „Do 1 stycznia” one click away). Add one line saying that Budget and Month Plan open on 2027 even if 2026 was chosen in December, and that the „od 1.01” mark is gone. Other lines stay.

#### 3. Roadmap S-48 decisions

**File**: `context/foundation/roadmap.md` (S-48 body)

**Intent**: Record the answers to the S-48 Unknowns, so the roadmap no longer asks them.

**Contract**: Change only the `Unknowns` line of S-48. It reads as answered:

- scope S-39–S-43;
- copy now plus live dates;
- MCP read check only after deploy;
- results in `checks.md`.

### Success Criteria:

#### Automated Verification:

- `checks.md` exists with the four step tables and every row has a non-empty „Oczekiwany wynik”: `grep -c '^| [CDGS][0-9]' context/changes/production-smoke-tests/checks.md` is at least 15
- README no longer says „Lata” counts each year „do 1.01”: `grep -n 'liczony „do 1.01”' README.md` returns nothing
- Every UI string quoted in `checks.md` exists in `lang/pl/`, `app/` or `resources/js/`, a templated one as its template (checked one by one with `grep -rn`)

#### Manual Verification:

- The owner reads `checks.md` and agrees with the expected results before Phase 2

**Implementation Note**: Pause after this phase for the owner's confirmation of the expected results.

---

## Phase 2: Rehearsal on a copy (November)

### Overview

Replay December and the year turn on a fresh copy of the owner's production data, in this worktree's own database, and fill the C-table.

### Changes Required:

#### 1. Snapshot and local copy

**Files**: none in the repo. The work lives in `storage/app/restore-trial/` (gitignored) and the database `preview_production_smoke_tests`.

**Intent**: Real data with a faked clock, isolated from the dev database and from the other worktrees.

**Contract**:

1. The owner runs `scripts/artisan.sh user:backup <id>` on the VPS and copies the file into this worktree's `storage/app/restore-trial/prod-user.json` over `ssh`; SHA-256 must match on both sides.
2. The owner also supplies a fresh mBank CSV export from an account that exists in the restored data. The agent shifts its dates to 29.12.2026–02.01.2027.
3. The agent creates `preview_production_smoke_tests`, migrates it, runs `user:create` for a rehearsal user and `user:restore --force`, and serves the worktree per `worktree-preview.md`. Before C1 the agent proves which database answers.

#### 2. Clock steps C1–C7

**File**: `context/changes/production-smoke-tests/checks.md` (C-table „Wynik” column)

**Intent**: Evidence for every year-turn change on the owner's data before 31.12.

**Contract**:

- `app:rehearsal-clock "<UTC>"` is run for each C-step, and the browser is driven with `playwright-cli`.
- Log in again after every jump of more than 2 h.
- C3 must choose 2026 before the clock passes 23:00 UTC.
- C6 starts from `user:reset-data` + `user:restore` of the same snapshot.
- C7 sets the clock back to 2026-12-03 09:00 (the command limits only against the real now), runs `user:reset-data` + `user:restore` again, and deletes the 2027 draft on the copy (`BudgetPolicy::delete` allows drafts).
- A failure is written as "nie" with what was seen instead. It stops the phase only if a later step depends on it.

#### 3. Cleanup

**File**: `checks.md` („Sprzątanie”)

**Intent**: Leave no copy of the production data behind.

**Contract**:

- `app:rehearsal-clock --off`;
- `user:reset-data` of the rehearsal user;
- drop `preview_production_smoke_tests`;
- empty `storage/app/restore-trial/`;
- remove `.playwright-cli`;
- the owner deletes this snapshot from `storage/app/private/user-snapshots/` on the VPS. The 2026-11-18 one is there too; Open Roadmap Question 3.

### Success Criteria:

#### Automated Verification:

- After cleanup the clock is off: `artisan app:rehearsal-clock` run in the `sail-8.5/app` image (the `docker run` command from AGENTS.md "Commands", worktree mounted) prints „zegar próby wyłączony”
- The preview database is gone: `docker exec household-treasurer-pgsql-1 psql -U sail -d postgres -tc "select 1 from pg_database where datname='preview_production_smoke_tests'"` returns nothing
- `storage/app/restore-trial/` holds no snapshot or CSV file

#### Manual Verification:

- Every C-step has a result. S-40 2.9 and 2.10 are confirmed at C3–C4, S-41 2.6 at C1 and C7, and S-41 3.7 at C4.
- Every failure has a fix change opened, and its id is written in `checks.md`
- The owner confirms the snapshot is deleted from the VPS

**Implementation Note**: Pause after this phase. If a fix change was opened, Phase 3 waits for its deploy.

---

## Phase 3: Production after the M-5 deploy

### Overview

Confirm that production runs the M-5 code and that its read paths still agree.

### Changes Required:

#### 1. D-steps

**File**: `checks.md` (D-table)

**Intent**: Prove that the deploy landed and that nothing on today's screens regressed. The year-turn behaviour itself is not visible yet.

**Contract**:

- **D1** (agent): `curl -I https://budget.kostecki.dev/up` returns 200.
- **D2** (owner): records the commit `deploy.sh` deployed (`git rev-parse --short HEAD` on the VPS); it must contain `f48b8363` (S-40): `git merge-base --is-ancestor f48b8363 HEAD && echo ok`.
- **D3** (owner): the Dashboard shows today's date with the weekday and „budżet 2026 w realizacji”; the login screen shows „Rok budżetu”, „2026 · <%>” and November as the current month bar (it shows no day).
- **D4** (owner): the claude.ai connector, asked for To Spend, answers the Dashboard's value for the same `app_day`.
- **D5** (owner): the year menu shows 2026 as current with no „od 1.01” mark; „Lata” opens on „Do <today>”.

Repeat D1, D3 and D4 after any later deploy before 31.12, as extra rows `D1b`… in the same table.

### Success Criteria:

#### Automated Verification:

- `/up` returns 200: `curl -sI https://budget.kostecki.dev/up | head -1`

#### Manual Verification:

- D2–D5 recorded in `checks.md` with the deploy date

**Implementation Note**: Pause until 3 December.

---

## Phase 4: Production in December

### Overview

The owner checks the S-41 notices and prepares the S-40 live check on the real dates.

### Changes Required:

#### 1. G-steps

**File**: `checks.md` (G-table)

**Intent**: Evidence for the pre-turn notices on live data, and the year turn order from README steps 1–2.

**Contract**:

- **G1** (3–5.12): the draft-year card for 2027 is present and opens Budget 2027. If December is unapproved, the December card names „zatwierdź do 31.12, 23:59”.
- **G2** (30–31.12): approve December (the December card disappears). Start 2027. The year menu shows the „od 1.01” mark, the Dashboard says „budżet 2026 w realizacji · budżet 2027 od 1 stycznia”, and the draft-year card is gone.
- **G3** (31.12, 23:00–23:59 Polish time): choose 2026 in the year menu, open Budget, and do not approve January. Leave the tab open and record the time of the choice.

### Success Criteria:

#### Manual Verification:

- G1–G3 recorded in `checks.md` with their dates
- 2026 is chosen in the session at 23:00–23:59 Polish time on 31.12, with the time recorded

**Implementation Note**: Pause until 1 January.

---

## Phase 5: Production on 1–2 January 2027

### Overview

The owner checks the year turn on live data, and the agent writes the MS-12 verdict.

### Changes Required:

#### 1. S-steps and verdict

**File**: `checks.md` (S-table, „Werdykt MS-12”)

**Intent**: Evidence that the first real year turn passes without manual fixes. This is the M-5 „Done when”.

**Contract**:

- **S1** (1.01, from 00:00 Polish time if awake, otherwise first thing in the morning): the menu, the Dashboard („piątek, 1 stycznia 2027 · budżet 2027 w realizacji”) and the operation dialog's default date agree on 1 January 2027.
- **S2** (1.01, 00:00 to about 01:30 Polish time, in the G3 tab without logging out): Budget and Month Plan open on 2027 with no message. Record the time. If it is checked later or after a new login, the result reads "nie dowodzi (nowa sesja)" and points to C4.
- **S3**:
  - „Lata” opens on „Pełne lata” with 2027 „w toku”; the Category Panel's „Lata” tab does the same;
  - year-over-year shows 1.01.2027 against 1.01.2026;
  - Budget 2027 shows „zebrane startowe” without „prognoza”.
- **S4** (2.01):
  - import the statement covering 29.12–02.01: December operations land in 2026 lines, January ones in 2027 lines;
  - the January proposal copies December amounts for fixed charges and items without a rhythm;
  - approve January.
- **Verdict**: "M-5 works on production without manual fixes", or a list of failures with fix change ids.

### Success Criteria:

#### Manual Verification:

- S1–S4 recorded in `checks.md`
- „Werdykt MS-12” written, and the owner agrees with it

---

## Testing Strategy

No new automated tests. The behaviour of S-39–S-43 is already covered by their Feature and Unit tests, and S-44 runs the `@gate` E2E specs in CI. This change adds the evidence that tests cannot give: real data, the production environment and the real calendar.

### Manual Testing Steps:

1. Phase 2: C1–C6 on the copy with the rehearsal clock, in clock order, then C7 with the clock set back.
2. Phase 3: D1–D5 after the deploy, repeated after any later deploy.
3. Phase 4: G1 on 3–5.12, G2–G3 on 30–31.12.
4. Phase 5: S1–S4 on 1–2.01, then the verdict.

## Migration Notes

None: no schema or data change. The copy lives only in the worktree's own database and is dropped in Phase 2.

## References

- Roadmap: `context/foundation/roadmap.md` (S-48, MS-12, Open Roadmap Questions 2 and 3)
- Template report: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md`
- Year-turn procedure: `README.md:321-350`
- Delivered behaviour and unchecked rows:
  - `context/archive/2026-11-19-one-app-day/plan.md` (S-39)
  - `context/archive/2026-11-19-session-year-follows-year-turn/plan.md` (S-40)
  - `context/archive/2026-11-19-year-turn-notices/plan.md` (S-41)
  - `context/archive/2026-11-18-import-across-draft-year/plan.md` (S-42)
  - `context/archive/2026-11-19-years-report-in-early-january/plan.md` (S-43)
- MCP evidence: `context/archive/2026-11-19-mcp-read-only-server/plan.md` (rows 4.4–4.6, 6.3–6.5)
- Production approval: `context/foundation/infrastructure.md:96`
- Worktree preview: `context/foundation/agents/worktree-preview.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Checklist and README

#### Automated

- [x] 1.1 checks.md has four step tables with at least 15 rows, each with an expected result — 9a3e6e25
- [x] 1.2 README no longer says „Lata” counts each year „do 1.01” — 9a3e6e25
- [x] 1.3 Every UI string quoted in checks.md exists in lang/pl/, app/ or resources/js/ — 9a3e6e25

#### Manual

- [ ] 1.4 The owner agrees with the expected results before Phase 2

### Phase 2: Rehearsal on a copy (November)

#### Automated

- [ ] 2.1 Rehearsal clock is off after cleanup
- [ ] 2.2 Database preview_production_smoke_tests is dropped
- [ ] 2.3 storage/app/restore-trial/ holds no snapshot or CSV file

#### Manual

- [ ] 2.4 Every C-step has a result; S-40 2.9/2.10 (C3–C4), S-41 2.6 (C1, C7) and 3.7 (C4) covered
- [ ] 2.5 Every failure has a fix change id in checks.md
- [ ] 2.6 The owner confirms the snapshot is deleted from the VPS

### Phase 3: Production after the M-5 deploy

#### Automated

- [ ] 3.1 /up returns 200 on production

#### Manual

- [ ] 3.2 D2–D5 recorded in checks.md with the deploy date

### Phase 4: Production in December

#### Manual

- [ ] 4.1 G1–G3 recorded in checks.md with their dates
- [ ] 4.2 2026 chosen in the session at 23:00–23:59 Polish time on 31.12

### Phase 5: Production on 1–2 January 2027

#### Manual

- [ ] 5.1 S1–S4 recorded in checks.md
- [ ] 5.2 Werdykt MS-12 written and agreed by the owner
