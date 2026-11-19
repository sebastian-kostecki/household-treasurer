// Regression E2E test: import step 1, format "Inny", file without a header row.
// Risk it protects: what the server receives from the DOM differs from what the
// form shows. The header checkbox was bound with dead radix names, so unchecking
// it did not change the page state and the file picker flipped the format back
// to mBank for a .csv file. Vitest and HTTP tests cannot see either.
// The test only previews: nothing is written to the database.
import { test, expect } from '@playwright/test';
import { newestExecutionBudgetYear } from './support/budget-year';

test(
    'format Inny stays after choosing a csv file, the header checkbox drives the column labels and the preview keeps the first line',
    { tag: '@gate' },
    async ({ page }) => {
        const pageErrors: Error[] = [];
        page.on('pageerror', (error) => pageErrors.push(error));

        // Base 36 keeps the stamp from being 13-19 pure digits, which the import
        // strips as a card number (Luhn).
        const stamp = Date.now().toString(36);
        const first = `E2E import pierwszy ${stamp}`;
        const second = `E2E import drugi ${stamp}`;

        await page.goto('/operations/import');

        // Both rows are dated in a Budget that is not a draft.
        const year = await newestExecutionBudgetYear(page);
        const csv = `${year}-01-01;${first};-12,34\n${year}-01-01;${second};-5,00\n`;

        const format = page.getByLabel('Format importu');
        await format.selectOption({ label: 'Inny' });

        await page.getByLabel('Plik importu (CSV lub XLSX)').setInputFiles({
            name: 'wyciag.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(csv, 'utf8'),
        });

        // The page preselects the first account, which can be a temporary "E2E …"
        // account another spec creates and deletes (one worker runs the specs in
        // turn, but a failed cleanup can leave one behind). Pick a demo account
        // by hand instead.
        await page.getByRole('combobox', { name: 'Konto' }).click();
        await page
            .getByRole('option')
            .filter({ hasNotText: /^E2E / })
            .first()
            .click();

        // The format chosen by hand survives the file with a .csv extension.
        await expect(format.locator('option:checked')).toHaveText('Inny');

        const header = page.getByRole('checkbox', {
            name: 'Pierwszy wiersz zawiera nagłówki kolumn',
        });
        await expect(header).toBeChecked();

        // Unchecking the header relabels the columns "Kolumna 1..3".
        await header.click();
        await expect(header).not.toBeChecked();

        const columns = page.getByRole('group', { name: 'Kolumny' });
        const dateColumn = columns.getByLabel('Data');
        await expect(dateColumn.locator('option')).toHaveText([
            'Kolumna 1',
            'Kolumna 2',
            'Kolumna 3',
        ]);

        // Step 2 shows both lines: the first one was not eaten as a header.
        await page.getByRole('button', { name: 'Pokaż podgląd' }).click();
        await expect(page.getByText(first, { exact: true })).toBeVisible();
        await expect(page.getByText(second, { exact: true })).toBeVisible();

        expect(pageErrors).toEqual([]);
    },
);
