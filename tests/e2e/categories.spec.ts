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
async function post(page: Page, path: string, form?: Record<string, string>) {
    const cookies = await page.context().cookies();
    const token = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');

    expect(token).toBeDefined();

    // The endpoints answer with a redirect to the Budget page; the test only
    // needs the write, so it does not follow it.
    const response = await page.request.post(path, {
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(token?.value ?? '') },
        form,
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

// Category Groups (S-22, ADR-0066). Risk it protects: a group cannot be created,
// "Grupa…" does not move a Category under the group, dragging a Category onto a
// group does not save the membership, or deleting a group takes its Categories
// with it. The tests create their own group and delete it at the end, through
// the UI, so the demo household is left as it was.
function groupBlock(page: Page, name: string) {
    return page
        .locator('[data-kind="group"]')
        .filter({ has: page.getByRole('heading', { name, exact: true }) });
}

async function createGroup(page: Page, name: string) {
    await page
        .locator('[data-section="expense"]')
        .getByRole('button', { name: 'Dodaj grupę' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa grupy').fill(name);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();

    await expect(groupBlock(page, name)).toBeVisible();
}

async function deleteGroup(page: Page, name: string) {
    // A success toast from the previous step can sit over the row's menu button,
    // and hovering it keeps it open, so the pointer leaves the toasts and the
    // test waits until they are gone before clicking.
    await page.mouse.move(0, 0);
    await expect(page.locator('[data-sonner-toast]')).toHaveCount(0);

    await groupBlock(page, name)
        .getByRole('button', { name: `Więcej działań grupy ${name}` })
        .click();
    await page.getByRole('menuitem', { name: 'Usuń grupę' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Usuń grupę' })
        .click();

    await expect(groupBlock(page, name)).toHaveCount(0);
}

test('a Category is put into a new group with "Grupa…" and stays when the group is deleted', async ({
    page,
}) => {
    const group = 'Grupa E2E przypisanie';

    await page.goto('/categories');
    await createGroup(page, group);

    try {
        await categoryRow(page, 'Rozrywka')
            .getByRole('button', { name: 'Grupa kategorii Rozrywka' })
            .click();
        await page.getByRole('menuitem', { name: group }).click();

        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');
    } finally {
        await deleteGroup(page, group);
    }

    await expect(categoryRow(page, 'Rozrywka')).toHaveCount(1);
    await expect(categoryRow(page, 'Rozrywka')).toContainText(currentYear);
});

// Risk: the server rejects an order that no longer matches the list (a group
// was added in another tab), answers 422, and the page undoes the move without
// a word. The other tab is a direct request, so the rejection is the real one.
test('an order rejected by the server shows an error and the saved order', async ({
    page,
}) => {
    const group = 'Grupa E2E odrzucona kolejność';
    const otherTabGroup = 'Grupa E2E z innej karty';

    await page.goto('/categories');
    await createGroup(page, group);
    await post(page, '/categories/groups', {
        type: 'expense',
        name: otherTabGroup,
    });

    try {
        await page.mouse.move(0, 0);
        await expect(page.locator('[data-sonner-toast]')).toHaveCount(0);

        const reorderRejected = page.waitForResponse(
            (response) =>
                response.url().endsWith('/categories/reorder') &&
                response.request().method() === 'PATCH',
        );
        await groupBlock(page, group)
            .getByRole('button', { name: `Więcej działań grupy ${group}` })
            .click();
        await page.getByRole('menuitem', { name: 'W górę' }).click();
        expect((await reorderRejected).status()).toBe(422);

        await expect(
            page.getByText(
                'Nie udało się zapisać kolejności. Przywrócono poprzednią.',
            ),
        ).toBeVisible();
        // The page shows the order the server holds, with the other tab's group.
        await expect(groupBlock(page, otherTabGroup)).toBeVisible();
    } finally {
        await page.reload();
        await deleteGroup(page, otherTabGroup);
        await deleteGroup(page, group);
    }
});

// Risk: a second "W górę / W dół" while the first save runs overwrites the
// order the page restores when that save fails. The first save is held back,
// so the second move happens while it is still running.
test('a group move does not start while the previous save is running', async ({
    page,
}) => {
    const group = 'Grupa E2E jeden zapis';
    const sentOrders: string[] = [];
    let releaseSave: () => void = () => {};
    const saveHeld = new Promise<void>((resolve) => {
        releaseSave = resolve;
    });

    await page.goto('/categories');
    await createGroup(page, group);
    await page.route('**/categories/reorder', async (route) => {
        if (route.request().method() !== 'PATCH') {
            return route.fallback();
        }

        sentOrders.push(route.request().postData() ?? '');
        await saveHeld;
        await route.continue();
    });

    try {
        await page.mouse.move(0, 0);
        await expect(page.locator('[data-sonner-toast]')).toHaveCount(0);

        const menuButton = groupBlock(page, group).getByRole('button', {
            name: `Więcej działań grupy ${group}`,
        });
        await menuButton.click();
        await page.getByRole('menuitem', { name: 'W górę' }).click();
        await expect.poll(() => sentOrders.length).toBe(1);

        await menuButton.click();
        await page.getByRole('menuitem', { name: 'W dół' }).click();

        const saved = page.waitForResponse(
            (response) =>
                response.url().endsWith('/categories/reorder') &&
                response.request().method() === 'PATCH',
        );
        releaseSave();
        expect((await saved).status()).toBe(204);
        await page.waitForLoadState('networkidle');

        expect(sentOrders).toHaveLength(1);
    } finally {
        releaseSave();
        await page.unroute('**/categories/reorder');
        await page.reload();
        await deleteGroup(page, group);
    }
});

test('dragging a Category onto an empty group saves the membership', async ({
    page,
}) => {
    const group = 'Grupa E2E przeciąganie';

    await page.goto('/categories');
    await createGroup(page, group);

    try {
        const reorderSaved = page.waitForResponse(
            (response) =>
                response.url().endsWith('/categories/reorder') &&
                response.request().method() === 'PATCH',
        );

        // SortableJS needs several dragover events, which `dragTo` (one move) does
        // not send, so the pointer travels in steps. Passing the group header
        // swaps the dragged row below it and the layout shifts up by one row, so
        // the group body is measured again after the first leg of the move.
        const handle = categoryRow(page, 'Rozrywka').locator('.drag-handle');
        const body = groupBlock(page, group).locator('.categories-group-body');
        const from = await handle.boundingBox();
        const firstLeg = await body.boundingBox();
        if (!from || !firstLeg) {
            throw new Error(
                'The drag handle or the group body is not on the page.',
            );
        }

        await page.mouse.move(
            from.x + from.width / 2,
            from.y + from.height / 2,
        );
        await page.mouse.down();
        await page.mouse.move(from.x + 5, from.y + 5, { steps: 5 });
        await page.mouse.move(
            firstLeg.x + firstLeg.width / 2,
            firstLeg.y + firstLeg.height / 2,
            { steps: 20 },
        );

        // The swap animates for 150 ms; measuring during it gives a stale box.
        await page.waitForTimeout(300);
        const secondLeg = await body.boundingBox();
        if (!secondLeg) {
            throw new Error('The group body disappeared during the drag.');
        }
        const centerX = secondLeg.x + secondLeg.width / 2;
        const centerY = secondLeg.y + secondLeg.height / 2;
        await page.mouse.move(centerX, centerY, { steps: 10 });
        // A few small moves inside the empty list send the dragover events
        // SortableJS needs to take the row into it.
        await page.mouse.move(centerX + 12, centerY, { steps: 4 });
        await page.mouse.move(centerX - 12, centerY, { steps: 4 });
        await expect(
            groupBlock(page, group).locator('.categories-ghost'),
        ).toBeVisible();
        await page.mouse.up();
        expect((await reorderSaved).status()).toBe(204);

        await page.reload();
        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');
    } catch (error) {
        // A failed drag leaves the button down, which would block the cleanup.
        await page.mouse.up();
        throw error;
    } finally {
        await deleteGroup(page, group);
    }
});
