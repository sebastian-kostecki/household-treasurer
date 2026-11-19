<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dokumenty zgodne z kodem

- **Plan**: context/changes/docs-match-code/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-19
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Evidence for Success Criteria: `AdrNumberingTest` 2 passed; Pint passed; all grep checks (2.2, 2.3, 3.1–3.4) clean; glossary entries 65 → 59; `composer ci:check` on PostgreSQL (`testing_docs_match_code`, `sail-8.5/app`) 1868 passed, 4 skipped, exit 0; `vendor/bin/sloppy ci` in the Sail image passed (3 medium findings, all in files outside this change). Manual rows 1.5, 2.4, 2.5, 3.7–3.9 are still `[ ]` (pending, not a defect).

## Findings

### F1 — AdrNumberingTest passes when it finds no ADR files

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Unit/AdrNumberingTest.php:8
- **Detail**: `glob(...) ?: []` returns `[]` if `context/foundation/adr` moves or is absent (`.dockerignore` excludes `context/`), and both tests then pass without checking anything. The sibling `tests/Unit/DesignTokensTest.php:22` guards the same case with `expect($matches)->not->toBeEmpty()`.
- **Fix**: Add `expect(adrFileNames())->not->toBeEmpty();` at the start of the first test.
- **Decision**: FIXED

### F2 — Direction and roadmap lines still treat the decision as open

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: context/foundation/development-directions.md:72, :95; context/foundation/roadmap.md:184
- **Detail**: The plan named only :134 and :145 in `development-directions.md`. Row 5 of the table (:72, „Porządek w dokumentach … zdublowany numer ADR 0008 | otwarte”) is exactly what this change closes. Row 4 (:95) still says „podsumowanie roku z decyzją o Year Close”, and `roadmap.md:184` parks „Year Close i Reserve — niezaimplementowane na `main` (AGENTS.md)”, citing an AGENTS.md sentence that no longer exists. AGENTS.md tells agents to read `development-directions.md` before proposing work, so an agent can still treat Year Close as an open decision. The dated section (:27, :38) is out of scope on purpose and stays.
- **Fix**: In the worktree, set row 5 at :72 to done (ADR-0103/ADR-0104) and change :95 to „podsumowanie roku (odczyt; Year Close odpada, ADR-0104)”. Change `roadmap.md:184` in the main checkout, in the same pass as the S-46 status change from Migration Notes.
  - Strength: The fix follows the plan's own split, so it does not touch `roadmap.md` while M-5 is uncommitted in the main checkout.
  - Tradeoff: `roadmap.md:184` stays stale until the post-merge step, and that step is easy to forget.
  - Confidence: HIGH. The lines and the plan's Migration Notes are verified.
  - Blind spot: I have not checked the main checkout's uncommitted `roadmap.md` for other mentions.
- **Decision**: FIXED — directions :72, :95 in the worktree; roadmap.md:184 queued in plan Migration Notes for the main checkout after merge

### F3 — ADR-0066:46 keeps a live Earmark clause with no note

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/adr/0066-*.md:46
- **Detail**: The plan leaves ADR 0066 alone because it names the terms "only among rejected options". That reason does not hold for :46, which sits in the "new wording of clauses" list: „ADR-0030: an Earmark comes from positive leftover on any expense Category…”. This is the same kind of clause as ADR-0063:34, and 0063:34 did get an Amended note. The effect is small because ADR-0030 now carries "Superseded by ADR-0104". ADR-0063:33 ("the block on deleting and on changing the type stays") is already covered by the meaning of its Amended note.
- **Fix**: Append `Amended by ADR-0104: the ADR-0030 point (Earmark) no longer holds, as Earmark left the glossary.` to ADR-0066, and add 0066 to the list in ADR-0104.
- **Decision**: FIXED

### F4 — Criterion 1.4 expects a result that its own contract makes impossible

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/docs-match-code/plan.md:108
- **Detail**: The grep `0008` also returns `adr/0103-cross-import-own-transfer-pair.md:5`. That line is the renumbering note, which the Phase 1 §2 contract required. The criterion expects only `adr/0013:5`, yet row 1.4 is ticked as if it matched.
- **Fix**: Change the 1.4 expectation in the plan to "only `adr/0013…:5` and the renumbering note in `adr/0103…:5`".
- **Decision**: FIXED

### F5 — AGENTS.md:10 names 5 of the 6 removed terms

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: AGENTS.md:10
- **Detail**: The parenthesis lists "Agenda, Cash Expectation, Year Close, Reserve, Earmark" and leaves out Settlement candidate. The plan faulted the old line for the same gap (health-check, fix 10). The new text copies the plan's proposal word for word, so this is not drift, and the 0017–0022 range covers ADR 0020/0022.
- **Fix**: Add „Settlement candidate” to the list in the parenthesis.
- **Decision**: FIXED

### F6 — Small divergences from repo conventions (test hints, renumbering note)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Unit/AdrNumberingTest.php:21, :38; context/foundation/adr/0103-cross-import-own-transfer-pair.md:5
- **Detail**: Both expectations are a bare `toBe([])` with no hint, while siblings such as `ReportsHardcodedUiTest.php` pass a message as the second argument. The Pest diff still shows the file names, so this is about readability. The renumbering note uses „Renumbered from 0008 on …”, while earlier notes use a „Numbering.” paragraph (`0055:23`, `0068:29`) or `> Numbering:` (`0078:39`).
- **Fix**: Add a hint "ADR numbers must be unique; take the next free number" to the second test, and change the note in 0103 to the form „Numbering. Renumbered from 0008 on 2026-11-19: …”.
- **Decision**: FIXED
