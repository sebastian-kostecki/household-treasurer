// E2E test: the „Kategorie” view (S-18, S-19).
// Risk it protects: the list of Categories is not reachable from the menu, a
// Category shows without the years of its Budget Lines, the link to Operations
// loses the Category id (and with it the history of all years), or a write
// from the list (create with a description, edit, archive, restore, delete)
// does not work in the browser. The test user has the demo household
// (demo:seed-household): a budget for the current year with the Category
// "Rozrywka"; the write flow creates its own Category and deletes it at the end.
import { test, expect, type Page } from '@playwright/test';

const currentYear = String(new Date().getFullYear());

function categoryRow(page: Page, name: string) {
    return page
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
}

test('Kategorie opens from the menu and lists Rozrywka with the years of its lines', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await page
        .locator('[data-sidebar="sidebar"]')
        .getByRole('link', { name: 'Kategorie', exact: true })
        .click();

    await expect(page).toHaveURL(/\/categories$/);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Kategorie' }),
    ).toBeVisible();
    await expect(categoryRow(page, 'Rozrywka')).toContainText(currentYear);
});

test('the link Operacje → leads to Operations filtered by the Category id over all years', async ({
    page,
}) => {
    await page.goto('/categories');

    await categoryRow(page, 'Rozrywka')
        .getByRole('link', { name: /^Operacje kategorii Rozrywka/ })
        .click();

    await expect(page).toHaveURL(/\/operations\?/);
    const query = new URL(page.url()).searchParams;
    expect(query.get('category_id')).toMatch(/^\d+$/);
    expect(query.get('date_from')).toBeNull();
    expect(query.get('date_to')).toBeNull();
    expect(query.get('year')).toBeNull();
});

test('a Category is created with a description, edited, archived, restored and deleted from the UI', async ({
    page,
}) => {
    const name = `E2E kategoria ${Date.now()}`;
    const description = 'Opis dodany w teście przeglądarkowym';
    const editedDescription = 'Opis po edycji';

    await page.goto('/categories');

    // Create in the Wydatki panel, with a description.
    await page
        .getByRole('button', { name: 'Dodaj kategorię: Wydatki' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa').fill(name);
    await dialog.getByLabel('Opis').fill(description);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toHaveCount(0);

    const expensePanel = page.locator('[data-section="expense"]');
    const row = expensePanel
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
    await expect(row).toContainText(description);

    // Edit the description.
    await row.getByRole('button', { name: 'Edytuj' }).click();
    await dialog.getByLabel('Opis').fill(editedDescription);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toContainText(editedDescription);
    await expect(row).not.toContainText(description);

    // Archive: the row leaves the panel and lands in the collapsed archive.
    await row.getByRole('button', { name: 'Archiwizuj' }).click();
    await dialog.getByRole('button', { name: 'Archiwizuj' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toHaveCount(0);

    const toggle = page.getByRole('button', { name: /^Archiwum \(\d+\)$/ });
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    const archivedRow = page
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
    await expect(archivedRow).toContainText(
        /Zarchiwizowana \d{2}\.\d{2}\.\d{4}/,
    );

    // Restore: back in the Wydatki panel.
    await archivedRow.getByRole('button', { name: 'Przywróć' }).click();
    await dialog.getByRole('button', { name: 'Przywróć' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toContainText(editedDescription);

    // Delete the fresh Category (no operations, so it can be deleted).
    await row.getByRole('button', { name: 'Usuń' }).click();
    await dialog.getByRole('button', { name: 'Usuń' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toHaveCount(0);
});
