import { expect, type Locator, type Page } from '@playwright/test';

// The sidebar year menu (ADR-0087): a trigger named „Rok budżetu <rok>”, years
// as `menuitemradio` newest first, „Dodaj rok N” under a separator.

export function yearTrigger(page: Page): Locator {
    return page
        .locator('[data-sidebar="sidebar"]')
        .getByRole('button', { name: /^Rok budżetu \d{4}$/ });
}

async function openYearMenu(page: Page): Promise<void> {
    await yearTrigger(page).click();
    await expect(page.getByRole('menu')).toBeVisible();
}

// Number of years with a Budget in the menu; the menu is closed again.
export async function budgetYearCount(page: Page): Promise<number> {
    await openYearMenu(page);
    const count = await page.getByRole('menuitemradio').count();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toHaveCount(0);

    return count;
}

// The year „Dodaj rok” offers; the menu is closed again.
export async function nextBudgetYear(page: Page): Promise<number> {
    await openYearMenu(page);
    const label =
        (await page
            .getByRole('menuitem', { name: /^Dodaj budżet na rok \d{4}$/ })
            .getAttribute('aria-label')) ?? '';
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toHaveCount(0);

    return Number(label.match(/\d{4}/)?.[0]);
}

// „Dodaj rok N” in the menu opens the „Nowy budżet” dialog.
export async function openCreateBudgetDialog(page: Page): Promise<void> {
    await openYearMenu(page);
    await page
        .getByRole('menuitem', { name: /^Dodaj budżet na rok \d{4}$/ })
        .click();
}

// Picks a year in the menu; a year that is already selected only closes it.
export async function selectBudgetYear(
    page: Page,
    year: number,
): Promise<void> {
    await openYearMenu(page);
    await page
        .getByRole('menuitemradio', { name: String(year), exact: true })
        .click();
    await expect(page.getByRole('menu')).toHaveCount(0);
}

// The trigger shows the selected year; the year in the menu is checked.
export async function expectSelectedBudgetYear(
    page: Page,
    year: number,
): Promise<void> {
    await expect(yearTrigger(page)).toHaveAccessibleName(`Rok budżetu ${year}`);
}

export async function expectBudgetYearInMenu(
    page: Page,
    year: number,
    checked: boolean,
): Promise<void> {
    await openYearMenu(page);
    await expect(
        page.getByRole('menuitemradio', { name: String(year), exact: true }),
    ).toHaveAttribute('aria-checked', String(checked));
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toHaveCount(0);
}
