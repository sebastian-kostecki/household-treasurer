// E2E test: the states of the „Raporty” view (change reports-view, ADR-0083).
// Risk it protects: a Reports state without a report (a draft Budget, no
// Budget at all) loses the title row, so the view looks like another page, and
// the 12-month window, reachable from the period switch, has no way back.
// The states are read from /dev/ui/reports: the kitchen sink renders the real
// page (Reports.vue) with the fixture props, and the E2E database has neither a
// draft Budget nor a user without a Budget. The sink answers 404 outside
// APP_ENV=local (the E2E server reads the project's .env), which would show up
// here as a missing heading.
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
    // Holds the requests matching `pattern` until `release()`. `held()` counts
    // them: a test asserts it is above zero, so a renamed chunk (the pattern
    // matching nothing) fails loudly instead of measuring a page whose chart
    // has already loaded.
    async function holdFragment(
        page: Page,
        pattern: string,
    ): Promise<{ release: () => void; held: () => number }> {
        let release: () => void = () => {};
        let heldCount = 0;
        const released = new Promise<void>((resolve) => {
            release = resolve;
        });

        await page.route(pattern, async (route) => {
            heldCount += 1;
            await released;
            await route.continue();
        });

        return { release, held: () => heldCount };
    }

    test('słupkowy: tabela Wydatki nie przeskakuje po doładowaniu wykresu', async ({
        page,
    }) => {
        const { release, held } = await holdFragment(
            page,
            '**/build/assets/ReportBarChart-*.js',
        );

        await page.goto('/reports');

        const heading = page
            .getByRole('heading', { name: 'Wydatki', exact: true })
            .first();
        await expect(heading).toBeVisible();
        // The chart is still in flight: the placeholder stands in its place.
        await expect(
            page.getByTestId('report-chart-placeholder-expense'),
        ).toBeVisible();
        await expect(page.getByTestId('report-chart-expense')).toHaveCount(0);
        expect(held()).toBeGreaterThan(0);
        const before = (await heading.boundingBox())!.y;

        release();
        await expect(page.getByTestId('report-chart-expense')).toBeVisible();
        const after = (await heading.boundingBox())!.y;

        expect(Math.abs(after - before)).toBeLessThanOrEqual(1);
    });

    test('Trend: tabela miar nie przeskakuje po doładowaniu wykresu', async ({
        page,
    }) => {
        const { release, held } = await holdFragment(
            page,
            '**/build/assets/ReportTrendChart-*.js',
        );

        await page.goto('/reports?view=trend');

        const table = page.locator(
            '[data-trend-table="summary"] .report-trend-scroll',
        );
        await expect(table).toBeVisible();
        // The chart is still in flight: the placeholder stands in its place.
        await expect(
            page.getByTestId('report-trend-chart-placeholder'),
        ).toBeVisible();
        await expect(page.getByTestId('report-trend-chart')).toHaveCount(0);
        expect(held()).toBeGreaterThan(0);
        const before = (await table.boundingBox())!.y;

        release();
        await expect(page.getByTestId('report-trend-chart')).toBeVisible();
        const after = (await table.boundingBox())!.y;

        expect(Math.abs(after - before)).toBeLessThanOrEqual(1);
    });
});

// In the 22rem phone frame the card is about 320 px wide and a row holds about
// 288 px of content: the Udział column must not take the name's room, so the
// share goes under the amount and the archived Badge under the name (ADR-0083).
// The Badge test assumes the real fonts (the build serves them), so a failure
// there can come from font loading, not from the CSS.
test.describe('Raporty: wąska karta pokazuje pełne nazwy i Udział pod kwotą', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dev/ui/reports');
    });

    for (const key of ['phone', 'compared-phone']) {
        test(`${key}: nazwy kategorii nie są ucięte`, async ({ page }) => {
            const table = scenario(page, key).locator('.report-table').first();
            const names = table.locator(
                '[data-report-row="category"] .report-cell-name .truncate',
            );

            await expect(names.first()).toBeVisible();
            const count = await names.count();

            expect(count).toBeGreaterThan(0);
            for (let index = 0; index < count; index++) {
                const clipped = await names
                    .nth(index)
                    .evaluate(
                        (element) => element.scrollWidth > element.clientWidth,
                    );

                expect(clipped, `nazwa #${index} w ${key}`).toBe(false);
            }
        });
    }

    test('phone: „udział 66,7%” stoi pod kwotą wiersza Żywność', async ({
        page,
    }) => {
        const row = scenario(page, 'phone')
            .locator('[data-report-row="category"]', { hasText: 'Żywność' })
            .first();
        const share = row.locator('.report-cell-share');
        const amount = row.locator('.report-cell-amount');

        await expect(share).toContainText('udział 66,7%');

        const shareBox = (await share.boundingBox())!;
        const amountBox = (await amount.boundingBox())!;
        expect(shareBox.y).toBeGreaterThan(amountBox.y + amountBox.height - 1);
    });

    test('phone: Badge „zarchiwizowana” schodzi pod nazwę', async ({
        page,
    }) => {
        const row = scenario(page, 'phone')
            .locator('[data-report-row="category"]', {
                hasText: 'Stara prenumerata',
            })
            .first();
        const name = row.locator('.report-cell-name .truncate');
        const badge = row
            .locator('.report-cell-name')
            .getByText('zarchiwizowana');

        await expect(name).toHaveText('Stara prenumerata');
        const nameBox = (await name.boundingBox())!;
        const badgeBox = (await badge.boundingBox())!;
        expect(badgeBox.y).toBeGreaterThanOrEqual(
            nameBox.y + nameBox.height - 1,
        );
    });
});
