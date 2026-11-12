import { expect, type Locator, type Page } from '@playwright/test';

async function openRowMenu(row: Locator, categoryName: string) {
    await row
        .getByRole('button', {
            name: `Więcej działań kategorii ${categoryName}`,
        })
        .click();
}

// „Zmień grupę…” in the row menu: pick the group in the dialog and save.
export async function assignCategoryToGroup(
    page: Page,
    row: Locator,
    categoryName: string,
    group: string,
) {
    await openRowMenu(row, categoryName);
    await page.getByRole('menuitem', { name: 'Zmień grupę…' }).click();

    const dialog = page.getByRole('dialog');
    await dialog.getByRole('radio', { name: group, exact: true }).click();
    await dialog.getByRole('button', { name: 'Zapisz' }).click();

    await expect(dialog).toHaveCount(0);
}

// „Operacje” in the row menu leads to Operations filtered by the Category.
export async function openCategoryOperations(
    page: Page,
    row: Locator,
    categoryName: string,
) {
    await openRowMenu(row, categoryName);
    await page.getByRole('menuitem', { name: 'Operacje', exact: true }).click();
}
