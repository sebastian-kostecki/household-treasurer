// E2E test: the „Raporty” view (S-28, FR-014, FR-015).
// Risk it protects: the report is not reachable (menu or shortcut 8), the
// charts or tables do not render in the browser (the chart is loaded lazily),
// the period switch does not reach the URL (?month=N), a collapsed group still
// shows its Categories, or changing the budget year in the sidebar throws the
// user from „Raporty” to the Budget.
// The test user has the demo household (demo:seed-household). The tests that
// need rows create their own operations and a group and delete them at the end
// through the UI, also when an assertion fails.
// The year-over-year test needs the mock of the previous year in the E2E
// database (`demo:seed-previous-year`, see README, section „Uruchomienie”).
import { test, expect, type Locator, type Page } from '@playwright/test';

function chart(page: Page, type: 'income' | 'expense'): Locator {
    // The chart is aria-hidden, so it is found by its test id.
    return page.getByTestId(`report-chart-${type}`);
}

function yearSegments(page: Page): Locator {
    return page.locator(
        '[data-sidebar="sidebar"] .sidebar-year-segments button',
    );
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

test('shortcut 8 opens Raporty with the measures, both charts and both tables', async ({
    page,
}) => {
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
                page.getByRole('heading', { level: 2, name: 'Wydatki' }),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', { level: 2, name: 'Przychody' }),
            ).toBeVisible();

            await expect(chart(page, 'expense')).toBeVisible();
            await expect(chart(page, 'income')).toBeVisible();
            await expect(
                page.getByTestId('report-chart-expense').locator('svg'),
            ).toBeVisible();
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

    // The chart legend names both years.
    const legend = page.getByTestId('report-chart-legend-expense');
    await expect(legend).toContainText(`Rok ${year}`);
    await expect(legend).toContainText(`Rok ${previousYear}`);

    // The previous year has no year before it: no comparison.
    await yearSegments(page)
        .filter({ hasText: String(previousYear) })
        .first()
        .click();
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: `Raport roku ${previousYear}`,
        }),
    ).toBeVisible();
    await expect(page.getByText('Różnica', { exact: true })).toHaveCount(0);
    await expect(page.getByTestId('report-chart-legend-expense')).toHaveCount(
        0,
    );
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

test('collapsing a group hides its Categories', async ({ page }) => {
    const group = 'Grupa E2E raport';
    const description = `E2E raport grupa ${Date.now()}`;

    await page.goto('/categories');
    await page
        .locator('[data-section="expense"]')
        .getByRole('button', { name: 'Dodaj grupę' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa grupy').fill(group);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(groupBlock(page, group)).toBeVisible();

    try {
        await page
            .locator('[data-category-row]')
            .filter({ has: page.getByText('Rozrywka', { exact: true }) })
            .getByRole('button', { name: 'Grupa kategorii Rozrywka' })
            .click();
        await page.getByRole('menuitem', { name: group }).click();
        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');

        await addOperation(page, 'Wydatek', description);
        try {
            await page.goto('/reports');

            const groupRow = page
                .locator('[data-report-row="group"]')
                .filter({ hasText: group });
            const categoryRow = page
                .locator('[data-report-row="category"]')
                .filter({ hasText: 'Rozrywka' });
            const toggle = groupRow.getByRole('button', {
                name: `Zwiń grupę ${group}`,
            });

            await expect(categoryRow).toBeVisible();
            await toggle.click();
            await expect(categoryRow).toBeHidden();
            await expect(
                groupRow.getByRole('button', {
                    name: `Rozwiń grupę ${group}`,
                }),
            ).toHaveAttribute('aria-expanded', 'false');
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

    const initialSegmentCount = await yearSegments(page).count();
    const createSegment = yearSegments(page).last();
    const createLabel = (await createSegment.getAttribute('aria-label')) ?? '';
    const newYear = Number(createLabel.match(/\d{4}/)?.[0]);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        await createSegment.click();
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await page.goto('/reports');
        await yearSegments(page)
            .filter({ hasText: String(previousYear) })
            .first()
            .click();

        await expect(page).toHaveURL(/\/reports$/);
        await expect(
            yearSegments(page)
                .filter({ hasText: String(previousYear) })
                .first(),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: `Raport roku ${previousYear}`,
            }),
        ).toBeVisible();
    } finally {
        if (created) {
            await page.goto('/budget');
            const newSegment = yearSegments(page)
                .filter({ hasText: String(newYear) })
                .first();
            if ((await newSegment.getAttribute('aria-pressed')) !== 'true') {
                await newSegment.click();
            }
            await page.getByRole('button', { name: 'Akcje budżetu' }).click();
            await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
            await page
                .getByRole('dialog', { name: 'Usunąć budżet?' })
                .getByRole('button', { name: 'Usuń budżet' })
                .click();
            await expect(yearSegments(page)).toHaveCount(initialSegmentCount);
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
    const group = 'Grupa E2E trend';
    const description = `E2E trend grupa ${Date.now()}`;

    await page.goto('/categories');
    await page
        .locator('[data-section="expense"]')
        .getByRole('button', { name: 'Dodaj grupę' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa grupy').fill(group);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(groupBlock(page, group)).toBeVisible();

    try {
        await page
            .locator('[data-category-row]')
            .filter({ has: page.getByText('Rozrywka', { exact: true }) })
            .getByRole('button', { name: 'Grupa kategorii Rozrywka' })
            .click();
        await page.getByRole('menuitem', { name: group }).click();
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

    const initialSegmentCount = await yearSegments(page).count();
    const createSegment = yearSegments(page).last();
    const createLabel = (await createSegment.getAttribute('aria-label')) ?? '';
    const newYear = Number(createLabel.match(/\d{4}/)?.[0]);
    expect(newYear).toBeGreaterThan(2000);
    const previousYear = newYear - 1;
    let created = false;

    try {
        await createSegment.click();
        const dialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await dialog.getByRole('button', { name: 'Utwórz budżet' }).click();
        await expect(dialog).toBeHidden();
        created = true;

        await page.goto('/reports?view=trend');
        await yearSegments(page)
            .filter({ hasText: String(previousYear) })
            .first()
            .click();

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
            const newSegment = yearSegments(page)
                .filter({ hasText: String(newYear) })
                .first();
            if ((await newSegment.getAttribute('aria-pressed')) !== 'true') {
                await newSegment.click();
            }
            await page.getByRole('button', { name: 'Akcje budżetu' }).click();
            await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
            await page
                .getByRole('dialog', { name: 'Usunąć budżet?' })
                .getByRole('button', { name: 'Usuń budżet' })
                .click();
            await expect(yearSegments(page)).toHaveCount(initialSegmentCount);
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
    await page.goto('/reports');
    const heading = page.getByRole('heading', {
        level: 1,
        name: /^Raport roku \d{4}$/,
    });
    await expect(heading).toBeVisible();
    const year = Number((await heading.textContent())?.match(/\d{4}/)?.[0]);
    const previousYear = year - 1;

    await page.goto('/reports?view=last-12');
    await yearSegments(page)
        .filter({ hasText: String(previousYear) })
        .first()
        .click();

    await expect(page).toHaveURL(/\/reports$/);
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: `Raport roku ${previousYear}`,
        }),
    ).toBeVisible();
});
