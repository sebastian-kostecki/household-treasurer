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
- seed: tests/e2e/seed.spec.ts — protects #7 (a critical browser flow — here the operation dialog: add expense, list, reload, delete — stops working although HTTP and Vitest tests pass)
- browser CLI: playwright-cli (session `-s=household-treasurer`), command skill at .claude/skills/playwright-cli/SKILL.md
- updated: 2026-11-06
