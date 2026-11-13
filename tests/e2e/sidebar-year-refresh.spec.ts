// Regression E2E test: the sidebar follows the budget year after every visit.
// Risk it protects: the persistent layout reads `shell` once in setup, so after
// creating or switching a budget year the page shows the new year while the
// sidebar keeps the old one selected (and lacks the new year) until F5.
// The test creates a second budget year through the dialog and deletes it at
// the end, also when an assertion fails.
import { test, expect, type Page } from '@playwright/test';
import {
    budgetYearCount,
    expectBudgetYearInMenu,
    expectSelectedBudgetYear,
    nextBudgetYear,
    openCreateBudgetDialog,
    selectBudgetYear,
} from './support/budget-year';

async function deleteCurrentBudget(page: Page): Promise<void> {
    await page.goto('/budget');
    await page.getByRole('button', { name: 'Akcje budżetu' }).click();
    await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
    await page
        .getByRole('dialog', { name: 'Usunąć budżet?' })
        .getByRole('button', { name: 'Usuń budżet' })
        .click();
}

test('sidebar selects the created year, the chosen year and the year picked from /accounts without a reload', async ({
    page,
}) => {
    await page.goto('/budget');

    const initialYearCount = await budgetYearCount(page);
    const newYear = await nextBudgetYear(page);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        // Scenario 1: creating a year selects it and offers the next year to add.
        await openCreateBudgetDialog(page);
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await expectSelectedBudgetYear(page, newYear);
        await expectBudgetYearInMenu(page, previousYear, false);
        expect(await nextBudgetYear(page)).toBe(newYear + 1);

        // Scenario 2: choosing the previous year in the sidebar selects it.
        await selectBudgetYear(page, previousYear);
        await expectSelectedBudgetYear(page, previousYear);
        await expectBudgetYearInMenu(page, newYear, false);

        // Scenario 3: choosing a year on /accounts selects it in the sidebar.
        await page.goto('/accounts');
        await selectBudgetYear(page, newYear);
        await expect(page).toHaveURL(/\/budget$/);
        await expectSelectedBudgetYear(page, newYear);
    } finally {
        if (created) {
            await page.goto('/budget');
            await selectBudgetYear(page, newYear);
            await deleteCurrentBudget(page);
            await expect
                .poll(() => budgetYearCount(page))
                .toBe(initialYearCount);
        }
    }
});
