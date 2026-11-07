---
paths:
    - 'app/**/*.php'
    - '.sloppy-baseline.json'
---

# Sloppy

## Run `vendor/bin/sloppy ci` on the host before finishing PHP changes

[Sloppy](https://github.com/heyosseus/sloppy) flags code-quality debt: god methods and classes, queries in loops, possible N+1, duplicated logic. CI runs it as its own step outside `composer ci:check`, so the Sail `ci:check` gate does not cover it. It reads git history and was verified on the host (also from a worktree), not inside the Sail image. In a pull request it reports only findings the change introduced; on a push it scans everything against the baseline.

## Fix a new finding; never regenerate the baseline to hide it

`.sloppy-baseline.json` holds the 26 findings that existed when Sloppy was added, keyed by class and member, not line number, so editing a baselined method does not resurface its finding. Run `vendor/bin/sloppy baseline` only after fixing a baselined finding, so its entry is dropped. See everything with `vendor/bin/sloppy scan --no-baseline --all`, narrowed with `--rule=SL101` or `--path=app/Actions`. There is no `sloppy.php` (defaults: `fail_on: high`, `tests` and `vendor` excluded); add one only for a decision about the whole project.

## Findings are heuristics: check casts and relations before "fixing" an N+1

`SL203` guesses from a property name. `$termDeposit->bank` is a `Bank` enum cast, not a relation, so no eager loading helps. Read the model's `casts()` and relations first.
