// Approval E2E test: import step 2 -> "Zatwierdź import" writes the operations.
// Risk #7 (context/foundation/test-plan.md): the import is approved in the
// browser, the rows reach the database and the Operations list although the
// HTTP and Vitest tests pass. Two expense rows go through the whole flow
// (format "Inny", preview, explicit category, approval) and are deleted again.
// The amounts are unusual and the rows share one account, so no row is taken
// for the second leg of an own transfer (pair detection looks at +-3 days).
import { test, expect, type Page } from '@playwright/test';
import { newestExecutionBudgetYear } from './support/budget-year';
import { withCleanup } from './support/cleanup';

// The row has no role, so it is the innermost element holding both the
// description and its "Więcej akcji" button.
function operationRow(page: Page, description: string) {
    return page
        .locator('div')
        .filter({ has: page.getByText(description, { exact: true }) })
        .filter({ has: page.getByRole('button', { name: 'Więcej akcji' }) })
        .last();
}

test(
    'import approved in step 2 writes both rows to the Operations list, survives a reload and deletes without JS errors',
    { tag: '@gate' },
    async ({ page }) => {
        const pageErrors: Error[] = [];
        page.on('pageerror', (error) => pageErrors.push(error));

        // Base 36 keeps the stamp from being 13-19 pure digits, which the import
        // strips as a card number (Luhn).
        const stamp = Date.now().toString(36);
        const first = `E2E zatwierdzenie A ${stamp}`;
        const second = `E2E zatwierdzenie B ${stamp}`;

        await withCleanup(
            async () => {
                await page.goto('/operations/import');

                // Both rows are dated in a Budget that is not a draft.
                const year = await newestExecutionBudgetYear(page);
                const csv = `Data;Opis;Kwota\n${year}-01-01;${first};-12,37\n${year}-01-01;${second};-3,41\n`;

                await page
                    .getByLabel('Format importu')
                    .selectOption({ label: 'Inny' });
                await page
                    .getByLabel('Plik importu (CSV lub XLSX)')
                    .setInputFiles({
                        name: 'wyciag.csv',
                        mimeType: 'text/csv',
                        buffer: Buffer.from(csv, 'utf8'),
                    });

                await page.getByRole('combobox', { name: 'Konto' }).click();
                await page
                    .getByRole('option', { name: 'eKonto', exact: true })
                    .click();

                await page
                    .getByRole('button', { name: 'Pokaż podgląd' })
                    .click();

                // Step 2: select every row and choose the category by hand, even
                // where the app suggests one.
                for (const description of [first, second]) {
                    const label = `01.01.${year}, ${description}`;

                    await page
                        .getByRole('checkbox', {
                            name: `Zaznacz wiersz: ${label}`,
                        })
                        .click();
                    await page
                        .getByRole('combobox', {
                            name: `Kategoria / konto: ${label}`,
                        })
                        .click();
                    await page
                        .getByRole('option', { name: 'Żywność', exact: true })
                        .click();
                }

                await page
                    .getByRole('button', { name: 'Zatwierdź import' })
                    .click();

                // Assertion: back on the list with the toast, both rows saved on the
                // server (still there after a reload).
                await expect(page).toHaveURL(/\/operations(\?|$)/);
                await expect(
                    page.getByText('2 operacje zaimportowane.'),
                ).toBeVisible();

                await page.goto(`/operations?q=${stamp}`);
                await expect(operationRow(page, first)).toContainText('−12,37');
                await expect(operationRow(page, second)).toContainText('−3,41');

                await page.reload();
                await expect(operationRow(page, first)).toContainText('−12,37');
                await expect(operationRow(page, second)).toContainText('−3,41');
            },
            async () => {
                // Cleanup one row at a time, also after a failure midway: only rows
                // that exist are deleted, and each deletion is asserted. The page
                // heading renders together with the list, so count() below does
                // not run before the rows are there.
                await page.goto(`/operations?q=${stamp}`);
                await expect(
                    page.getByRole('heading', { level: 1, name: 'Operacje' }),
                ).toBeVisible();

                for (const description of [first, second]) {
                    const text = page.getByText(description, { exact: true });

                    if ((await text.count()) === 0) {
                        continue;
                    }

                    await operationRow(page, description)
                        .getByRole('button', { name: 'Więcej akcji' })
                        .click();
                    await page.getByRole('menuitem', { name: 'Usuń' }).click();
                    await page
                        .getByRole('dialog', { name: 'Usunąć wydatek?' })
                        .getByRole('button', { name: 'Usuń' })
                        .click();
                    await expect(text).toBeHidden();
                }
            },
        );

        expect(pageErrors).toEqual([]);
    },
);
