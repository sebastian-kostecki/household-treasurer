<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Production Smoke Tests (S-48)

- **Plan**: context/changes/production-smoke-tests/plan.md
- **Scope**: Phases 1–3 of 5 (completed phases; 4 and 5 are calendar-bound, December 2026 and 1–2 January 2027)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-20
- **Verdict**: REJECTED
- **Findings**: 1 critical, 5 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | WARNING |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated criteria, re-run during this review: 1.1 (19 step rows, at least 15 required), 1.2 (no „liczony „do 1.01”” in README), 1.3 (every quoted UI string found in `lang/pl/`, `app/` or `resources/js/`), 2.1 („zegar próby wyłączony”), 2.2 (no `preview_production_smoke_tests` database), 2.3 (`storage/app/restore-trial/` empty, no `.playwright-cli`), 3.1 (`HTTP/2 200`). All pass.

The REJECTED verdict comes only from F1, a data-safety finding. The fix is cheap because the commits are still local.

## Triage

The owner chose „napraw samodzielnie” (2026-11-20): all ten findings fixed by the agent; F6 via Fix B. Fixed: F1–F10 (10).

## Findings

### F1 — Owner's To Spend amount committed to a public repo

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/changes/production-smoke-tests/checks.md:31 (D4); commits caa8adc9, 1d38e86d
- **Detail**:
  - D4 records the owner's real production To Spend amount twice (from the connector and from the Dashboard).
  - plan.md:38 says „Nothing personal from the production data is in the repo”. The checks.md:3 header forbids single amounts.
  - `gh repo view` reports `isPrivate: false`.
  - Neither commit is on any remote yet (`git branch -r --contains caa8adc9` is empty). Once pushed, the amount stays in public history.
  - D4 only needs equality, so the number adds nothing as evidence.
- **Fix**: Replace both amounts with „równe: tak”. Then rewrite caa8adc9 and 1d38e86d before any push: `git reset --soft 563294fc` and commit again, or `git commit --fixup` + `GIT_SEQUENCE_EDITOR=: git rebase -i --autosquash 563294fc`. Check that `git log -p 563294fc..HEAD` no longer contains the amount.
  - Strength: Removes the only real money figure in the change, before it is public. The history is unpushed, so the rewrite affects no one.
  - Tradeoff: The two commits (D3/D4 and closing phase 3) get new SHAs. Nothing references them yet.
  - Confidence: HIGH — local-only branch, verified.
  - Blind spot: Whether the amount was copied anywhere outside the repo (another worktree, notes) was not checked.
- **Decision**: FIXED — D4 reads „równe: tak”; caa8adc9 and 1d38e86d replaced by 19264c07 (git reset --soft 563294fc + one commit); `git log -p 563294fc..HEAD` has no amount; not pushed before the rewrite.

### F2 — Progress 2.6 ticked, but checks.md still says the owner must delete the snapshot

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/production-smoke-tests/checks.md:55, :62; plan.md:399
- **Detail**:
  - Commit 563294fc („usunięcie snapshotów z VPS (2.6)”) ticks 2.6, „The owner confirms the snapshot is deleted from the VPS”.
  - checks.md still reads „Done … except the last item (owner)” and „OWNER TO DO: delete this snapshot … (and the 2026-11-18 one)”.
  - Nothing in the repo says who ran the deletion or whether both snapshots are gone. Only the human may write on halina175 (infrastructure.md:96).
- **Fix**: Ask the owner who deleted the files and whether both are gone. Change checks.md:55/:62 to „deleted by the owner on 2026-11-20 (both)”, or untick 2.6.
- **Decision**: FIXED — checks.md „Sprzątanie” records the owner's confirmation from Progress 2.6 (both snapshots). Assumption: 2.6 was ticked on the owner's word; who ran the deletion is still not recorded.

### F3 — „Przebieg” has no code commit and no snapshot SHA-256 match

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/production-smoke-tests/checks.md:7-8
- **Detail**:
  - The plan contract (plan.md:95) and Phase 2 step 1 (plan.md:183, „SHA-256 must match on both sides”) require these fields.
  - Both are still `_(fill in …)_` placeholders, although all of Phase 2 is ticked.
  - The template `rehearsal.md:7-12` fills them.
