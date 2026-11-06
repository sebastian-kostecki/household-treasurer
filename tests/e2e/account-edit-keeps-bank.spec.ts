// Regression E2E test: editing a bank account keeps its bank.
// Risk it protects: the account dialog silently drops the bank. After a cash
// account's edit dialog is opened and cancelled, the bank account's edit
// dialog shows "Inny" instead of the stored bank, and saving writes
// bank = 'other', so the bank is lost without any error.
// The test creates its own uniquely named accounts and deletes them at the end.
import { test, expect, type Locator, type Page } from '@playwright/test';

async function createAccount(
    page: Page,
    name: string,
    type: 'Gotówka' | 'Rachunek bankowy',
    bank?: string,
): Promise<void> {
    await page.getByRole('button', { name: 'Dodaj konto' }).click();
    const dialog = page.getByRole('dialog', { name: 'Nowe konto' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('textbox', { name: 'Nazwa' }).fill(name);
    await dialog.getByRole('button', { name: type, exact: true }).click();
    if (bank) {
        await dialog.getByRole('combobox', { name: 'Bank' }).click();
        await page.getByRole('option', { name: bank }).click();
    }
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toBeHidden();
}

async function deleteAccount(page: Page, row: Locator): Promise<void> {
    await row.getByRole('button', { name: 'Usuń' }).click();
    await page
        .getByRole('dialog', { name: 'Usunąć konto?' })
        .getByRole('button', { name: 'Usuń' })
        .click();
}

test('editing a bank account after cancelling a cash account edit keeps the bank in the dialog and after saving', async ({
    page,
}) => {
    const pageErrors: Error[] = [];
    page.on('pageerror', (error) => pageErrors.push(error));

    const suffix = Date.now();
    const cashName = `E2E gotówka ${suffix}`;
    const bankName = `E2E bank ${suffix}`;
    const rowFor = (name: string): Locator =>
        page
            .locator('.accounts-row')
            .filter({ has: page.getByText(name, { exact: true }) });

    await page.goto('/accounts');

    try {
        // Setup: one cash account and one mBank account, created through the dialog.
        await createAccount(page, cashName, 'Gotówka');
        await createAccount(page, bankName, 'Rachunek bankowy', 'mBank');
        await expect(rowFor(bankName)).toContainText('mBank');

        // Action: open the cash account's edit dialog, cancel, then open the bank one.
        await rowFor(cashName).getByRole('button', { name: 'Edytuj' }).click();
        const dialog = page.getByRole('dialog', { name: 'Edytuj konto' });
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Anuluj' }).click();
        await expect(dialog).toBeHidden();

        // Focus returns to the cash row's "Edytuj" button and opens its tooltip,
        // which covers the row above it; Escape closes the tooltip.
        await page.mouse.move(0, 0);
        await page.keyboard.press('Escape');
        await rowFor(bankName).getByRole('button', { name: 'Edytuj' }).click();
        await expect(dialog).toBeVisible();

        // Assertion 1: the dialog shows the stored bank, not "Inny".
        await expect(
            dialog.getByRole('combobox', { name: 'Bank' }),
        ).toContainText('mBank');

        // Assertion 2: saving without changes keeps the bank after a reload.
        await dialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(dialog).toBeHidden();
        await page.reload();
        await expect(rowFor(bankName)).toContainText('mBank');
    } finally {
        // Cleanup — runs also when an assertion fails, so no rows are left behind.
        await page.goto('/accounts');
        for (const name of [cashName, bankName]) {
            const row = rowFor(name);
            if ((await row.count()) > 0) {
                await deleteAccount(page, row);
                await expect(row).toHaveCount(0);
            }
        }
    }

    expect(pageErrors).toEqual([]);
});
