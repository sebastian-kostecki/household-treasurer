// E2E test: the „Raporty” view (S-28, FR-014, FR-015).
// Risk it protects: the report is not reachable (menu or shortcut 8), the
// Ranking lists do not render in the browser, the period switch does not reach
// the URL (?month=N), the Ranking mode (?ranking=groups) is lost on a period
// change, a collapsed group still shows its Categories, or changing the budget
// year in the sidebar throws the user from „Raporty” to the Budget.
// The test user has the demo household (demo:seed-household). The tests that
// need rows create their own operations and a group and delete them at the end
// through the UI, also when an assertion fails.
// The year-over-year test and the „Lata” test need the mock of the previous
// year in the E2E database (`demo:seed-previous-year`, see README.pl.md, section
// „Uruchomienie”): the UI creates only a draft of the next year, so the tests
// do not create a Budget in progress themselves.
import { test, expect, type Locator, type Page } from '@playwright/test';
import {
    budgetYearCount,
    expectSelectedBudgetYear,
    nextBudgetYear,
    openCreateBudgetDialog,
    selectBudgetYear,
} from './support/budget-year';
import { assignCategoryToGroup } from './support/categories';

// Wayfinder orders the query keys itself, so the URL is checked by its params.
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

function ranking(page: Page, type: 'income' | 'expense'): Locator {
    return page.locator(`[data-ranking-list="${type}"]`);
}

function groupBlock(page: Page, name: string): Locator {
    return page
        .locator('[data-kind="group"]')
        .filter({ has: page.getByRole('heading', { name, exact: true }) });
}

async function addOperation(
    page: Page,
    kind: 'Wydatek' | 'Dochód',
    description: string,
): Promise<void> {
    await page.goto('/operations');
    await page.getByRole('button', { name: 'Dodaj operację' }).click();
    await page.getByRole('menuitem', { name: kind }).click();

    const dialog = page.getByRole('dialog', {
        name: kind === 'Wydatek' ? 'Dodaj wydatek' : 'Dodaj dochód',
    });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('textbox', { name: 'Kwota' }).fill('12,34');
    await dialog
        .getByRole('combobox', {
            name:
                kind === 'Wydatek'
                    ? 'Kategoria wydatkowa'
                    : 'Kategoria dochodowa',
        })
        .click();
    if (kind === 'Wydatek') {
        await page.getByRole('option', { name: 'Rozrywka' }).click();
    } else {
        await page.getByRole('option').first().click();
    }
    await dialog.getByRole('textbox', { name: 'Opis' }).fill(description);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toBeHidden();
}

async function deleteOperation(
    page: Page,
    kind: 'wydatek' | 'dochód',
    description: string,
): Promise<void> {
    await page.goto('/operations');
    const row = page
        .locator('div')
        .filter({ has: page.getByText(description, { exact: true }) })
        .filter({ has: page.getByRole('button', { name: 'Więcej akcji' }) })
        .last();
    await row.getByRole('button', { name: 'Więcej akcji' }).click();
    await page.getByRole('menuitem', { name: 'Usuń' }).click();
    await page
        .getByRole('dialog', { name: `Usunąć ${kind}?` })
        .getByRole('button', { name: 'Usuń' })
        .click();
    await expect(page.getByText(description, { exact: true })).toBeHidden();
}