- **Fix**: Fill in the commit, the `user:backup` time, the snapshot version and the SHA match from the session. If any of them was not recorded, write „nie zapisano”.
- **Decision**: FIXED — code commit 126b4250 and backup time 2026-11-20 07:07:08 UTC (from the file name) filled in; snapshot version and SHA-256 match written as „nie zapisano”.

### F4 — G1 still expects „Dodaj rok 2027”; the card opens „Nowy budżet”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/production-smoke-tests/checks.md:40 (G1); plan.md:108, :126, :289
- **Detail**:
  - C7 found the missing-year card opens the dialog titled „Nowy budżet” for 2027 (`DashboardNotices.vue:242-250` → `create_budget_title`, ui.php:302). „Dodaj rok :year” (ui.php:18) is the year-menu item.
  - The wrong expectation comes from S-41 2.6's wording and went into the plan's Phase 1 contract.
  - The expectation is wrong, not the behaviour, so this is not a failure that needs a fix change. G1 runs on 3–5.12 with the wrong text.
- **Fix**: Change G1 (and plan.md:108) to „opens „Nowy budżet” with year 2027” before 3 December.
- **Decision**: FIXED — G1, plan.md S-41 contract and the C7 clock row now say the „Nowy budżet” dialog for 2027.

### F5 — C5 does not prove the January proposal rule, and S4 repeats an imprecise expectation

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: context/changes/production-smoke-tests/checks.md:20 (C5), :51 (S4); README.md:330, :346
- **Detail**:
  - The C5 and S4 expected result is „the January proposal copies December amounts for fixed charges and items without a rhythm”.
  - The code (`app/Support/MonthPlanProposal.php:228-260`) does this:
    - Fixed Charge gets the last paid net actual of December, and December's plan only when nothing was paid;
    - Even Annual and Irregular get the annual share;
    - everything else gets December's plan amount, matched by Category.
  - C5 reports „32 z 48 kwot jak w grudniu, 16 innych (rytmy roczny i nieregularny…)”. The per-rhythm split is asserted, not counted. A Fixed Charge that correctly differs from December's plan would be counted as „inna” and hide behind the explanation.
  - README step 4 (:330) and the checklist line (:346) say the same imprecise thing. The warning at :331 hints at the real rule.
- **Fix**: Restate the S4 expected result per rhythm: Fixed Charge = December's last net payment, no rhythm or current = December's plan, annual or irregular = annual share. Have S4 record counts per rhythm. Align README:330/:346 with the code in the same edit. Leave C5 as is, but annotate it: „podział na rytmy nie policzony; dowód w S4”.
  - Strength: The one January check left on live data then tests the rule the code actually implements. README is the owner's own checklist, and S-steps quote it.
  - Tradeoff: README wording changes outside the planned „Lata” line, so it is a small scope addition to Phase 1's README contract.
  - Confidence: HIGH — read the proposal code directly.
  - Blind spot: Whether the C5 copy actually had a Fixed Charge that differed was not checked. The data is gone.
- **Decision**: FIXED — S4 expected result and plan.md S4 contract restated per rhythm with counts per rhythm; C5 annotated „dowód w S4”; README step 4 and the January checklist line aligned with MonthPlanProposal.

### F6 — Agent performed owner steps on production

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: context/changes/production-smoke-tests/checks.md:29-31 (D2, D3, D4); plan.md:32, :252-255; context/foundation/infrastructure.md:96
- **Detail**:
  - Plan.md:32 allows the agent only `curl -I /up` and local work. D2–D5 are owner steps.
  - D2 records „odczyt agenta przez SSH”: `git rev-parse`, `merge-base --is-ancestor` and container inspection on halina175. infrastructure.md:96 allows read-only *log* reads over SSH if the human grants it.
  - D3 (production login page in a browser) and D4 (production budget through the claude.ai connector) were also done by the agent. D4 is how the F1 amount entered the repo.
  - Nothing indicates a write, and the owner confirmed each result afterwards. The command list and the SSH grant are not recorded.
