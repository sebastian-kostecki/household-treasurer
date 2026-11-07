import { test as setup, expect } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('sign in once and save the session', async ({ page }) => {
    const username = process.env.E2E_USERNAME;
    const password = process.env.E2E_PASSWORD;

    if (!username || !password) {
        throw new Error(
            'Set E2E_USERNAME and E2E_PASSWORD (see context/foundation/test-stack.md, ## E2E)',
        );
    }

    await page.goto('/login');
    // The Inertia/Vue page renders on the client and drops input typed before
    // it mounts. Retry fill + submit until the submit leaves the sign-in page;
    // clear first, because re-filling the same value fires no change event.
    await expect(async () => {
        const email = page.getByRole('textbox', { name: 'Adres e-mail' });
        const passwordField = page.getByRole('textbox', { name: 'Hasło' });

        await email.fill('');
        await email.fill(username);
        await passwordField.fill('');
        await passwordField.fill(password);
        await page.getByRole('button', { name: 'Zaloguj się' }).click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), {
            timeout: 5_000,
        });
    }).toPass();
    await expect(page.getByRole('link', { name: 'Operacje' })).toBeVisible();

    await page.context().storageState({ path: authFile });
});
