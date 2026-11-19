# Test stack

## E2E

<!-- Written by /10x-e2e-setup. Re-run it to change this section; other skills only read it. -->

- runner: Playwright Test, @playwright/test 1.65.0
- config: playwright.config.ts
- single-spec command: npx playwright test tests/e2e/<name>.spec.ts
- full-suite command: npx playwright test
- base URL: http://localhost:8000
- port: 8000 (detected from the `php artisan serve` default; the project sets no port, Sail holds 8080 and 5173; detected default 8000, override with E2E_PORT)
- web server command: bash scripts/e2e-server.sh $PORT (npm run build, then `php artisan migrate --force` and PHP's built-in server with Laravel's router in the sail-8.5/app image on the household-treasurer_sail network, database household_treasurer_e2e; refuses to start while public/hot exists, so stop `composer run dev` first; needs the Sail stack up); reuseExistingServer outside CI
- auth setup project: setup (tests/e2e/auth.setup.ts), credentials from E2E_USERNAME / E2E_PASSWORD in .env; the user lives only in the local database household_treasurer_e2e (`artisan user:create` + `artisan demo:seed-household`, run with the Sail image and `-e DB_DATABASE=household_treasurer_e2e`)
- storageState: playwright/.auth/user.json (gitignored)
- seed: tests/e2e/seed.spec.ts — protects #7 (a critical browser flow stops working although HTTP and Vitest tests pass; here the operation dialog: add expense, list, reload, delete; a gate spec, tag @gate)
- browser CLI: playwright-cli (session `-s=household-treasurer`), command skill at .claude/skills/playwright-cli/SKILL.md
- updated: 2026-11-19

## E2E w CI

<!-- Poza blokiem /10x-e2e-setup: ponowne uruchomienie tej umiejętności nie powinno tej sekcji usuwać. -->

- job `e2e` w `.github/workflows/tests.yml`, równolegle do `ci`, na pushu do `main` i `development` oraz każdym PR; własna usługa PostgreSQL 18
- komenda: `npm run e2e:gate` (`playwright test --project=gate`); projekt `gate` wybiera testy z tagiem `@gate` i zależy od `setup`, projekt `chromium` (lokalnie) ich nie uruchamia
- bramki: `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected`, `import-approve`
- serwer na runnerze: `scripts/e2e-server-ci.sh` (wybierany po `GITHUB_ACTIONS`); build, migrację i seed robi job, skrypt odmawia startu przy `public/hot` lub bez `public/build/manifest.json`
- konto: `e2e@example.test`, hasło `password` jawnie w `env` joba; `php artisan demo:seed-household e2e@example.test` na świeżej bazie joba (nie sekret, baza żyje tylko w trakcie joba)
- `retries: 0`, `trace: 'retain-on-failure'`, projekt `gate` z `timeout: 90_000`, `expect.timeout: 10_000`; przy porażce lub anulowaniu artefakt `playwright-report` (raport HTML, `test-results/` z trace i `storage/logs/`, 14 dni); `expect.timeout` to obejście animacji dialogu bez `prefers-reduced-motion` (issue #277)
- próba stabilności lokalnie: `CI=1` na świeżej bazie, `--project=setup`, potem `--project=gate --no-deps --repeat-each=20`
