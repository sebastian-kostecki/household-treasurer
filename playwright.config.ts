import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

// Local secrets (E2E_USERNAME, E2E_PASSWORD) come from the gitignored .env.
// In CI the workflow copies .env.example (empty E2E_* values) and the job sets
// the variables itself; loadEnvFile does not overwrite variables that are
// already set, so the job's values win.
if (existsSync('.env')) {
    process.loadEnvFile('.env');
}

// 8000 is the default port of `php artisan serve` (the project sets none;
// Sail already holds 8080 and 5173). scripts/e2e-server.sh runs the same
// built-in server. E2E_PORT overrides the port when 8000 is taken.
const PORT = Number(process.env.E2E_PORT ?? 8000);
const baseURL = `http://localhost:${PORT}`;

export default defineConfig({
    testDir: './tests/e2e',
    forbidOnly: !!process.env.CI,
    // No retries, also in CI: a retry hides a flaky spec (test-plan §5), and a
    // spec that flakes leaves the gate instead.
    retries: 0,
    // One worker, also locally: every spec logs in as the same user through
    // one storageState, so all workers share one database session, and the
    // session driver has no locking. A request of one worker saves the session
    // without what another one has just put in it (the import preview), and a
    // spec that deletes the account another one has picked breaks it too.
    workers: 1,
    // A closed dialog stays in the DOM until its exit animation ends, which on
    // a loaded runner can take longer than the default 5 s. The assertions
    // stay the same; only the wait for them is longer.
    expect: { timeout: 10_000 },
    reporter: process.env.CI
        ? [['list'], ['github'], ['html', { open: 'never' }]]
        : 'html',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /.*\.setup\.ts/ },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'playwright/.auth/user.json',
            },
            // The gate specs (tag @gate) run in the gate project.
            grepInvert: /@gate/,
            dependencies: ['setup'],
        },
        {
            name: 'gate',
            // The month plan and irregular-category flows are long (setup, two
            // bills, plan view, cleanup) and take 27-43 s, so the default 30 s
            // fails them on timing alone. Assertions keep their own timeout.
            timeout: 90_000,
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'playwright/.auth/user.json',
            },
            grep: /@gate/,
            dependencies: ['setup'],
        },
    ],
    webServer: {
        // Production build, then PHP's built-in server in the Sail image on the
        // separate database household_treasurer_e2e (scripts/e2e-server.sh).
        // Refuses to start while public/hot exists (Vite dev server running).
        // On a GitHub runner (no Docker network of Sail) the job has built the
        // app and seeded the database: scripts/e2e-server-ci.sh only serves it.
        command: process.env.GITHUB_ACTIONS
            ? `bash scripts/e2e-server-ci.sh ${PORT}`
            : `bash scripts/e2e-server.sh ${PORT}`,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 600_000,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 },
    },
});
