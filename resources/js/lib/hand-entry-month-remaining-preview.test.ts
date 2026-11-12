import { expect, test } from 'vite-plus/test';
import {
    previewHandEntryMonthRemainingWarnings,
    type HandEntryMonthRemainingCategory,
} from './hand-entry-month-remaining-preview';
import type { Rhythm } from '../types/rhythm';

const TODAY = '2026-11-10';

function everyMonth(rhythm: Rhythm): Record<number, Rhythm> {
    return Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => [index + 1, rhythm]),
    );
}

/** Running until October, even annual from November. */
function changedInNovember(): Record<number, Rhythm> {
    return { ...everyMonth('running'), 11: 'even_annual', 12: 'even_annual' };
}

function category(
    overrides: Partial<HandEntryMonthRemainingCategory> &
        Pick<HandEntryMonthRemainingCategory, 'id' | 'rhythms'>,
): HandEntryMonthRemainingCategory {
    return {
        planned_amount_minor_units: 120_000,
        monthly_execution_minor_units: {},
        ...overrides,
    };
}

function expensePreview(
    categories: HandEntryMonthRemainingCategory[],
    categoryId: number,
    operationDate: string,
    amountMinorUnits: number,
) {
    return previewHandEntryMonthRemainingWarnings({
        mode: 'create',
        operationDate,
        handEntryMonthRemaining: {
            today: TODAY,
            budgets: [{ year: 2026, is_draft: false, categories }],
        },
        editedRecord: null,
        proposal: { kind: 'expense', category_id: categoryId },
        signedAmountMinorUnits: amountMinorUnits,
    });
}

test('an expense over the month remaining of a fixed charge warns', () => {
    const result = expensePreview(
        [category({ id: 10, rhythms: everyMonth('fixed_charge') })],
        10,
        '2026-11-05',
        70_000,
    );

    expect(result.showOverMonthCategoryWarning).toBe(true);
});

test('the category warning takes the rhythm of the month of the operation', () => {
    const changed = [category({ id: 10, rhythms: changedInNovember() })];
    const running = [category({ id: 10, rhythms: everyMonth('running') })];

    expect(
        expensePreview(changed, 10, '2026-10-20', 70_000)
            .showOverMonthCategoryWarning,
    ).toBe(true);
    expect(expensePreview(changed, 10, '2026-11-05', 70_000)).toEqual({
        showOverMonthCategoryWarning: false,
        showOverMonthPoolWarning: false,
    });
    expect(
        expensePreview(running, 10, '2026-11-05', 70_000)
            .showOverMonthCategoryWarning,
    ).toBe(true);
});

test('the pool holds only the categories that are in it in the month of the operation', () => {
    const selected = category({
        id: 20,
        rhythms: everyMonth('running'),
        planned_amount_minor_units: 1_200_000,
    });
    const overspent = (rhythms: Record<number, Rhythm>) =>
        category({
            id: 21,
            rhythms,
            planned_amount_minor_units: 0,
            monthly_execution_minor_units: { 11: 5_000_000 },
        });

    expect(
        expensePreview(
            [selected, overspent(changedInNovember())],
            20,
            '2026-11-05',
            10_000,
        ).showOverMonthPoolWarning,
    ).toBe(false);
    expect(
        expensePreview(
            [selected, overspent(everyMonth('running'))],
            20,
            '2026-11-05',
            10_000,
        ).showOverMonthPoolWarning,
    ).toBe(true);
});