test('shortcut 8 opens Raporty with the measures and both Ranking lists with share bars', async ({
    page,
}) => {
    // Two operations are added and two deleted through the UI around the
    // Reports check: about 20 s alone and over 30 s on a busy machine, so the
    // default limit made the test flaky (it timed out at different steps).
    test.slow();

    const stamp = Date.now();
    const expenseDescription = `E2E raport wydatek ${stamp}`;
    const incomeDescription = `E2E raport dochód ${stamp}`;

    await addOperation(page, 'Wydatek', expenseDescription);
    try {
        await addOperation(page, 'Dochód', incomeDescription);
        try {
            await page.goto('/dashboard');

            // The listener is attached when the layout mounts; repeat the press.
            await expect(async () => {
                await page.keyboard.press('8');
                await expect(page).toHaveURL(/\/reports$/, { timeout: 1000 });
            }).toPass({ timeout: 10000 });

            await expect(
                page.getByRole('heading', { level: 1, name: /^Raport roku/ }),
            ).toBeVisible();
            await expect(page.getByText('Wynik roku')).toBeVisible();
            await expect(
                page.getByRole('heading', {
                    level: 2,
                    name: 'Wydatki od największych',
                }),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', {
                    level: 2,
                    name: 'Przychody od największych',
                }),
            ).toBeVisible();

            // The share bar is aria-hidden, so it is found by its marker.
            await expect(
                ranking(page, 'expense')
                    .locator('[data-report-share-bar]')
                    .first(),
            ).toBeVisible();
            await expect(
                ranking(page, 'income')
                    .locator('[data-report-share-bar]')
                    .first(),
            ).toBeVisible();
            await expect(
                ranking(page, 'expense').getByText('Razem'),
            ).toHaveCount(1);
            await expect(
                ranking(page, 'income').getByText('Razem'),
            ).toHaveCount(1);
            await expect(page.getByText('Razem')).toHaveCount(2);
        } finally {
            await deleteOperation(page, 'dochód', incomeDescription);
        }
    } finally {
        await deleteOperation(page, 'wydatek', expenseDescription);
    }
});

test('Raporty of the current year compare it with the year before and drop the comparison for the oldest year', async ({
    page,
}) => {
    await page.goto('/reports');

    const heading = page.getByRole('heading', {
        level: 1,
        name: /^Raport roku \d{4}$/,
    });
    await expect(heading).toBeVisible();
    const year = Number((await heading.textContent())?.match(/\d{4}/)?.[0]);
    expect(year).toBeGreaterThan(2000);
    const previousYear = year - 1;

    // Column headers of both years and the difference.
    const headerRow = page.locator('.report-header-row').first();
    await expect(headerRow).toContainText(String(year));
    await expect(headerRow).toContainText(String(previousYear));
    await expect(headerRow).toContainText('Różnica');

    // The previous-year line in the measures panel.
    await expect(
        page.getByText(new RegExp(`^${previousYear}:`)).first(),
    ).toBeVisible();

    // The Ranking footer names the compared year.
    await expect(
        page.getByText(`Kwoty w zł · różnica do ${previousYear}`).first(),
    ).toBeVisible();

    // The previous year has no year before it: no comparison.
    await selectBudgetYear(page, previousYear);
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: `Raport roku ${previousYear}`,
        }),
    ).toBeVisible();
    await expect(page.getByText('Różnica', { exact: true })).toHaveCount(0);
    await expect(page.getByText(/^Kwoty w zł · różnica do/)).toHaveCount(0);
});

test('switching to Miesiąc and picking a month puts ?month=N in the URL', async ({
    page,
}) => {
    await page.goto('/reports');

    await page.getByRole('button', { name: 'Miesiąc', exact: true }).click();
    await expect(page).toHaveURL(/\/reports\?month=\d+$/);

    await page.getByRole('button', { name: /^styczeń \d{4}/ }).click();
    await expect(page).toHaveURL(/\/reports\?month=1$/);
    await expect(
        page.getByRole('heading', { level: 1, name: /^Raport: styczeń/ }),
    ).toBeVisible();
});

