// Seed E2E test: the pattern every generated test copies.
// Risk #7 (context/foundation/test-plan.md): a critical browser flow — here the
// operation dialog — stops working although the HTTP and Vitest tests pass.
// The test user (E2E_USERNAME) has demo data: a 2026 budget, accounts and
// expense categories (demo:seed-household).
import { test, expect } from '@playwright/test';

test('expense added through the operation dialog shows on the list, survives a reload and deletes without JS errors', async ({
    page,
}) => {
    const pageErrors: Error[] = [];
    page.on('pageerror', (error) => pageErrors.push(error));

    const description = `E2E wydatek ${Date.now()}`;
    await page.goto('/operations');

    // Setup + action: add an expense through the dialog.
    await page.getByRole('button', { name: 'Dodaj operację' }).click();
    await page.getByRole('menuitem', { name: 'Wydatek' }).click();

    const dialog = page.getByRole('dialog', { name: 'Dodaj wydatek' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('textbox', { name: 'Kwota' }).fill('12,34');
    await dialog.getByRole('combobox', { name: 'Kategoria wydatkowa' }).click();
    await page.getByRole('option', { name: 'Żywność' }).click();
    await dialog.getByRole('textbox', { name: 'Opis' }).fill(description);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toBeHidden();

    // Assertion: the row is on the list and still there after a reload
    // (saved on the server, not only in client state). The row has no role,
    // so it is the innermost element holding both the description and its
    // "Więcej akcji" button.
    const row = page
        .locator('div')
        .filter({ has: page.getByText(description, { exact: true }) })
        .filter({ has: page.getByRole('button', { name: 'Więcej akcji' }) })
        .last();
    await expect(row).toContainText('−12,34 zł');

    await page.reload();
    await expect(row).toContainText('−12,34 zł');

    // Cleanup — asserted, so a cleanup that fails turns the run red.
    await row.getByRole('button', { name: 'Więcej akcji' }).click();
    await page.getByRole('menuitem', { name: 'Usuń' }).click();
    await page
        .getByRole('dialog', { name: 'Usunąć wydatek?' })
        .getByRole('button', { name: 'Usuń' })
        .click();
    await expect(page.getByText(description, { exact: true })).toBeHidden();

    expect(pageErrors).toEqual([]);
});
