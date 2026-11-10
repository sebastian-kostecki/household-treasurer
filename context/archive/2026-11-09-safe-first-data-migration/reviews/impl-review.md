<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Safe First Data Migration

- **Plan**: context/changes/safe-first-data-migration/plan.md
- **Scope**: Full plan (Phases 1–3 of 3)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-09
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Re-run during review: `bash -n scripts/deploy.sh` (OK), `grep` for `backup:run` (line 41) before `migrate --force` (line 44), `ApplicationBackupRestoreTest` on SQLite (4 passed) and on PostgreSQL (4 passed), `index.md` contains both new rules (count 2), forbidden-term grep over `.ai/rules/` (empty), the six `file:line` references of `categories.md` match the code, `git check-ignore` confirms `storage/app/restore-trial/` is ignored. Full `composer ci:check` was not re-run (the diff since the last green run is docs/config only).

## Findings

### F1 — README says archives are encrypted, production probably has them unencrypted

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: README.md:144, config/backup.php:76, context/changes/safe-first-data-migration/restore-trial.md (Uwagi)
- **Detail**: README states "Archiwum jest szyfrowane hasłem `BACKUP_ARCHIVE_PASSWORD`", but `config/backup.php:76` encrypts only when the variable is set (`.env.prod.example:68` ships it empty). The restore trial succeeded with a fake password, so production archives are most likely plain zips. Since F-04 every deployment adds another full copy (dump plus `storage/app`, including financial data) to the host, and the finding sits only in `restore-trial.md` as "do rozstrzygnięcia osobno" with no tracked follow-up.
- **Fix**: Make the README sentence conditional ("szyfrowane, jeśli `BACKUP_ARCHIVE_PASSWORD` jest ustawione") and queue a follow-up in `follow-ups/review-fixes.md` to set the password on the VPS.
- **Decision**: FIXED — README zdanie warunkowe; follow-up o haśle na VPS w `follow-ups/review-fixes.md`

### F2 — A change to `deploy.sh` runs the old script on its first deployment

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: scripts/deploy.sh:18, scripts/lib/production-deploy.sh:16-21
- **Detail**: `production_pull_latest` updates the script while it runs, so bash keeps executing the old file. The trial hit this: the first run after merge had no backup step. It is documented in README, but it recurs whenever `deploy.sh` changes together with a migration, which is exactly the S-18 case if the script is touched then.
- **Fix A ⭐ Recommended**: Leave as is; the README note is enough because `deploy.sh` already has the backup step and S-18 should not need to change it.
  - Strength: no change to a script that was just proven on production.
  - Tradeoff: relies on a human reading the note.
  - Confidence: MED — holds as long as S-18 leaves `deploy.sh` alone.
  - Blind spot: not checked whether other planned changes (S-18 control numbers stopping the deploy) edit `deploy.sh`.
- **Fix B**: Re-exec the script after `git pull` when its hash changed (`exec "$0" "$@"`).
  - Strength: removes the whole class of "old script ran".
  - Tradeoff: more logic in a critical script, needs a guard against loops.
  - Confidence: MED — standard pattern, untested here.
  - Blind spot: interaction with `set -euo pipefail` and `sudo` prompts.
- **Decision**: ACCEPTED (Fix A) — notka w README wystarczy; do sprawdzenia przy planie S-18, czy dotyka `deploy.sh`

### F3 — The production restore path was not exercised end to end

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: README.md ("Przywrócenie produkcji"), restore-trial.md
- **Detail**: The trial proves that a production archive loads locally into a separate database and that control numbers match. The README's production procedure (`artisan.sh down`, revert, `backup:restore` as root in the prod container, `deploy.sh`, `up`) was not run; `psql` credentials in the prod container and root-owned restored files (fixed afterwards by the `chown` in `deploy.sh`) are inferred from code. The plan accepted this ("Nie zmieniamy RestoreApplicationBackup", the trial is local), but the README presents the steps as proven.
- **Fix**: Add one line under "Przywrócenie produkcji": the procedure was verified locally (restore into a separate database); the production run has not been rehearsed, so S-18 should rehearse it on a throwaway database before the first irreversible migration.
- **Decision**: FIXED — zastrzeżenie w README pod „Przywrócenie produkcji”

### F4 — roadmap.md left uncommitted with F-04 "in-progress"

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md (working tree)
- **Detail**: The working tree has an unstaged edit that marks F-03 done and F-04 "in-progress" while all Progress boxes of the plan are `[x]`. It belongs to closing the change, not to this implementation.
- **Fix**: Set F-04 to done when archiving with `/10x-archive`, and commit the roadmap together with it.
- **Decision**: FIXED — F-04 ustawione na done w roadmap.md (wpis w sekcji Done dopisze `/10x-archive`); zmiana niezatwierdzona