test('in the Grupy mode a group starts collapsed, expands to its Categories and the mode survives the period switch', async ({
    page,
}) => {
    const group = `Grupa E2E raport ${Date.now()}`;
    const description = `E2E raport grupa ${Date.now()}`;

    await page.goto('/categories');
    await page.getByRole('button', { name: 'Dodaj grupę' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa grupy').fill(group);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(groupBlock(page, group)).toBeVisible();

    try {
        await assignCategoryToGroup(
            page,
            page
                .locator('[data-category-row]')
                .filter({ has: page.getByText('Rozrywka', { exact: true }) }),
            'Rozrywka',
            group,
        );
        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');

        await addOperation(page, 'Wydatek', description);
        try {
            await page.goto('/reports');
            await page
                .getByRole('button', { name: 'Grupy', exact: true })
                .click();
            await expect(page).toHaveURL(/\/reports\?ranking=groups$/);

            const groupRow = ranking(page, 'expense')
                .locator('[data-report-row="group"]')
                .filter({ hasText: group });
            const categoryRow = ranking(page, 'expense')
                .locator('[data-report-row="category"]')
                .filter({ hasText: 'Rozrywka' });
            // The accessible name flips to „Zwiń grupę …” once open, so the
            // button is found by its row, not by its name.
            const toggle = groupRow.locator('button[aria-expanded]');

            await expect(toggle).toHaveAccessibleName(`Rozwiń grupę ${group}`);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(categoryRow).toBeHidden();
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(categoryRow).toBeVisible();
            await toggle.click();
            await expect(categoryRow).toBeHidden();

            // The mode travels through the period switch.
            await page
                .getByRole('button', { name: 'Miesiąc', exact: true })
                .click();
            await expectReportsQuery(page, {
                month: /^\d+$/,
                ranking: 'groups',
            });
            await page
                .getByRole('button', { name: '12 miesięcy', exact: true })
                .click();
            await expectReportsQuery(page, {
                view: 'last-12',
                ranking: 'groups',
            });
            await page
                .getByRole('button', { name: 'Rok', exact: true })
                .click();
            await expect(page).toHaveURL(/\/reports\?ranking=groups$/);
        } finally {
            await deleteOperation(page, 'wydatek', description);
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

test('changing the budget year in the sidebar stays on Raporty', async ({
    page,
}) => {
    await page.goto('/budget');

    const initialYearCount = await budgetYearCount(page);
    const newYear = await nextBudgetYear(page);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        await openCreateBudgetDialog(page);
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await page.goto('/reports');
        await selectBudgetYear(page, previousYear);

        await expect(page).toHaveURL(/\/reports$/);
        await expectSelectedBudgetYear(page, previousYear);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: `Raport roku ${previousYear}`,
            }),
        ).toBeVisible();
    } finally {
        if (created) {
            await page.goto('/budget');
            await selectBudgetYear(page, newYear);
            await page.getByRole('button', { name: 'Akcje budżetu' }).click();
            await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
            await page
                .getByRole('dialog', { name: 'Usunąć budżet?' })
                .getByRole('button', { name: 'Usuń budżet' })
                .click();
            await expect
                .poll(() => budgetYearCount(page))
                .toBe(initialYearCount);
        }
    }
});

test('the Trend view shows the month chart and the measure and category tables', async ({
    page,
}) => {
    await page.goto('/reports');
    await page.getByRole('button', { name: 'Trend', exact: true }).click();

    await expect(page).toHaveURL(/\/reports\?view=trend$/);
    await expect(
        page.getByRole('heading', { level: 1, name: /^Trend \d{4}$/ }),
    ).toBeVisible();

    const summary = page.locator('[data-trend-table="summary"]');
    await expect(
        summary.getByRole('heading', { level: 2, name: 'Miesiące' }),
    ).toBeVisible();
    // The chart is aria-hidden, so it is found by its test id.
    await expect(
        page.getByTestId('report-trend-chart').locator('svg'),
    ).toBeVisible();
    await expect(page.getByTestId('report-trend-chart-legend')).toContainText(
        'Przychody',
    );
    for (const measure of ['Przychody', 'Wydatki', 'Wynik']) {
        await expect(
            summary.getByRole('rowheader', { name: measure, exact: true }),
        ).toBeVisible();
    }

    const expenses = page.locator('[data-trend-table="expense"]');
    // 12 months, the name column and „Razem”.
    await expect(expenses.getByRole('columnheader')).toHaveCount(14);
    await expect(
        expenses.getByRole('columnheader', { name: 'Razem', exact: true }),
    ).toBeVisible();

    await expect(
        page.getByText('Kwoty w zł, zaokrąglone do pełnych złotych').first(),
    ).toBeVisible();
});

test('Trend groups start collapsed and expand to their Categories', async ({
    page,
}) => {
    // The demo household has no expense group, so the test creates its own.
    const group = `Grupa E2E trend ${Date.now()}`;
    const description = `E2E trend grupa ${Date.now()}`;

    await page.goto('/categories');
    await page.getByRole('button', { name: 'Dodaj grupę' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa grupy').fill(group);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(groupBlock(page, group)).toBeVisible();

    try {
        await assignCategoryToGroup(
            page,
            page
                .locator('[data-category-row]')
                .filter({ has: page.getByText('Rozrywka', { exact: true }) }),
            'Rozrywka',
            group,
        );
        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');

        await addOperation(page, 'Wydatek', description);
        try {
            await page.goto('/reports?view=trend');

            const groupRow = page
                .locator('[data-trend-table="expense"]')
                .locator('[data-report-row="group"]')
                .filter({ hasText: group });
            // The accessible name flips to „Zwiń grupę …” once open, so the
            // button is found by its row, not by its name.
            const toggle = groupRow.locator('button[aria-expanded]');
            await expect(toggle).toHaveAccessibleName(`Rozwiń grupę ${group}`);
            const categoryRow = page
                .locator('[data-trend-table="expense"]')
                .locator('[data-report-row="category"]')
                .filter({ hasText: 'Rozrywka' });

            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(categoryRow).toBeHidden();
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(categoryRow).toBeVisible();
        } finally {
            await deleteOperation(page, 'wydatek', description);
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

test('changing the budget year in the sidebar stays on the Trend', async ({
    page,
}) => {
    await page.goto('/budget');

    const initialYearCount = await budgetYearCount(page);
    const newYear = await nextBudgetYear(page);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        await openCreateBudgetDialog(page);
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await page.goto('/reports?view=trend');
        await selectBudgetYear(page, previousYear);

        await expect(page).toHaveURL(/\/reports\?view=trend$/);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: `Trend ${previousYear}`,
            }),
        ).toBeVisible();
    } finally {
        if (created) {
            await page.goto('/budget');
            await selectBudgetYear(page, newYear);
            await page.getByRole('button', { name: 'Akcje budżetu' }).click();
            await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
            await page
                .getByRole('dialog', { name: 'Usunąć budżet?' })
                .getByRole('button', { name: 'Usuń budżet' })
                .click();
            await expect
                .poll(() => budgetYearCount(page))
                .toBe(initialYearCount);
        }
    }
});

/** "listopad 2025 – październik 2026": the 12 closed months before today (UTC). */
function expectedWindowRange(): string {
    const months = [
        'styczeń',
        'luty',
        'marzec',
        'kwiecień',
        'maj',
        'czerwiec',
        'lipiec',
        'sierpień',
        'wrzesień',
        'październik',
        'listopad',
        'grudzień',
    ];
    const now = new Date();
    const through = new Date(
        Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - 1, 1),
    );
    const from = new Date(
        Date.UTC(through.getUTCFullYear(), through.getUTCMonth() - 11, 1),
    );
    const label = (date: Date): string =>
        `${months[date.getUTCMonth()]} ${date.getUTCFullYear()}`;

    return `${label(from)} – ${label(through)}`;
}

test('the 12 months view opens from the switch with the window range and no month bar', async ({
    page,
}) => {
    await page.goto('/reports');
    await page
        .getByRole('button', { name: '12 miesięcy', exact: true })
        .click();

    await expect(page).toHaveURL(/\/reports\?view=last-12$/);
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: 'Raport: ostatnie 12 miesięcy',
        }),
    ).toBeVisible();
    await expect(page.getByTestId('report-window-range')).toHaveText(
        expectedWindowRange(),
    );

    // Depending on the seeded Budgets the window is covered or empty.
    await expect(
        page
            .getByText('Wynik 12 miesięcy')
            .or(page.getByText('Brak budżetu w realizacji w tym okresie')),
    ).toBeVisible();
    await expect(page.getByText('Wynik roku')).toHaveCount(0);
    await expect(page.getByText('Budżet w przygotowaniu')).toHaveCount(0);
});

