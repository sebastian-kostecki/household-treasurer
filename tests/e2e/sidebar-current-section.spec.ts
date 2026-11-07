// E2E test: the sidebar marks the current section with aria-current="page".
// Risk it protects: Inertia's Link does not add the attribute itself, so the
// active item is only visible by colour, and sub-pages (/operations/import)
// may lose their section highlight for assistive technology.
import { test, expect, type Locator, type Page } from '@playwright/test';

function sidebarLink(page: Page, name: string): Locator {
    return page
        .locator('[data-sidebar="sidebar"]')
        .getByRole('link', { name, exact: true });
}

test('sidebar marks Operacje as the current section on /operations/import', async ({
    page,
}) => {
    await page.goto('/operations/import');

    await expect(sidebarLink(page, 'Operacje')).toHaveAttribute(
        'aria-current',
        'page',
    );
    await expect(sidebarLink(page, 'Dashboard')).not.toHaveAttribute(
        'aria-current',
        'page',
    );
});

test('sidebar marks Konta as the current section on /accounts', async ({
    page,
}) => {
    await page.goto('/accounts');

    await expect(sidebarLink(page, 'Konta')).toHaveAttribute(
        'aria-current',
        'page',
    );
});
