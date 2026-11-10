// E2E test: the „Raporty” view (S-28, FR-014, FR-015).
// Risk it protects: the report is not reachable (menu or shortcut 8), the
// charts or tables do not render in the browser (the chart is loaded lazily),
// the period switch does not reach the URL (?month=N), a collapsed group still
// shows its Categories, or changing the budget year in the sidebar throws the
// user from „Raporty” to the Budget.
// The test user has the demo household (demo:seed-household). The tests that
// need rows create their own operations and a group and delete them at the end
// through the UI, also when an assertion fails.
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
            await expect(yearSegments(page)).toHaveCount(2);
        }
    }
});
