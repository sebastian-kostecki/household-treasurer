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

// The newest year whose Budget is not a draft (ADR-0098: the „szkic” pill,
// `.sidebar-year-state-draft`, sits inside the row); the menu is closed again.
// Needs a page with the sidebar.
export async function newestExecutionBudgetYear(page: Page): Promise<number> {
    await openYearMenu(page);
    const items = page.getByRole('menuitemradio');
    const count = await items.count();
    const years: number[] = [];

    for (let index = 0; index < count; index++) {
        const item = items.nth(index);

        if ((await item.locator('.sidebar-year-state-draft').count()) > 0) {
            continue;
        }

        const year = Number((await item.textContent())?.match(/\d{4}/)?.[0]);

        if (Number.isInteger(year)) {
            years.push(year);
        }
    }

    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toHaveCount(0);

    if (years.length === 0) {
        throw new Error('The year menu has no Budget outside the draft phase.');
    }

    return Math.max(...years);
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
