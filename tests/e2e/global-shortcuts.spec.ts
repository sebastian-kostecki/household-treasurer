// E2E test: global keyboard shortcuts (1-n views, N add operation, / search).
// Risk it protects: a shortcut fires while the user types or while a dialog is
// open (a digit in the search field switches the view), or an intent set
// before the Operations page loads is lost.
import { test, expect, type Page } from '@playwright/test';

// The shortcut listener is attached when the layout mounts, which can happen
// after `goto` resolves; a key pressed earlier is lost, so repeat the press
// until the expected navigation happens.
async function pressUntilUrl(page: Page, key: string, url: RegExp) {
    await expect(async () => {
        await page.keyboard.press(key);
        await expect(page).toHaveURL(url, { timeout: 1000 });
    }).toPass({ timeout: 10000 });
}

test('a digit switches the view', async ({ page }) => {
    await page.goto('/dashboard');

    await pressUntilUrl(page, '2', /\/budget$/);
});

test('a digit typed in the Operations search field stays in the field', async ({
    page,
}) => {
    await page.goto('/operations');
    const search = page.locator('#operations-search');

    await search.focus();
    await page.keyboard.press('1');

    await expect(page).toHaveURL(/\/operations$/);
    await expect(search).toHaveValue('1');
});

test('N opens the add operation menu on Operations after the visit', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await pressUntilUrl(page, 'n', /\/operations$/);
    await expect(page.getByRole('menu')).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Wydatek' })).toBeVisible();
});

test('N on Operations opens the same menu without leaving the page', async ({
    page,
}) => {
    await page.goto('/operations');

    await page.keyboard.press('n');

    await expect(page).toHaveURL(/\/operations$/);
    await expect(page.getByRole('menu')).toBeVisible();
});

test('/ on the Dashboard focuses an empty search field on Operations', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await pressUntilUrl(page, '/', /\/operations$/);
    const search = page.locator('#operations-search');
    await expect(search).toBeFocused();
    await expect(search).toHaveValue('');
});

test('a digit does nothing while the expense dialog is open', async ({
    page,
}) => {
    await page.goto('/operations');
    await page.getByRole('button', { name: 'Dodaj operację' }).click();
    await page.getByRole('menuitem', { name: 'Wydatek' }).click();
    await expect(
        page.getByRole('dialog', { name: 'Dodaj wydatek' }),
    ).toBeVisible();

    await page.keyboard.press('2');

    await expect(page).toHaveURL(/\/operations$/);
});
