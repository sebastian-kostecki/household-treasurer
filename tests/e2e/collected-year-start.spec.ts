// E2E test: „zebrane startowe” in a Budget row (S-27, FR-012, ADR-0076).
// Risk it protects: the chain from marking a Category „nieregularna” with an
// opening collected amount, through creating the next year's Budget in the
// „Nowy budżet” dialog, to the Budget row breaks somewhere only the running
// app shows: the row has no „zebrane startowe” line, shows another amount than
// the one entered, or lacks the „prognoza” word before 1 January of that year.
// The Category has no plan in the current year, so its monthly rate is 0 and
// the result does not depend on the date the test runs. The test deletes the
// Budget it created and archives the Category, also when an assertion fails.
// Each cleanup step runs on its own: a failed step does not skip the next one
// and does not hide the test's own error.
import { test, expect, type Page } from '@playwright/test';
import {
    budgetYearCount,
    expectSelectedBudgetYear,
    nextBudgetYear,
    openCreateBudgetDialog,
    selectBudgetYear,
} from './support/budget-year';

async function deleteCurrentBudget(page: Page): Promise<void> {
    await page.goto('/budget');
    await page.getByRole('button', { name: 'Akcje budżetu' }).click();
    await page.getByRole('menuitem', { name: 'Usuń budżet' }).click();
    await page
        .getByRole('dialog', { name: 'Usunąć budżet?' })
        .getByRole('button', { name: 'Usuń budżet' })
        .click();
}

type CleanupStep = { label: string; run: () => Promise<void> };

/** Runs every step, also after a failed one, and returns the failures with their cause. */
async function runCleanup(steps: CleanupStep[]): Promise<Error[]> {
    const failures: Error[] = [];

    for (const step of steps) {
        try {
            await step.run();
        } catch (error) {
            failures.push(
                new Error(`Cleanup step failed: ${step.label}`, {
                    cause: error,
                }),
            );
        }
    }

    return failures;
}

test('the next year Budget row shows the collected amount at the start of the year as a forecast', async ({
    page,
}) => {
    const stamp = Date.now();
    const category = `E2E zebrane startowe ${stamp}`;
    let newYear = 0;
    let createdCategory = false;
    let createdBudget = false;
    let initialYearCount = 0;
    let testFailed = false;
    let testError: unknown;

    try {
        // Setup: an expense Category „nieregularna” with 500,00 collected at the start.
        await page.goto('/categories');
        await page
            .getByRole('button', { name: 'Dodaj kategorię: Wydatki' })
            .click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Nazwa').fill(category);
        await dialog
            .getByRole('button', { name: 'Nieregularna', exact: true })
            .click();
        await dialog.getByLabel('Zebrane startowe').fill('500,00');
        await dialog.getByRole('button', { name: 'Zapisz' }).click();
        // Set before the assertion: the request may have saved the Category
        // even when the dialog does not close in time.
        createdCategory = true;
        await expect(dialog).toHaveCount(0);

        await page.goto('/budget');
        initialYearCount = await budgetYearCount(page);
        newYear = await nextBudgetYear(page);
        expect(newYear).toBeGreaterThan(2000);

        await openCreateBudgetDialog(page);
        const budgetDialog = page.getByRole('dialog', { name: 'Nowy budżet' });
        await expect(budgetDialog).toBeVisible();
        await budgetDialog
            .getByRole('button', { name: 'Utwórz budżet' })
            .click();
        createdBudget = true;
        await expect(budgetDialog).toBeHidden();

        await expectSelectedBudgetYear(page, newYear);

        const row = page
            .locator('.budget-category-row')
            .filter({ has: page.getByText(category, { exact: true }) });
        await expect(row.locator('[data-collected-at-year-start]')).toHaveText(
            /zebrane startowe 500,00\s+·\s+prognoza/,
        );
    } catch (error) {
        testFailed = true;
        testError = error;
    }

    const cleanupSteps: CleanupStep[] = [];

    if (createdBudget) {
        cleanupSteps.push({
            label: `delete the ${newYear} Budget`,
            run: async () => {
                await page.goto('/budget');
                await selectBudgetYear(page, newYear);
                await deleteCurrentBudget(page);
                await expect
                    .poll(() => budgetYearCount(page))
                    .toBe(initialYearCount);
            },
        });
    }

    if (createdCategory) {
        cleanupSteps.push({
            label: `archive the Category „${category}”`,
            run: async () => {
                await page.goto('/categories');
                await page
                    .locator('[data-category-row]')
                    .filter({ has: page.getByText(category, { exact: true }) })
                    .getByRole('button', { name: 'Archiwizuj' })
                    .click();
                await page
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Archiwizuj' })
                    .click();
                await expect(page.getByRole('dialog')).toHaveCount(0);
            },
        });
    }

    const cleanupFailures = await runCleanup(cleanupSteps);

    if (testFailed) {
        // The test's own error is the evidence; cleanup failures go to the log.
        for (const failure of cleanupFailures) {
            console.error(failure, failure.cause);
        }

        throw testError;
    }

    if (cleanupFailures.length > 0) {
        throw new AggregateError(cleanupFailures, 'E2E cleanup failed');
    }
});
