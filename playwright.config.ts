import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

// Local secrets (E2E_USERNAME, E2E_PASSWORD) come from the gitignored .env.
// In CI the file is absent and the variables come from the job.
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
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'html',
    use: {
        baseURL,
        trace: 'on-first-retry',
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
            dependencies: ['setup'],
        },
    ],
    webServer: {
        // Production build, then PHP's built-in server in the Sail image on the
        // separate database household_treasurer_e2e (scripts/e2e-server.sh).
        // Refuses to start while public/hot exists (Vite dev server running).
        command: `bash scripts/e2e-server.sh ${PORT}`,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 600_000,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 },
    },
});
