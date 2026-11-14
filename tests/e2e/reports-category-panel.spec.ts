// E2E test: the Category Panel of the „Raporty” Year view (S-35, ADR-0097, ADR-0100).
// Risk it protects: a click on a Category name in the Year Ranking does not
// select it (no ?category in the address, no panel, no aria-current), the
// button to Operations disagrees with the panel amount, the narrow layout has
// no sheet or the sheet does not clear ?category on close, the year switch
// loses ?category or ?ranking, and a refresh with ?category shows another
// panel (or, in a wide window, flashes a sheet).
// The test user has the demo household (demo:seed-household) with the
// previous year (demo:seed-previous-year); the tests read the Rankings they
// find and need no operations of their own. The Wayfinder query key order is
// not fixed, so the address is checked by its params.
import { test, expect, type Locator, type Page } from '@playwright/test';
import { selectBudgetYear } from './support/budget-year';
import { assignCategoryToGroup } from './support/categories';

async function expectReportsQuery(
    page: Page,
    expected: Record<string, string | RegExp>,
): Promise<void> {
    await expect
        .poll(() => {
            const url = new URL(page.url());
            const params = Object.fromEntries(url.searchParams);
            const matches = Object.entries(expected).every(([key, value]) =>
                typeof value === 'string'
                    ? params[key] === value
                    : value.test(params[key] ?? ''),
            );

            return (
                url.pathname === '/reports' &&
                matches &&
                Object.keys(params).length === Object.keys(expected).length
            );
        })
        .toBe(true);
}

function expenseNames(page: Page): Locator {
    return page.locator('[data-ranking-list="expense"] [data-report-select]');
}

function rowOf(name: Locator): Locator {
    return name.locator('xpath=ancestor::*[@data-report-row][1]');
}

function panelAside(page: Page): Locator {
    return page.getByRole('complementary', { name: 'Panel kategorii' });
}

function groupBlock(page: Page, name: string): Locator {
    return page
        .locator('[data-kind="group"]')
        .filter({ has: page.getByRole('heading', { name, exact: true }) });
}