test('changing the budget year in the sidebar from the 12 months view leads to the year report', async ({
    page,
}) => {
    await page.goto('/budget');

    const initialYearCount = await budgetYearCount(page);
    const newYear = await nextBudgetYear(page);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        await openCreateBudgetDialog(page);
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await page.goto('/reports?view=last-12');
        await selectBudgetYear(page, previousYear);

        await expect(page).toHaveURL(/\/reports$/);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: `Raport roku ${previousYear}`,
            }),
        ).toBeVisible();
    } finally {
        if (created) {
            await page.goto('/budget');
            await selectBudgetYear(page, newYear);
            await page.getByRole('button', { name: 'Akcje budżetu' }).click();
            await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
            await page
                .getByRole('dialog', { name: 'Usunąć budżet?' })
                .getByRole('button', { name: 'Usuń budżet' })
                .click();
            await expect
                .poll(() => budgetYearCount(page))
                .toBe(initialYearCount);
        }
    }
});

test('the Lata view shows a category in two years, switches the tab and range without a request and survives a year change', async ({
    page,
}) => {
    test.slow();

    const description = `E2E lata wydatek ${Date.now()}`;

    await addOperation(page, 'Wydatek', description);
    let created = false;
    let newYear = 0;
    let initialYearCount = 0;

    try {
        await page.goto('/reports');
        // The panel has its own „Lata” tab: pick the one of the period switch.
        await page
            .getByRole('group', { name: 'Okres raportu' })
            .getByRole('button', { name: 'Lata', exact: true })
            .click();

        await expect(page).toHaveURL(/\/reports\?view=years$/);
        const heading = page.getByRole('heading', {
            level: 1,
            name: /^Raport: lata \d{4}–\d{4}$/,
        });
        await expect(heading).toBeVisible();
        const [firstYear, lastYear] = ((await heading.textContent()) ?? '')
            .match(/\d{4}/g)!
            .map(Number);
        expect(lastYear).toBeGreaterThan(firstYear);
        const previousYear = lastYear - 1;

        const expenseTable = page.locator('[data-years-table="expense"]');
        await expect(
            expenseTable.getByRole('columnheader', {
                name: String(previousYear),
                exact: true,
            }),
        ).toHaveCount(1);

        // Rozrywka has an amount in both the current and the previous year.
        let row = expenseTable
            .locator('[data-report-row="category"]')
            .filter({ hasText: 'Rozrywka' });
        if ((await row.count()) === 0 || !(await row.first().isVisible())) {
            const toggles = expenseTable.getByRole('button', {
                name: /^Rozwiń grupę/,
            });
            for (let i = (await toggles.count()) - 1; i >= 0; i--) {
                await toggles.nth(i).click();
            }
        }
        row = expenseTable
            .locator('[data-report-row="category"]')
            .filter({ hasText: 'Rozrywka' })
            .first();
        await expect(row).toBeVisible();
        const columnCount = lastYear - firstYear + 1;
        const cells = row.getByRole('cell');
        await expect(cells.nth(columnCount - 1)).toHaveText(/\d/);
        await expect(cells.nth(columnCount - 2)).toHaveText(/\d/);

        // Tab and range are local: no new request, no change of the URL.
        const requests: string[] = [];
        page.on('request', (request) => {
            if (request.resourceType() !== 'image') {
                requests.push(request.url());
            }
        });

        await page
            .getByRole('button', { name: 'Przychody', exact: true })
            .click();
        const incomeTable = page.locator('[data-years-table="income"]');
        await expect(incomeTable).toBeVisible();
        await expect(expenseTable).toHaveCount(0);
        await expect(
            incomeTable.getByRole('heading', { level: 2 }),
        ).toBeVisible();
        // The tab changes the rows, not only the heading: the expense
        // Category is not among the income rows.
        await expect(incomeTable.getByText('Rozrywka')).toHaveCount(0);

        const incomeSameDay = await incomeTable.innerText();
        await page
            .getByRole('button', { name: 'Pełne lata', exact: true })
            .click();
        await expect(page.getByTestId('report-years-subtitle')).toContainText(
            'Zakończone lata liczone w całości',
        );
        await expect
            .poll(async () => incomeTable.innerText())
            .not.toBe(incomeSameDay);

        await expect(page).toHaveURL(/\/reports\?view=years$/);
        expect(requests).toEqual([]);
        page.removeAllListeners('request');

        // The sidebar year change keeps „Lata”.
        await page.goto('/budget');
        initialYearCount = await budgetYearCount(page);
        newYear = await nextBudgetYear(page);
        expect(newYear).toBeGreaterThan(2000);

        await openCreateBudgetDialog(page);
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await page.goto('/reports?view=years');
        await selectBudgetYear(page, newYear - 1);

        await expect(page).toHaveURL(/\/reports\?view=years$/);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: /^Raport: lata \d{4}–\d{4}$/,
            }),
        ).toBeVisible();
        await expect(
            page.locator('[data-years-table="expense"]'),
        ).toBeVisible();
    } finally {
        try {
            if (created) {
                await page.goto('/budget');
                await selectBudgetYear(page, newYear);
                await page
                    .getByRole('button', { name: 'Akcje budżetu' })
                    .click();
                await page
                    .getByRole('menuitem', { name: 'Usuń budżet' })
                    .click();
                await page
                    .getByRole('dialog', { name: 'Usunąć budżet?' })
                    .getByRole('button', { name: 'Usuń budżet' })
                    .click();
                await expect
                    .poll(() => budgetYearCount(page))
                    .toBe(initialYearCount);
            }
        } finally {
            await deleteOperation(page, 'wydatek', description);
        }
    }
});

