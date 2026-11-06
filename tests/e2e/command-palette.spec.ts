// E2E test: the "Szukaj i przejdź" command palette (Ctrl+K).
// Risk it protects: the palette opens but cannot be driven from the keyboard
// (focus not in the combobox, Enter does nothing, Esc loses the focus), or an
// item leads somewhere other than it promises. The test user has the demo
// household (demo:seed-household): a 2026 budget with categories such as
// "Żywność".
import { test, expect, type Page } from '@playwright/test';

// The shortcut listener is attached when the layout mounts, which can happen
// after `goto` resolves; a key pressed earlier is lost, so repeat the press
// until the palette is there.
async function openPaletteWithShortcut(page: Page) {
    await expect(async () => {
        await page.keyboard.press('Control+k');
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 1000 });
    }).toPass({ timeout: 10000 });
}

test('Ctrl+K opens the palette with the focus in the combobox', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await openPaletteWithShortcut(page);

    await expect(
        page.getByRole('dialog', { name: 'Szukaj i przejdź' }),
    ).toBeVisible();
    await expect(page.getByRole('combobox')).toBeFocused();
});

test('typing a view name and Enter goes to that view', async ({ page }) => {
    await page.goto('/dashboard');
    await openPaletteWithShortcut(page);

    await page.getByRole('combobox').fill('Lok');
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(/\/term-deposits$/);
});

test('a category goes to Operations filtered by the category and the year', async ({
    page,
}) => {
    await page.goto('/dashboard');
    await openPaletteWithShortcut(page);
    await expect(page.getByRole('option', { name: /Żywność/ })).toBeVisible();

    await page.getByRole('combobox').fill('Żywność');
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(/\/operations\?/);
    const query = new URL(page.url()).searchParams;
    expect(query.get('category_name')).toBe('Żywność');
    expect(query.get('category_type')).toBe('expense');
    expect(query.get('date_from')).toMatch(/^\d{4}-01-01$/);
    expect(query.get('date_to')).toMatch(/^\d{4}-12-31$/);
});

test('categories that load after the user has typed are still filtered and shown', async ({
    page,
}) => {
    // Hold the categories response back, so the text is typed before the
    // category rows exist: they mount while a search is already active.
    await page.route('**/command-palette/categories', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 1500));
        await route.continue();
    });
    await page.goto('/dashboard');
    await openPaletteWithShortcut(page);

    await page.getByRole('combobox').fill('Żywność');

    await expect(page.getByRole('option', { name: /Żywność/ })).toBeVisible({
        timeout: 10000,
    });
    await expect(page.getByText('Brak wyników')).toBeHidden();
});

test('"Dodaj operację" opens the type menu on Operations', async ({ page }) => {
    await page.goto('/dashboard');
    await openPaletteWithShortcut(page);

    await page.getByRole('combobox').fill('Dodaj operację');
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(/\/operations$/);
    await expect(page.getByRole('menu')).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Wydatek' })).toBeVisible();
});

test('"Dodaj rok budżetu" opens the new budget dialog for the next year', async ({
    page,
}) => {
    await page.goto('/dashboard');
    const createSegment = page.getByRole('button', {
        name: /Dodaj budżet na rok \d{4}/,
    });
    const nextYear = (await createSegment.textContent())?.match(/\d{4}/)?.[0];
    expect(nextYear).toBeDefined();

    await openPaletteWithShortcut(page);
    await page.getByRole('combobox').fill('Dodaj rok');
    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('spinbutton')).toHaveValue(nextYear!);
    // The palette is gone: one layer, not two.
    await expect(page.getByRole('dialog')).toHaveCount(1);
});

test.describe('on a phone, with the menu sheet closed', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('"Dodaj rok budżetu" still opens the new budget dialog', async ({
        page,
    }) => {
        await page.goto('/dashboard');
        await openPaletteWithShortcut(page);

        await page.getByRole('combobox').fill('Dodaj rok');
        await page.keyboard.press('Enter');

        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await expect(dialog).toBeVisible();
        await expect(dialog.getByRole('spinbutton')).toHaveValue(/^\d{4}$/);
    });
});

test('Esc closes the palette and returns the focus', async ({ page }) => {
    await page.goto('/dashboard');
    const trigger = page.getByRole('button', { name: /Szukaj i przejdź/ });
    await trigger.focus();
    await trigger.press('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();

    await page.keyboard.press('Escape');

    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(trigger).toBeFocused();
});

test('the sidebar button opens the palette', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('button', { name: /Szukaj i przejdź/ }).click();

    await expect(
        page.getByRole('dialog', { name: 'Szukaj i przejdź' }),
    ).toBeVisible();
});
