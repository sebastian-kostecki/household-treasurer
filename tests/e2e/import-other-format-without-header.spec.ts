// Regression E2E test: import step 1, format "Inny", file without a header row.
// Risk it protects: what the server receives from the DOM differs from what the
// form shows. The header checkbox was bound with dead radix names, so unchecking
// it did not change the page state and the file picker flipped the format back
// to mBank for a .csv file. Vitest and HTTP tests cannot see either.
// The test only previews: nothing is written to the database.
import { test, expect } from '@playwright/test';

test('format Inny stays after choosing a csv file, the header checkbox drives the column labels and the preview keeps the first line', async ({
    page,
}) => {
    const pageErrors: Error[] = [];
    page.on('pageerror', (error) => pageErrors.push(error));

    const stamp = Date.now();
    const year = new Date().getFullYear();
    const first = `E2E import pierwszy ${stamp}`;
    const second = `E2E import drugi ${stamp}`;
    const csv = `${year}-03-15;${first};-12,34\n${year}-03-16;${second};-5,00\n`;

    await page.goto('/operations/import');

    const format = page.getByLabel('Format importu');
    await format.selectOption({ label: 'Inny' });

    await page.getByLabel('Plik importu (CSV lub XLSX)').setInputFiles({
        name: 'wyciag.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(csv, 'utf8'),
    });

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
});
