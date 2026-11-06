// E2E test: on a phone the sidebar is reachable through the menu button.
// Risk it protects: without the header bar there is no other entry to the
// navigation sheet below 768 px, so a regression leaves the app without menu.
import { test, expect } from '@playwright/test';

test.use({ viewport: { width: 390, height: 844 } });

test('phone shows no fixed sidebar and opens the menu sheet from the bar', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await expect(page.locator('[data-slot="sidebar"]')).toBeHidden();

    await page.getByRole('button', { name: 'Otwórz menu' }).click();
    await page.getByRole('link', { name: 'Konta', exact: true }).click();

    await expect(page).toHaveURL(/\/accounts$/);
});
