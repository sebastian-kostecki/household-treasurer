// E2E test: an irregular expense Category and its „zebrane” (S-26, US-02).
// Risk it protects: the chain from marking a Category „nieregularna” with an
// opening collected amount, through a bill booked on the Operations page, to
// the month plan row breaks somewhere only the running app shows: the dialog
// does not send the amount, the row does not say „Nieregularna” and „zebrane”,
// a bill covered by the collected amount still shows as over plan, or the
// excess over the collected amount does not reach the deviation column.
// The test user has the demo household (demo:seed-household): a budget for the
// current year with accounts. The test creates its own Category (plan 0, so the
// monthly rate is 0 and the collected amount only falls with the bills) and
// deletes its operations and archives the Category at the end.
import { test, expect, type Page } from '@playwright/test';

async function addExpense(
    page: Page,
    category: string,
    amount: string,
    description: string,
) {
    await page.goto('/operations');
    await page.getByRole('button', { name: 'Dodaj operację' }).click();
    await page.getByRole('menuitem', { name: 'Wydatek' }).click();

    const dialog = page.getByRole('dialog', { name: 'Dodaj wydatek' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('textbox', { name: 'Kwota' }).fill(amount);
    await dialog.getByRole('combobox', { name: 'Kategoria wydatkowa' }).click();
    await page.getByRole('option', { name: category }).click();
    await dialog.getByRole('textbox', { name: 'Opis' }).fill(description);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toBeHidden();
}

async function deleteExpense(page: Page, description: string) {
    await page.goto('/operations');
    const row = page
        .locator('div')
        .filter({ has: page.getByText(description, { exact: true }) })
        .filter({ has: page.getByRole('button', { name: 'Więcej akcji' }) })
        .last();
    await row.getByRole('button', { name: 'Więcej akcji' }).click();
    await page.getByRole('menuitem', { name: 'Usuń' }).click();
    await page
        .getByRole('dialog', { name: 'Usunąć wydatek?' })
        .getByRole('button', { name: 'Usuń' })
        .click();
    await expect(page.getByText(description, { exact: true })).toBeHidden();
}

// The month plan opens the selected budget year (the oldest one by default),
// and a bill dated today lands in the budget of the current year.
async function openCurrentMonthPlan(page: Page) {
    await page.goto('/month-plan');
    const year = page
        .locator('[data-sidebar="sidebar"]')
        .getByRole('button', { name: String(new Date().getFullYear()) });
    await year.click();
    await expect(year).toHaveAttribute('aria-pressed', 'true');
    await page.goto('/month-plan');
}

function planRow(page: Page, name: string) {
    return page
        .locator('.month-plan-row')
        .filter({ has: page.getByText(name, { exact: true }) });
}

test('an irregular Category: a bill covered by the collected amount, then one over it', async ({
    page,
}) => {
    const stamp = Date.now();
    const category = `E2E nieregularna ${stamp}`;
    const firstBill = `E2E rachunek A ${stamp}`;
    const secondBill = `E2E rachunek B ${stamp}`;
    const created: string[] = [];

    // Setup: an expense Category „nieregularna” with 500,00 collected at the start.
    await page.goto('/categories');
    await page
        .getByRole('button', { name: 'Dodaj kategorię: Wydatki' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa').fill(category);
    await dialog
        .getByRole('button', { name: 'Nieregularna', exact: true })
        .click();
    await dialog.getByLabel('Zebrane startowe').fill('500,00');
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toHaveCount(0);

    try {
        // A bill of 400,00 is covered by the collected amount: 100,00 is left.
        await addExpense(page, category, '400,00', firstBill);
        created.push(firstBill);

        await openCurrentMonthPlan(page);
        const row = planRow(page, category);
        await expect(row).toContainText('Nieregularna');
        await expect(row.locator('[data-collected]')).toContainText(
            'zebrane 100,00',
        );
        await expect(row).not.toContainText('ponad plan');

        // A bill of 300,00 more is 200,00 over the collected amount.
        await addExpense(page, category, '300,00', secondBill);
        created.push(secondBill);

        await page.goto('/month-plan');
        await expect(row.locator('[data-collected]')).toContainText(
            'zebrane 0,00',
        );
        await expect(row).toContainText('ponad plan');
        await expect(row.locator('.month-plan-cell-deviation')).toContainText(
            '200,00',
        );
    } finally {
        for (const description of created) {
            await deleteExpense(page, description);
        }

        // The first bill joined the Category to a Budget in execution, so the
        // list no longer allows deleting it; archiving takes it off the lists.
        await page.goto('/categories');
        await page
            .locator('[data-category-row]')
            .filter({ has: page.getByText(category, { exact: true }) })
            .getByRole('button', {
                name: `Więcej działań kategorii ${category}`,
            })
            .click();
        await page.getByRole('menuitem', { name: 'Archiwizuj' }).click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Archiwizuj' })
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(
            page
                .locator('[data-section="expense"]')
                .getByText(category, { exact: true }),
        ).toHaveCount(0);
    }
});
