# Parallel changes

Several changes can run at once: one agent session and one worktree per change (`.claude/worktrees/<change-id>`). The roadmap's `## Streams` and each item's `Parallel with` say which items qualify; two changes that edit the same view, calculator or report run one after the other.

## Start the worktree on `development`

`EnterWorktree` branches from `origin/main`, which lags the local `development`. Before reading code, run `git rebase development` in the new worktree and check that `git log -1` shows the current `development` head.

## Own environment per change

The Sail stack belongs to the main checkout: the dev database `household_treasurer`, the test database `testing` and port 8080. A change writes only to its own databases and ports, named after the change id. Below, `<db>` is the change id with `_` for `-`, for example `category_merge`.

Create a database with `docker exec household-treasurer-pgsql-1 psql -U sail -d postgres -c 'create database <name>'`, and drop it the same way once the change is merged.

| Need | Own resource | How |
| --- | --- | --- |
| PostgreSQL tests, gate | database `testing_<db>` | Create it empty, then use the worktree test command from AGENTS.md ("Commands") with `-e DB_DATABASE=testing_<db>`, both for `artisan test` and for `ci:check`. `RefreshDatabase` wipes the database it runs on, so two sessions on one database break each other's runs. |
| E2E (`npx playwright test`) | database `e2e_<db>`, port 8100–8199 | `create database e2e_<db> template household_treasurer_e2e` copies the E2E user, the demo household and the previous-year mock. The copy fails while another session is connected to the template; wait for that run to finish. Then run `E2E_PORT=<port> E2E_DB_DATABASE=e2e_<db> npx playwright test`, with `E2E_USERNAME` / `E2E_PASSWORD` exported from the main checkout's `.env`. The port also names the server container (`scripts/e2e-server.sh`). Pick a port nobody listens on (`ss -ltn`): outside CI Playwright reuses a server it finds on the port, so a busy port runs your tests against another worktree's code. |
| Browser preview | database `preview_<db>`, port 8090+ | `worktree-preview.md`. |

A worktree's new migration runs only on its own databases, so the shared ones keep the schema of `development` until the merge.

## Merging into `development`

1. Bring `development` into the branch first: `git merge development` once the branch is on `origin` (a rebase would need a force push), `git rebase development` while it is still local. Resolve conflicts on the branch, not on `development`.
2. Resolve the conflicts that parallel work produces as follows:
   - **ADR number.** The change that merges second takes the next free number in `context/foundation/adr/`. Rename its file and every `ADR-00NN` reference to it (`grep -rn "ADR-00NN" context .ai app resources tests`). Precedent: S-30 moved from ADR-0076 to ADR-0077.
   - **`context/foundation/roadmap.md`, `glossary.md`, `routes/web.php`.** Keep both sides: each change edits only its own rows, entries and routes.
   - **Migrations.** When both changes add migrations for the same table, check that the timestamps run in the order the schema needs.
3. Run the gate on the merged branch: `npm run build` (regenerates Wayfinder from both sets of routes), then the full gate on `testing_<db>` plus Sloppy. Each branch passed the gate on its own code only, so only this run checks the combined code.
4. Merge into `development` from the main checkout as `Merge branch 'worktree-<change-id>' (S-NN) into development`.
5. Push only when `git rev-list --count origin/development..development` is 0. Otherwise the push would publish the user's unpushed commits, so commit locally and say so in the report.
6. Clean up: `git worktree remove .claude/worktrees/<change-id>`, drop `testing_<db>`, `e2e_<db>` and `preview_<db>`, and `docker rm -f` the preview container.
