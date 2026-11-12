// E2E test: the „Kategorie” view (S-18, S-19).
// Risk it protects: the list of Categories is not reachable from the menu, a
// Category shows without the years of its Budget Lines, the link to Operations
// loses the Category id (and with it the history of all years), or a write
// from the list (create with a description, edit, archive, restore, delete)
// does not work in the browser, or an order the server refuses (422) stays on
// the page without a message. The test user has the demo household
// (demo:seed-household): a budget for the current year with the Category
// "Rozrywka"; the write flow creates its own Category and deletes it at the end.
import { test, expect, type Page } from '@playwright/test';
import {
    assignCategoryToGroup,
    openCategoryOperations,
} from './support/categories';

const currentYear = String(new Date().getFullYear());

function categoryRow(page: Page, name: string) {
    return page
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
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

test('the menu item Operacje leads to Operations filtered by the Category id over all years', async ({
    page,
}) => {
    await page.goto('/categories');

    await openCategoryOperations(
        page,
        categoryRow(page, 'Rozrywka'),
        'Rozrywka',
    );

    await expect(page).toHaveURL(/\/operations\?/);
    const query = new URL(page.url()).searchParams;
    expect(query.get('category_id')).toMatch(/^\d+$/);
    expect(query.get('date_from')).toBeNull();
    expect(query.get('date_to')).toBeNull();
    expect(query.get('year')).toBeNull();
});

test('an order the server refuses (422) is rolled back with a toast', async ({
    page,
}) => {
    await page.goto('/categories');

    const rows = page
        .locator('[data-section="expense"]')
        .locator('[data-category-row]');
    await expect(rows.nth(1)).toBeVisible();
    const orderBefore = await rows.allInnerTexts();

    // The list changed in another tab: ReorderCategoryList refuses the tree.
    await page.route('**/categories/reorder', (route) =>
        route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({
                message: 'The category order is invalid.',
                errors: { category_ids: ['The category order is invalid.'] },
            }),
        }),
    );

    const handle = rows.nth(0).getByRole('button', {
        name: 'Przeciągnij, aby zmienić kolejność',
    });
    await handle.dragTo(rows.nth(1));

    await expect(
        page.getByText(
            'Nie udało się zapisać kolejności. Przywrócono poprzednią.',
        ),
    ).toBeVisible();
    expect(await rows.allInnerTexts()).toEqual(orderBefore);
});

