// E2E test: „zebrane startowe” in a Budget row (S-27, FR-012, ADR-0076).
// Risk it protects: the chain from marking a Category „nieregularna” with an
// opening collected amount, through creating the next year's Budget in the
// „Nowy budżet” dialog, to the Budget row breaks somewhere only the running
// app shows: the row has no „zebrane startowe” line, shows another amount than
// the one entered, or lacks the „prognoza” word before 1 January of that year.
// The Category has no plan in the current year, so its monthly rate is 0 and
// the result does not depend on the date the test runs. The test deletes the
// Budget it created and archives the Category, also when an assertion fails.
import { test, expect, type Locator, type Page } from '@playwright/test';

function yearSegments(page: Page): Locator {
    return page.locator(
        '[data-sidebar="sidebar"] .sidebar-year-segments button',
    );
}

function selectSegment(page: Page, year: number): Locator {
    return yearSegments(page)
        .filter({ hasText: String(year) })
        .first();
}

async function deleteCurrentBudget(page: Page): Promise<void> {
    await page.goto('/budget');
    await page.getByRole('button', { name: 'Akcje budżetu' }).click();
    await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
    await page
        .getByRole('dialog', { name: 'Usunąć budżet?' })
        .getByRole('button', { name: 'Usuń budżet' })
        .click();
}

test('the next year Budget row shows the collected amount at the start of the year as a forecast', async ({
    page,
}) => {
    const stamp = Date.now();
    const category = `E2E zebrane startowe ${stamp}`;
    let newYear = 0;
    let createdBudget = false;
    let initialSegmentCount = 0;

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
        await page.goto('/budget');
        initialSegmentCount = await yearSegments(page).count();
        const createSegment = yearSegments(page).last();
        const createLabel =
            (await createSegment.getAttribute('aria-label')) ?? '';
        newYear = Number(createLabel.match(/\d{4}/)?.[0]);
        expect(newYear).toBeGreaterThan(2000);

        await createSegment.click();
        const budgetDialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await expect(budgetDialog).toBeVisible();
        await budgetDialog
            .getByRole('button', { name: 'Utwórz budżet' })
            .click();
        await expect(budgetDialog).toBeHidden();
        createdBudget = true;

        await expect(selectSegment(page, newYear)).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        const row = page
            .locator('.budget-category-row')
            .filter({ has: page.getByText(category, { exact: true }) });
        await expect(row.locator('[data-collected-at-year-start]')).toHaveText(
            /zebrane startowe 500,00\s+·\s+prognoza/,
        );
    } finally {
        if (createdBudget) {
            await page.goto('/budget');
            if (
                (await selectSegment(page, newYear).getAttribute(
                    'aria-pressed',
                )) !== 'true'
            ) {
                await selectSegment(page, newYear).click();
            }
            await deleteCurrentBudget(page);
            await expect(yearSegments(page)).toHaveCount(initialSegmentCount);
        }

        await page.goto('/categories');
        await page
            .locator('[data-category-row]')
            .filter({ has: page.getByText(category, { exact: true }) })
            .getByRole('button', { name: 'Archiwizuj' })
            .click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Archiwizuj' })
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
    }
});
