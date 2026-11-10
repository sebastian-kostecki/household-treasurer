// E2E test: the read-only „Kategorie” view (S-18).
// Risk it protects: the list of Categories is not reachable from the menu, a
// Category shows without the years of its Budget Lines, the archive section
// does not open, or the link to Operations loses the Category id (and with it
// the history of all years). The test user has the demo household
// (demo:seed-household): a budget for the current year with the Category
// "Rozrywka" and no archived Categories, so the archive test archives one
// through the app's own endpoint and restores it at the end.
import { test, expect, type Page } from '@playwright/test';

const currentYear = String(new Date().getFullYear());

function categoryRow(page: Page, name: string) {
    return page
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
}

async function categoryIdOf(page: Page, name: string): Promise<string> {
    const href = await page
        .getByRole('link', {
            name: `Operacje kategorii ${name}, wszystkie lata`,
        })
        .getAttribute('href');
    const id = new URL(href ?? '', 'http://localhost').searchParams.get(
        'category_id',
    );

    expect(id).toMatch(/^\d+$/);

    return id as string;
}

// The CSRF token of the session travels in the XSRF-TOKEN cookie.
async function post(page: Page, path: string) {
    const cookies = await page.context().cookies();
    const token = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');

    expect(token).toBeDefined();

    // The endpoints answer with a redirect to the Budget page; the test only
    // needs the write, so it does not follow it.
    const response = await page.request.post(path, {
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(token?.value ?? '') },
        maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
}

test('Kategorie opens from the menu and lists Rozrywka with the years of its lines', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await page
        .locator('[data-sidebar="sidebar"]')
        .getByRole('link', { name: 'Kategorie', exact: true })
        .click();

    await expect(page).toHaveURL(/\/categories$/);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Kategorie' }),
    ).toBeVisible();
    await expect(categoryRow(page, 'Rozrywka')).toContainText(currentYear);
});

test('the link Operacje → leads to Operations filtered by the Category id over all years', async ({
    page,
}) => {
    await page.goto('/categories');

    await categoryRow(page, 'Rozrywka')
        .getByRole('link', { name: /^Operacje kategorii Rozrywka/ })
        .click();

    await expect(page).toHaveURL(/\/operations\?/);
    const query = new URL(page.url()).searchParams;
    expect(query.get('category_id')).toMatch(/^\d+$/);
    expect(query.get('date_from')).toBeNull();
    expect(query.get('date_to')).toBeNull();
    expect(query.get('year')).toBeNull();
});

test('an archived Category is in the collapsed archive with its date and the section opens', async ({
    page,
}) => {
    await page.goto('/categories');
    const id = await categoryIdOf(page, 'Rozrywka');

    await post(page, `/categories/${id}/archive`);

    try {
        await page.goto('/categories');

        await expect(categoryRow(page, 'Rozrywka')).toHaveCount(0);
        const toggle = page.getByRole('button', { name: /^Archiwum \(\d+\)$/ });
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await toggle.click();

        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(categoryRow(page, 'Rozrywka')).toContainText(
            /Zarchiwizowana \d{2}\.\d{2}\.\d{4}/,
        );
    } finally {
        // Cleanup — asserted by `post`, so a failed restore turns the run red.
        await post(page, `/categories/${id}/restore`);
    }

    await page.goto('/categories');
    await expect(categoryRow(page, 'Rozrywka')).toContainText(currentYear);
});
