// Regression E2E test: the sidebar follows the budget year after every visit.
// Risk it protects: the persistent layout reads `shell` once in setup, so after
// creating or switching a budget year the page shows the new year while the
// sidebar keeps the old one selected (and lacks the new segment) until F5.
// The test creates a second budget year through the dialog and deletes it at
// the end, also when an assertion fails.
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

test('sidebar selects the created year, the chosen year and the year picked from /accounts without a reload', async ({
    page,
}) => {
    await page.goto('/budget');

    const initialSegmentCount = await yearSegments(page).count();
    const createSegment = yearSegments(page).last();
    const createLabel = (await createSegment.getAttribute('aria-label')) ?? '';
    const newYear = Number(createLabel.match(/\d{4}/)?.[0]);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        // Scenario 1: creating a year selects it and adds the next "+" segment.
        await createSegment.click();
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await expect(selectSegment(page, newYear)).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        await expect(selectSegment(page, previousYear)).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        await expect(
            page.getByRole('button', {
                name: `Dodaj budżet na rok ${newYear + 1}`,
            }),
        ).toBeVisible();

        // Scenario 2: choosing the previous year in the sidebar selects it.
        await selectSegment(page, previousYear).click();
        await expect(selectSegment(page, previousYear)).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        await expect(selectSegment(page, newYear)).toHaveAttribute(
            'aria-pressed',
            'false',
        );

        // Scenario 3: choosing a year on /accounts selects it in the sidebar.
        await page.goto('/accounts');
        await selectSegment(page, newYear).click();
        await expect(page).toHaveURL(/\/budget$/);
        await expect(selectSegment(page, newYear)).toHaveAttribute(
            'aria-pressed',
            'true',
        );
    } finally {
        if (created) {
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
    }
});