- **Fix A ⭐ Recommended**: Record the allowance. In infrastructure.md „Approval”, add read-only `git`/`docker ps|inspect` over SSH and MCP reads, with the rule that values from them stay out of the repo. Add a one-line addendum to plan.md Phase 3 saying D2–D4 were read by the agent and confirmed by the owner.
  - Strength: Matches what happened and what the owner accepted. The next production check (D1b…, G, S) has a clear boundary.
  - Tradeoff: Widens what the agent may do on production without supervision.
  - Confidence: MED — depends on whether the owner granted SSH deliberately for this.
  - Blind spot: The exact commands run on halina175 are not in the repo.
- **Fix B**: Keep D/G/S steps owner-only. Annotate D2–D4 „odczyt agenta, potwierdzony przez właściciela” and change nothing else.
  - Strength: Keeps the existing approval rule strict.
  - Tradeoff: Repeats (D1b, D3b, D4b) cost the owner more time, and the agent's earlier reads stay outside the written rule.
  - Confidence: HIGH — no doc change needed.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix B — D2–D4 already carry „agent read, owner confirmed”; added a note under the D-table that these are owner steps and production values are never written down. infrastructure.md „Approval” unchanged (widening the agent's production access is the owner's call).

### F7 — C4 is partial, and the gaps have no pointer to where they get checked

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/production-smoke-tests/checks.md:19
- **Detail**:
  - C4 is „CZĘŚCIOWO OK”. Three things were not checked: the operation dialog's default date, the refusal of a future date, and the Category Panel „Lata” tab.
  - Progress 2.4's named rows (S-40 2.9/2.10, S-41 2.6, 3.7) are still covered.
  - S-43 2.8 (the panel shows „Pełne lata”) stays unticked in its archive. The Phase 2 contract (plan.md:205) wants failures written as „nie”.
- **Fix**: Append „→ S1 (data dialogu, data z przyszłości), S3 (Panel kategorii „Lata”)” to C4.
- **Decision**: FIXED — C4 points the unchecked items to S1 and S3.

### F8 — D3 lacks the „current month bar” observation

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/production-smoke-tests/checks.md:30
- **Detail**: The D3 expected result includes „November as the current month bar (no day)”. The result records „Rok budżetu”, „2026 · 88%” and `shell.today`, but not the month bar. 3.2 is ticked anyway.
- **Fix**: Record the month bar at D3b (the next deploy repeat), or note it as not checked in D3.
- **Decision**: FIXED — D3 notes the month bar was not recorded and is to be checked at D3b.

### F9 — Automated Progress rows without commit SHAs

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/changes/production-smoke-tests/plan.md:391-405
- **Detail**: The Progress convention (plan.md:373) appends ` — <sha>`. Phase 1 does, and so does the archived `2026-11-18-year-turn-rehearsal/plan.md`. Rows 2.1–2.3 and 3.1 do not.
- **Fix**: Append „ — 2cd875e4” to 2.1–2.3 and „ — 563294fc” to 3.1. The F1 rewrite starts after 563294fc, so both SHAs stay valid.
- **Decision**: FIXED — 2.1–2.3 carry 2cd875e4, 3.1 carries 563294fc.

### F10 — README year-turn lines: the panel clause and a near-duplicate

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: README.md:345, :347-348
- **Detail**:
  - Line 347 reads as if „Do 1 stycznia” were one click away in the Category Panel too. The panel has no range switch (ADR-0101, ADR-0108).
  - Line 348 („Budżet i Plan miesiąca otwierają się na N+1…”) partly repeats line 345 („Budżet otwiera się na N+1 bez ręcznej zmiany roku w menu”).
  - Both lines are otherwise accurate against `ReportYearsRange`, `report-years.ts`, `SelectedBudgetYear` and `budget-year-menu.ts:52`.
- **Fix**: Move „(i zakładka „Lata” w Panelu kategorii)” after the one-click clause or into its own sentence. Merge line 348 into line 345.
- **Decision**: FIXED — README: the panel clause separated from the one-click range switch; the S-40 line merged into the Budget line.
