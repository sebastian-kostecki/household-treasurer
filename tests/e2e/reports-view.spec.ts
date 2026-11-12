// E2E test: the states of the „Raporty” view (change reports-view, ADR-0083).
// Risk it protects: a Reports state without a report (a draft Budget, no
// Budget at all) loses the title row, so the view looks like another page, and
// the 12-month window, reachable from the period switch, has no way back.
// The states are read from /dev/ui/reports: the kitchen sink renders the real
// page (Reports.vue) with the fixture props, and the E2E database has neither a
// draft Budget nor a user without a Budget.
import { test, expect, type Locator, type Page } from '@playwright/test';

function scenario(page: Page, key: string): Locator {
    return page.locator(`[data-scenario="${key}"]`);
}

test.describe('Raporty: stany bez raportu leżą pod wierszem tytułu', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dev/ui/reports');
    });

    test('szkic budżetu: h1, przełącznik z czterema segmentami i kartka pod nimi', async ({
        page,
    }) => {
        const draft = scenario(page, 'draft');
        const heading = draft.getByRole('heading', {
            level: 1,
            name: 'Raport roku 2027',
        });
        const card = draft.getByRole('heading', {
            name: 'Budżet w przygotowaniu',
        });

        await expect(heading).toBeVisible();
        await expect(draft.locator('.segmented-control-item')).toHaveCount(4);
        await expect(card).toBeVisible();

        const headingBox = await heading.boundingBox();
        const cardBox = await card.boundingBox();
        expect(headingBox!.y).toBeLessThan(cardBox!.y);
    });

    test('szkic budżetu z oknem 12 miesięcy: h1, przełącznik i tabela Wydatki', async ({
        page,
    }) => {
        const draftWindow = scenario(page, 'draft-last-12');

        await expect(
            draftWindow.getByRole('heading', {
                level: 1,
                name: 'Raport: ostatnie 12 miesięcy',
            }),
        ).toBeVisible();
        await expect(
            draftWindow.locator('.segmented-control-item'),
        ).toHaveCount(4);
        await expect(
            draftWindow.getByRole('heading', { name: 'Wydatki', exact: true }),
        ).toBeVisible();
    });

    test('brak budżetu: h1 „Raporty” i kartka, bez przełącznika okresu', async ({
        page,
    }) => {
        const noBudget = scenario(page, 'no-budget');

        await expect(
            noBudget.getByRole('heading', { level: 1, name: 'Raporty' }),
        ).toBeVisible();
        await expect(
            noBudget.getByRole('heading', { name: 'Brak budżetu' }),
        ).toBeVisible();
        await expect(noBudget.locator('.segmented-control')).toHaveCount(0);
    });
});

// The chart is a lazy fragment: until it arrives the table must already stand
// where it will stay, so the page does not jump when the chart appears.
test.describe('Raporty: miejsce na wykres, gdy fragment jest w drodze', () => {
    async function holdFragment(
        page: Page,
        pattern: string,
    ): Promise<() => void> {
        let release: () => void = () => {};
        const released = new Promise<void>((resolve) => {
            release = resolve;
        });

        await page.route(pattern, async (route) => {
            await released;
            await route.continue();
        });

        return release;
    }

    test('słupkowy: tabela Wydatki nie przeskakuje po doładowaniu wykresu', async ({
        page,
    }) => {
        const release = await holdFragment(
            page,
            '**/build/assets/ReportBarChart-*.js',
        );

        await page.goto('/reports');

        const heading = page
            .getByRole('heading', { name: 'Wydatki', exact: true })
            .first();
        await expect(heading).toBeVisible();
        const before = (await heading.boundingBox())!.y;

        release();
        await expect(page.getByTestId('report-chart-expense')).toBeVisible();
        const after = (await heading.boundingBox())!.y;

        expect(Math.abs(after - before)).toBeLessThanOrEqual(1);
    });

    test('Trend: tabela miar nie przeskakuje po doładowaniu wykresu', async ({
        page,
    }) => {
        const release = await holdFragment(
            page,
            '**/build/assets/ReportTrendChart-*.js',
        );

        await page.goto('/reports?view=trend');

        const table = page.locator(
            '[data-trend-table="summary"] .report-trend-scroll',
        );
        await expect(table).toBeVisible();
        const before = (await table.boundingBox())!.y;

        release();
        await expect(page.getByTestId('report-trend-chart')).toBeVisible();
        const after = (await table.boundingBox())!.y;

        expect(Math.abs(after - before)).toBeLessThanOrEqual(1);
    });
});