test('a Category is created with a description, edited, archived, restored and deleted from the UI', async ({
    page,
}) => {
    const name = `E2E kategoria ${Date.now()}`;
    const description = 'Opis dodany w teście przeglądarkowym';
    const editedDescription = 'Opis po edycji';

    await page.goto('/categories');

    // Create in the Wydatki panel, with a description.
    await page
        .getByRole('button', { name: 'Dodaj kategorię: Wydatki' })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nazwa').fill(name);
    await dialog.getByLabel('Opis').fill(description);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toHaveCount(0);

    const expensePanel = page.locator('[data-section="expense"]');
    const row = expensePanel
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
    await expect(row).toContainText(description);

    // Edit the description.
    const rowMenu = `Więcej działań kategorii ${name}`;
    await row.getByRole('button', { name: rowMenu }).click();
    await page.getByRole('menuitem', { name: 'Edytuj' }).click();
    await dialog.getByLabel('Opis').fill(editedDescription);
    await dialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toContainText(editedDescription);
    await expect(row).not.toContainText(description);

    // Archive: the row leaves the panel and lands in the collapsed archive.
    await row.getByRole('button', { name: rowMenu }).click();
    await page.getByRole('menuitem', { name: 'Archiwizuj' }).click();
    await dialog.getByRole('button', { name: 'Archiwizuj' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toHaveCount(0);

    const toggle = page.getByRole('button', { name: /^Archiwum \(\d+\)$/ });
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    const archivedRow = page
        .locator('[data-category-row]')
        .filter({ has: page.getByText(name, { exact: true }) });
    await expect(archivedRow).toContainText(
        /Zarchiwizowana \d{2}\.\d{2}\.\d{4}/,
    );

    // Restore: back in the Wydatki panel.
    await archivedRow.getByRole('button', { name: rowMenu }).click();
    await page.getByRole('menuitem', { name: 'Przywróć' }).click();
    await dialog.getByRole('button', { name: 'Przywróć' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toContainText(editedDescription);

    // Delete the fresh Category (no operations, so it can be deleted).
    await row.getByRole('button', { name: rowMenu }).click();
    await page.getByRole('menuitem', { name: 'Usuń' }).click();
    await dialog.getByRole('button', { name: 'Usuń' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toHaveCount(0);
});

// Category Groups (S-22, ADR-0069). Risk it protects: a group cannot be created,
// "Zmień grupę…" does not move a Category under the group, dragging a Category onto a
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

test('a Category is put into a new group with "Zmień grupę…" and stays when the group is deleted', async ({
    page,
}) => {
    const group = 'Grupa E2E przypisanie';

    await page.goto('/categories');
    await createGroup(page, group);

    try {
        await assignCategoryToGroup(
            page,
            categoryRow(page, 'Rozrywka'),
            'Rozrywka',
            group,
        );

        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');
    } finally {
        await deleteGroup(page, group);
    }

    await expect(categoryRow(page, 'Rozrywka')).toHaveCount(1);
    await expect(categoryRow(page, 'Rozrywka')).toContainText(currentYear);
});

// Risk: the „Zmień grupę…” dialog opens without the Category's current group
// selected, or "Bez grupy" does not take the Category back to the type level.
test('the group dialog shows the current group and "Bez grupy" takes the Category out of it', async ({
    page,
}) => {
    const group = 'Grupa E2E okno grupy';

    await page.goto('/categories');
    await createGroup(page, group);

    try {
        const row = categoryRow(page, 'Rozrywka');
        await assignCategoryToGroup(page, row, 'Rozrywka', group);
        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toContainText('Rozrywka');

        await page.mouse.move(0, 0);
        await expect(page.locator('[data-sonner-toast]')).toHaveCount(0);

        await row
            .getByRole('button', { name: 'Więcej działań kategorii Rozrywka' })
            .click();
        await page.getByRole('menuitem', { name: 'Zmień grupę…' }).click();
        const dialog = page.getByRole('dialog');
        await expect(
            dialog.getByRole('radio', { name: group, exact: true }),
        ).toBeChecked();

        await dialog.getByRole('radio', { name: 'Bez grupy' }).click();
        await dialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(dialog).toHaveCount(0);

        await expect(
            groupBlock(page, group).locator('[data-category-row]'),
        ).toHaveCount(0);
        await expect(categoryRow(page, 'Rozrywka')).toHaveCount(1);
    } finally {
        await deleteGroup(page, group);
    }
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

// Category Merge (S-21, ADR-0078). Risk it protects: the first dialog that loads
// a preview from the server before it confirms. The menu does not open the
// dialog, the preview does not show the operations that will move, "Scal" is not
// held back until the preview is there, or the operations stay on the source
// Category (which is gone) instead of the target. The test creates two fresh
// expense Categories and one operation, and cleans up through the UI; the
// target ends archived, because a Category with a line in a Budget in execution
// cannot be deleted (the same as in irregular-collected.spec.ts).
test('a Category is merged into another from the menu, with the preview, and its operation follows', async ({
    page,
}) => {
    const stamp = Date.now();
    const source = `E2E scalana ${stamp}`;
    const target = `E2E docelowa ${stamp}`;
    const description = `E2E operacja scalenia ${stamp}`;
    const consoleErrors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });

    await page.goto('/categories');
    for (const name of [source, target]) {
        await page
            .getByRole('button', { name: 'Dodaj kategorię: Wydatki' })
            .click();
        const createDialog = page.getByRole('dialog');
        await createDialog.getByLabel('Nazwa').fill(name);
        await createDialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(createDialog).toHaveCount(0);
    }

    // One operation on the source Category, booked on the Operations page.
    await page.goto('/operations');
    await page.getByRole('button', { name: 'Dodaj operację' }).click();
    await page.getByRole('menuitem', { name: 'Wydatek' }).click();
    const expenseDialog = page.getByRole('dialog', { name: 'Dodaj wydatek' });
    await expect(expenseDialog).toBeVisible();
    await expenseDialog.getByRole('textbox', { name: 'Kwota' }).fill('12,34');
    await expenseDialog
        .getByRole('combobox', { name: 'Kategoria wydatkowa' })
        .click();
    await page.getByRole('option', { name: source }).click();
    await expenseDialog
        .getByRole('textbox', { name: 'Opis' })
        .fill(description);
    await expenseDialog.getByRole('button', { name: 'Zapisz' }).click();
    await expect(expenseDialog).toBeHidden();

    try {
        await page.goto('/categories');
        await categoryRow(page, source)
            .getByRole('button', { name: `Więcej działań kategorii ${source}` })
            .click();
        await page.getByRole('menuitem', { name: 'Scal z inną…' }).click();

        const dialog = page.getByRole('dialog', {
            name: `Scal kategorię „${source}”`,
        });
        await expect(dialog).toBeVisible();
        // No target yet, so there is no preview and "Scal" is held back.
        const confirm = dialog.getByRole('button', {
            name: 'Scal',
            exact: true,
        });
        await expect(confirm).toBeDisabled();

        await dialog.getByRole('combobox', { name: 'Scal z' }).click();
        await page.getByRole('option', { name: target }).click();

        // The preview comes from the server: the total row counts the operation.
        await expect(
            dialog.getByRole('heading', { name: `Na „${target}” przejdzie` }),
        ).toBeVisible();
        const total = dialog
            .getByRole('row')
            .filter({ has: page.getByText('Razem', { exact: true }) });
        await expect(total.getByRole('cell').nth(1)).toHaveText('1');
        await expect(confirm).toBeEnabled();

        await confirm.click();
        await expect(dialog).toHaveCount(0);
        await expect(page.getByText('Kategorie scalone.')).toBeVisible();
        await expect(categoryRow(page, source)).toHaveCount(0);
        await expect(categoryRow(page, target)).toHaveCount(1);

        // The operation is under the target Category, over all years.
        await openCategoryOperations(page, categoryRow(page, target), target);
        await expect(page).toHaveURL(/\/operations\?/);
        await expect(
            page.getByText(description, { exact: true }),
        ).toBeVisible();
    } finally {
        await page.goto('/operations');
        const row = page
            .locator('div')
            .filter({ has: page.getByText(description, { exact: true }) })
            .filter({ has: page.getByRole('button', { name: 'Więcej akcji' }) })
            .last();
        if ((await row.count()) > 0) {
            await row.getByRole('button', { name: 'Więcej akcji' }).click();
            await page.getByRole('menuitem', { name: 'Usuń' }).click();
            await page
                .getByRole('dialog', { name: 'Usunąć wydatek?' })
                .getByRole('button', { name: 'Usuń' })
                .click();
            await expect(
                page.getByText(description, { exact: true }),
            ).toBeHidden();
        }

        await page.goto('/categories');
        for (const name of [source, target]) {
            const leftover = categoryRow(page, name);
            if ((await leftover.count()) > 0) {
                await leftover
                    .getByRole('button', {
                        name: `Więcej działań kategorii ${name}`,
                    })
                    .click();
                await page
                    .getByRole('menuitem', { name: 'Archiwizuj' })
                    .click();
                await page
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Archiwizuj' })
                    .click();
                await expect(page.getByRole('dialog')).toHaveCount(0);
            }
        }
    }

    expect(consoleErrors).toEqual([]);
});