async function addExpense(page: Page, description: string): Promise<void> {
    await page.goto('/operations');
    await page.getByRole('button', { name: 'Dodaj operację' }).click();
    await page.getByRole('menuitem', { name: 'Wydatek' }).click();

    const dialog = page.getByRole('dialog', { name: 'Dodaj wydatek' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('textbox', { name: 'Kwota' }).fill('12,34');
    await dialog.getByRole('combobox', { name: 'Kategoria wydatkowa' }).click();
    await page.getByRole('option', { name: 'Rozrywka' }).click();
    await dialog.getByRole('textbox', { name: 'Opis' }).fill(description);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toBeHidden();
}

async function deleteExpense(page: Page, description: string): Promise<void> {
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

/** The numeric value of an amount text such as „−1 234,56 zł”, in grosze. */
function amountToMinorUnits(text: string): number {
    const normalised = text
        .replace(/[\s ]/g, '')
        .replace('−', '-')
        .replace(/[^0-9,+-]/g, '')
        .replace(',', '.');

    return Math.round(Number(normalised) * 100);
}

test.describe('wide window: the panel stands beside the Rankings', () => {
    test('(a) a click on a name selects the Category: ?category, the panel and aria-current', async ({
        page,
    }) => {
        await page.goto('/reports');

        const names = expenseNames(page);
        await expect(names.first()).toBeVisible();
        expect(await names.count()).toBeGreaterThan(1);

        // The default is the first position of the Wydatki Ranking.
        await expect(names.first()).toHaveAttribute('aria-current', 'true');
        await expect(panelAside(page)).toBeVisible();

        const second = names.nth(1);
        const secondRow = rowOf(second);
        const secondName = (await second.textContent())?.trim() ?? '';
        const secondAmount =
            (
                await secondRow.locator('.report-cell-amount').textContent()
            )?.trim() ?? '';
        expect(secondName).not.toBe('');

        // A marker on the window survives a visit of the client, not a page load.
        await page.evaluate(() => {
            (window as unknown as { __noReload?: boolean }).__noReload = true;
        });

        await second.click();

        await expectReportsQuery(page, { category: /^\d+$/ });
        await expect(second).toHaveAttribute('aria-current', 'true');
        await expect(names.first()).not.toHaveAttribute('aria-current', 'true');
        await expect(second).toHaveAttribute(
            'aria-controls',
            'report-category-panel',
        );
        await expect(panelAside(page)).toContainText(secondName);
        await expect(
            panelAside(page).locator('[data-panel-amount]'),
        ).toContainText(secondAmount);
        // A click is a visit of the client: the page was not reloaded.
        expect(
            await page.evaluate(
                () =>
                    (window as unknown as { __noReload?: boolean }).__noReload,
            ),
        ).toBe(true);
        await expect(page.getByRole('dialog')).toHaveCount(0);
    });

    test('(b) „Operacje: <kategoria>, <rok>” opens Operations with the amount of the panel', async ({
        page,
    }) => {
        await page.goto('/reports');

        const names = expenseNames(page);
        await expect(names.first()).toBeVisible();
        const name = (await names.first().textContent())?.trim() ?? '';
        const year = (
            await page
                .getByRole('heading', { level: 1, name: /^Raport roku \d{4}$/ })
                .textContent()
        )?.match(/\d{4}/)?.[0];
        const panelAmount =
            (
                await panelAside(page)
                    .locator('[data-panel-amount]')
                    .textContent()
            )?.trim() ?? '';

        await panelAside(page)
            .getByRole('link', { name: `Operacje: ${name}, ${year}` })
            .click();

        await expect(page).toHaveURL(/\/operations\?/);
        expect(new URL(page.url()).searchParams.get('category_id')).toMatch(
            /^\d+$/,
        );

        // The day rows carry their sums; with no further page they add up to the panel.
        const daySums = page.locator('.operations-cell-amount.amount-sum');
        await expect(daySums.first()).toBeVisible();

        // The list has no total across pages: say so instead of passing quietly.
        test.skip(
            (await page
                .getByRole('navigation', { name: /paginacja/i })
                .count()) > 0,
            'The Operations of the first Category span several pages; the list has no total to compare.',
        );

        const texts = await daySums.allTextContents();
        const total = texts.reduce(
            (sum, text) => sum + amountToMinorUnits(text),
            0,
        );

        expect(Math.abs(total)).toBe(Math.abs(amountToMinorUnits(panelAmount)));
    });

    test('(e) a refresh with ?category shows the same panel and never a sheet', async ({
        page,
    }) => {
        await page.goto('/reports');

        const names = expenseNames(page);
        await expect(names.first()).toBeVisible();
        const second = names.nth(1);
        const secondName = (await second.textContent())?.trim() ?? '';

        await second.click();
        await expectReportsQuery(page, { category: /^\d+$/ });

        await page.reload();

        await expect(expenseNames(page).first()).toBeVisible();
        await expect(panelAside(page)).toContainText(secondName);
        await expect(
            expenseNames(page).filter({ hasText: secondName }),
        ).toHaveAttribute('aria-current', 'true');

        // Wait past the first measure of the layout: no sheet ever appears.
        await page.waitForTimeout(500);
        await expect(page.getByRole('dialog')).toHaveCount(0);

        // An unknown category falls back to the default, silently.
        await page.goto('/reports?category=999999');
        await expect(expenseNames(page).first()).toHaveAttribute(
            'aria-current',
            'true',
        );
        await expect(page.getByRole('dialog')).toHaveCount(0);
    });

    test('(d) the year switch keeps ?category and ?ranking and opens the group of the Category', async ({
        page,
    }) => {
        test.slow();

        const stamp = Date.now();
        const group = `Grupa E2E panel ${stamp}`;
        const description = `E2E panel wydatek ${stamp}`;

        await page.goto('/categories');
        await page.getByRole('button', { name: 'Dodaj grupę' }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Nazwa grupy').fill(group);
        await dialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(groupBlock(page, group)).toBeVisible();

        try {
            await assignCategoryToGroup(
                page,
                page.locator('[data-category-row]').filter({
                    has: page.getByText('Rozrywka', { exact: true }),
                }),
                'Rozrywka',
                group,
            );
            await addExpense(page, description);

            let originalYear: number | null = null;

            try {
                await page.goto('/reports?ranking=groups');
                const heading = page.getByRole('heading', {
                    level: 1,
                    name: /^Raport roku \d{4}$/,
                });
                await expect(heading).toBeVisible();
                const year = Number(
                    (await heading.textContent())?.match(/\d{4}/)?.[0],
                );
                originalYear = year;

                const groupRow = page
                    .locator(
                        '[data-ranking-list="expense"] [data-report-row="group"]',
                    )
                    .filter({ hasText: group });
                await groupRow.locator('button[aria-expanded]').click();
                const name = page
                    .locator(
                        '[data-ranking-list="expense"] [data-report-select]',
                    )
                    .filter({ hasText: 'Rozrywka' });
                await name.click();
                await expectReportsQuery(page, {
                    ranking: 'groups',
                    category: /^\d+$/,
                });
                const category = new URL(page.url()).searchParams.get(
                    'category',
                );
                await expect(panelAside(page)).toContainText('Rozrywka');

                // The demo previous year may have no row for the Category: then the
                // server falls back to the default panel and there is nothing to keep.
                const previousCell = rowOf(name).locator(
                    '.report-cell-previous',
                );
                const previous =
                    (await previousCell.textContent())?.replace(
                        /[^0-9—]/g,
                        '',
                    ) ?? '—';
                test.skip(
                    previous.includes('—') || previous === '',
                    'The previous year of the demo household has no Rozrywka row.',
                );

                await selectBudgetYear(page, year - 1);
                await expect(
                    page.getByRole('heading', {
                        level: 1,
                        name: `Raport roku ${year - 1}`,
                    }),
                ).toBeVisible();

                await expectReportsQuery(page, {
                    ranking: 'groups',
                    category: category ?? '',
                });
                await expect(panelAside(page)).toContainText('Rozrywka');
                await expect(
                    page
                        .locator(
                            '[data-ranking-list="expense"] [data-report-select]',
                        )
                        .filter({ hasText: 'Rozrywka' }),
                ).toHaveAttribute('aria-current', 'true');
                // The group of the Category starts expanded after the switch.
                await expect(
                    page
                        .locator(
                            '[data-ranking-list="expense"] [data-report-row="group"]',
                        )
                        .filter({ hasText: group })
                        .locator('button[aria-expanded]'),
                ).toHaveAttribute('aria-expanded', 'true');
            } finally {
                // The selected year is kept in the session: leave the one found.
                if (originalYear !== null) {
                    await page.goto('/reports');
                    await selectBudgetYear(page, originalYear);
                }
                await deleteExpense(page, description);
            }
        } finally {
            await page.goto('/categories');
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
    });

    test('(f) the Lata tab is in the address, survives a refresh and a year change, and shows full years in a past year', async ({
        page,
    }) => {
        test.slow();

        await page.goto('/reports');
        const names = expenseNames(page);
        await expect(names.first()).toBeVisible();
        const previousCell = rowOf(names.first()).locator(
            '.report-cell-previous',
        );
        const previous =
            (await previousCell.textContent())?.replace(/[^0-9—]/g, '') ?? '—';
        test.skip(
            previous.includes('—') || previous === '',
            'The previous year of the demo household has no row for the first Category.',
        );

        const heading = page.getByRole('heading', {
            level: 1,
            name: /^Raport roku \d{4}$/,
        });
        const year = Number((await heading.textContent())?.match(/\d{4}/)?.[0]);
        const lata = panelAside(page).locator('.segmented-control-item', {
            hasText: 'Lata',
        });

        try {
            // A marker on the window survives a visit of the client, not a page load.
            await page.evaluate(() => {
                (window as unknown as { __noReload?: boolean }).__noReload =
                    true;
            });

            await lata.click();
            await expectReportsQuery(page, { panel: 'years' });
            await expect(lata).toHaveAttribute('data-state', 'on');
            expect(
                await page.evaluate(
                    () =>
                        (window as unknown as { __noReload?: boolean })
                            .__noReload,
                ),
            ).toBe(true);
            await expect(
                panelAside(page).locator('[data-report-year-bars] [data-year]'),
            ).not.toHaveCount(0);
            await expect(
                panelAside(page).locator('[data-panel-row="years-range"]'),
            ).toContainText('Każdy rok do');

            await page.reload();
            await expect(panelAside(page)).toBeVisible();
            await expect(lata).toHaveAttribute('data-state', 'on');

            // A selection keeps the tab.
            await names.first().click();
            await expectReportsQuery(page, {
                category: /^\d+$/,
                panel: 'years',
            });
            const category = new URL(page.url()).searchParams.get('category');

            await selectBudgetYear(page, year - 1);
            await expect(
                page.getByRole('heading', {
                    level: 1,
                    name: `Raport roku ${year - 1}`,
                }),
            ).toBeVisible();
            await expectReportsQuery(page, {
                category: category ?? '',
                panel: 'years',
            });
            await expect(lata).toHaveAttribute('data-state', 'on');
            await expect(
                panelAside(page).locator('[data-panel-row="years-range"]'),
            ).toContainText('Pełne lata');
        } finally {
            // The selected year is kept in the session: leave the one found.
            await page.goto('/reports');
            await selectBudgetYear(page, year);
        }
    });
});

test.describe('phone width: the panel is a sheet from the bottom', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('(c) a tap opens the sheet, closing it takes ?category off the address', async ({
        page,
    }) => {
        await page.goto('/reports');

        const names = expenseNames(page);
        await expect(names.first()).toBeVisible();

        // Without a selection no row is highlighted and no sheet is open, and
        // no button points at a panel that is not in the page.
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(names.first()).not.toHaveAttribute('aria-current', 'true');
        await expect(names.first()).not.toHaveAttribute('aria-controls');
        await expect(panelAside(page)).toHaveCount(0);

        // The second name: closing must not fall back to the default (the first) on the way out.
        const second = names.nth(1);
        const name = (await second.textContent())?.trim() ?? '';
        await second.click();

        const sheet = page.getByRole('dialog');
        await expect(sheet).toBeVisible();
        await expect(sheet).toContainText(name);
        await expect(second).toHaveAttribute(
            'aria-controls',
            'report-category-panel',
        );
        await expectReportsQuery(page, { category: /^\d+$/ });

        // One close button, with a Polish name: no built-in „Close” of the sheet.
        await expect(sheet.getByRole('button', { name: 'Close' })).toHaveCount(
            0,
        );
        await expect(
            sheet.getByRole('button', { name: /zamknij/i }),
        ).toHaveCount(1);

        await sheet
            .getByRole('button', { name: 'Zamknij panel kategorii' })
            .click();

        // While the sheet slides out it still shows the Category that was open.
        const closing = page.locator('[data-slot="sheet-content"]');
        if ((await closing.count()) > 0) {
            expect((await closing.textContent()) ?? '').toContain(name);
        }

        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expectReportsQuery(page, {});
        await expect(names.first()).not.toHaveAttribute('aria-current', 'true');
        // Focus goes back to the name that opened the sheet.
        await expect(second).toBeFocused();
    });

    test('(c) an address with ?category opens the sheet at once', async ({
        page,
    }) => {
        await page.goto('/reports');
        await expenseNames(page).first().click();
        await expectReportsQuery(page, { category: /^\d+$/ });

        await page.reload();

        const sheet = page.getByRole('dialog');
        await expect(sheet).toBeVisible();

        // Opened by the address, not by a tap: closing still returns focus to the name.
        await sheet
            .getByRole('button', { name: 'Zamknij panel kategorii' })
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(expenseNames(page).first()).toBeFocused();
    });
});

test.describe('phone width: a link from the Lata table opens the sheet on Lata', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('(g) the name link opens the sheet with the Lata tab pressed', async ({
        page,
    }) => {
        await page.goto('/reports?view=years');

        const link = page
            .locator(
                '[data-years-table="expense"] [data-report-years-link]:visible',
            )
            .first();
        await expect(link).toBeVisible();
        await link.click();

        await expectReportsQuery(page, {
            category: /^\d+$/,
            panel: 'years',
        });
        const sheet = page.getByRole('dialog');
        await expect(sheet).toBeVisible();
        await expect(
            sheet.locator('.segmented-control-item', { hasText: 'Lata' }),
        ).toHaveAttribute('data-state', 'on');
        await expect(
            sheet.locator('[data-report-year-bars] [data-year]'),
        ).not.toHaveCount(0);
    });
});
