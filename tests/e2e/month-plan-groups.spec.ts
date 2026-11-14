// E2E test: Category Groups in the month plan (S-25, ADR-0079).
// Risk it protects (risk #7 of the test plan): a collapsed group hides an
// overrun, or cannot be opened again, while the HTTP and Vitest tests pass.
// The chain only the running app shows: a group made in „Kategorie”, a
// Category put into it, a booked expense, the group row in the month plan with
// the Actual sum and „ponad plan”, the collapse that hides the Category row
// but keeps the signal on the group row, and the expand that brings it back.
// The test user has the demo household (demo:seed-household). The test creates
// its own group and Category (plan 0, so any expense is over plan) and removes
// them at the end: the operation is deleted, the Category archived and the
// group deleted.
import { test, expect, type Page } from '@playwright/test';
import {
    expectSelectedBudgetYear,
    selectBudgetYear,
} from './support/budget-year';
import { assignCategoryToGroup } from './support/categories';

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
// and an expense dated today lands in the budget of the current year.
async function openCurrentMonthPlan(page: Page) {
    await page.goto('/month-plan');
    const year = new Date().getFullYear();
    await selectBudgetYear(page, year);
    await expectSelectedBudgetYear(page, year);
    await page.goto('/month-plan');
}

function groupBlock(page: Page, name: string) {
    return page
        .locator('[data-kind="group"]')
        .filter({ has: page.getByRole('heading', { name, exact: true }) });
}

function categoryRow(page: Page, name: string) {
    return page
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
}

function planRow(page: Page, name: string) {
    return page
        .locator('.month-plan-row')
        .filter({ has: page.getByText(name, { exact: true }) });
}

test('a collapsed month plan group keeps the over-plan signal and opens again', async ({
    page,
}) => {
    // The test walks Categories, Operations and the plan through the UI, so it
    // needs more than the default 30 s on a slow local server.
    test.setTimeout(120_000);
    const stamp = Date.now();
    const group = `E2E grupa planu ${stamp}`;
    const category = `E2E kategoria grupy ${stamp}`;
    const description = `E2E wydatek grupy ${stamp}`;
    let expenseBooked = false;
    let categoryCreated = false;
    let groupCreated = false;

    try {
        // Setup: an expense group with an own running Category (plan 0) in it.
        await page.goto('/categories');
        await page
            .locator('[data-section="expense"]')
            .getByRole('button', { name: 'Dodaj grupę' })
            .click();
        const groupDialog = page.getByRole('dialog');
        await groupDialog.getByLabel('Nazwa grupy').fill(group);
        await groupDialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(groupBlock(page, group)).toBeVisible();
        groupCreated = true;

        await page
            .getByRole('button', { name: 'Dodaj kategorię: Wydatki' })
            .click();
        const categoryDialog = page.getByRole('dialog');
        await categoryDialog.getByLabel('Nazwa').fill(category);
        await categoryDialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(categoryDialog).toHaveCount(0);
        categoryCreated = true;

        await assignCategoryToGroup(
            page,
            categoryRow(page, category),
            category,
            group,
        );
        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText(category);

        // A 100,00 expense on a Category planned at 0 is over plan.
        await addExpense(page, category, '100,00', description);
        expenseBooked = true;

        await openCurrentMonthPlan(page);
        const groupRow = page
            .locator('[data-group-row]')
            .filter({ has: page.getByText(group, { exact: true }) });
        await expect(groupRow).toBeVisible();
        await expect(groupRow.locator('[data-group-actual]')).toContainText(
            '100,00',
        );
        await expect(groupRow.locator('[data-group-over-plan]')).toHaveText(
            'ponad plan',
        );
        await expect(planRow(page, category)).toBeVisible();
        // A row in a group is indented under the group row.
        await expect(planRow(page, category)).toHaveClass(
            /month-plan-row-nested/,
        );

        // Collapsed: the Category row goes, the signal stays on the group row.
        await groupRow
            .getByRole('button', { name: `Zwiń grupę ${group}` })
            .click();
        await expect(planRow(page, category)).toBeHidden();
        await expect(groupRow.locator('[data-group-over-plan]')).toBeVisible();
        await expect(groupRow.locator('[data-group-actual]')).toContainText(
            '100,00',
        );

        // Expanded: the Category row is back.
        await groupRow
            .getByRole('button', { name: `Rozwiń grupę ${group}` })
            .click();
        await expect(planRow(page, category)).toBeVisible();
    } finally {
        if (expenseBooked) {
            await deleteExpense(page, description);
        }

        await page.goto('/categories');

        if (categoryCreated) {
            // The expense joined the Category to a Budget in execution, so the
            // list no longer allows deleting it; archiving takes it off the lists.
            await categoryRow(page, category)
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
        }

        if (groupCreated) {
            // A success toast can sit over the menu button of the group.
            await page.mouse.move(0, 0);
            await expect(page.locator('[data-sonner-toast]')).toHaveCount(0);

            await groupBlock(page, group)
                .getByRole('button', { name: `Więcej działań grupy ${group}` })
                .click();
            await page.getByRole('menuitem', { name: 'Usuń grupę' }).click();
            await page
                .getByRole('dialog')
                .getByRole('button', { name: 'Usuń grupę' })
                .click();
            await expect(groupBlock(page, group)).toHaveCount(0);
        }
    }
});