test('a category name in the Lata table opens the panel of the Year view on its Lata tab', async ({
    page,
}) => {
    test.slow();

    const description = `E2E lata link ${Date.now()}`;

    await addOperation(page, 'Wydatek', description);

    try {
        await page.goto('/reports?view=years');
        const heading = page.getByRole('heading', {
            level: 1,
            name: /^Raport: lata \d{4}–\d{4}$/,
        });
        await expect(heading).toBeVisible();
        const [firstYear, lastYear] = ((await heading.textContent()) ?? '')
            .match(/\d{4}/g)!
            .map(Number);
        const columnCount = lastYear - firstYear + 1;

        const expenseTable = page.locator('[data-years-table="expense"]');
        // The footer hint names the link.
        await expect(expenseTable).toContainText(
            'Kliknij kategorię, aby zobaczyć jej lata w panelu',
        );

        let row = expenseTable
            .locator('[data-report-row="category"]')
            .filter({ hasText: 'Rozrywka' });
        if ((await row.count()) === 0 || !(await row.first().isVisible())) {
            const toggles = expenseTable.getByRole('button', {
                name: /^Rozwiń grupę/,
            });
            for (let i = (await toggles.count()) - 1; i >= 0; i--) {
                await toggles.nth(i).click();
            }
        }
        row = expenseTable
            .locator('[data-report-row="category"]')
            .filter({ hasText: 'Rozrywka' })
            .first();
        await expect(row).toBeVisible();

        await row.locator('[data-report-years-link]').click();

        await expectReportsQuery(page, {
            category: /^\d+$/,
            panel: 'years',
        });
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: `Raport roku ${lastYear}`,
            }),
        ).toBeVisible();

        const aside = page.getByRole('complementary', {
            name: 'Panel kategorii',
        });
        await expect(aside).toContainText('Rozrywka');
        await expect(
            aside.locator('.segmented-control-item', { hasText: 'Lata' }),
        ).toHaveAttribute('data-state', 'on');
        await expect(
            aside.locator('[data-report-year-bars] [data-year]'),
        ).toHaveCount(columnCount);
    } finally {
        await deleteOperation(page, 'wydatek', description);
    }
});
