import { expect, test } from 'vite-plus/test';
import type {
    EntryToSpendCategory,
    EntryToSpendInputs,
    EntryToSpendItem,
} from './entry-to-spend-warning';
import { computeImportToSpendWarnings } from './import-to-spend-warnings';
import type { ImportRow, RowSelection } from './import-preview-rows';

const SALARY = 1;
const INTEREST = 2;
const GROCERIES = 3;

const categories: EntryToSpendCategory[] = [
    { id: SALARY, type: 'income', month_item_kind: null },
    { id: INTEREST, type: 'income', month_item_kind: null },
    { id: GROCERIES, type: 'expense', month_item_kind: 'running' },
];

function item(
    categoryId: number,
    plan: number,
    actual = 0,
    positiveCount = actual > 0 ? 1 : 0,
): EntryToSpendItem {
    return {
        category_id: categoryId,
        plan_minor_units: plan,
        actual_minor_units: actual,
        positive_amount_count: positiveCount,
    };
}

/** November 2026 To Spend: income actual 5 000 (50 zł), Running plan 0. */
function inputs(): EntryToSpendInputs {
    return {
        budgets: [
            {
                year: 2026,
                categories,
                months: [
                    {
                        month: 11,
                        items: [
                            item(SALARY, 500_000, 5_000),
                            item(GROCERIES, 0),
                        ],
                    },
                ],
            },
        ],
    };
}

function row(
    index: number,
    amount: number,
    date: string | null = '2026-11-02',
    valid = true,
): ImportRow {
    return {
        index,
        operation_date: date,
        description: null,
        counterparty: null,
        raw_statement_description: null,
        amount_minor_units: amount,
        valid,
        invalid_reason: null,
        import_duplicate: false,
        import_pair_candidate: false,
        pair_candidates: [],
    };
}

function selection(overrides: Partial<RowSelection> = {}): RowSelection {
    return {
        selected: true,
        type: 'expense',
        category_id: GROCERIES,
        other_account_id: '',
        term_deposit_id: '',
        interest_amount_input: '',
        interest_category_id: '',
        pair_first_leg_kind: '',
        pair_first_leg_id: '',
        ...overrides,
    };
}

test('accumulates selected rows in index order and warns from the row that crosses zero', () => {
    const rows = [row(0, -3_000), row(1, -4_000), row(2, 10_000)];
    const result = computeImportToSpendWarnings(
        rows,
        {
            0: selection(),
            1: selection(),
            2: selection({ type: 'income', category_id: SALARY }),
        },
        inputs(),
    );

    expect(result[0]).toBeNull();
    expect(result[1]).toEqual({
        year: 2026,
        month: 11,
        toSpendAfterMinorUnits: -2_000,
    });
    expect(result[2]).toBeNull();
});

test('skips unselected, uncategorised and own transfer rows without feeding pending', () => {
    const rows = [
        row(0, -9_000),
        row(1, -9_000),
        row(2, -9_000),
        row(3, -1_000),
    ];
    const result = computeImportToSpendWarnings(
        rows,
        {
            0: selection({ selected: false }),
            1: selection({ category_id: '' }),
            2: selection({ type: 'own_transfer', other_account_id: 5 }),
            3: selection(),
        },
        inputs(),
    );

    expect(result[0]).toBeNull();
    expect(result[1]).toBeNull();
    expect(result[2]).toBeNull();
    expect(result[3]).toBeNull();
});

test('capital movement interest changes the baseline of a later row but does not warn itself', () => {
    const rows = [row(0, 10_000, '2026-11-02'), row(1, -4_000)];
    const result = computeImportToSpendWarnings(
        rows,
        {
            0: selection({
                type: 'capital_movement',
                category_id: '',
                term_deposit_id: 9,
                interest_amount_input: '20',
                interest_category_id: INTEREST,
            }),
            1: selection(),
        },
        inputs(),
    );

    expect(result[0]).toBeNull();
    // 50 zł + 20 zł interest = 70 zł; minus 40 zł = 30 zł: no warning.
    expect(result[1]).toBeNull();

    const without = computeImportToSpendWarnings(
        [row(0, -6_000)],
        { 0: selection() },
        inputs(),
    );

    // Without interest 50 zł − 60 zł is negative.
    expect(without[0]?.toSpendAfterMinorUnits).toBe(-1_000);
});

test('a refund is counted with a negative sign and never warns', () => {
    const result = computeImportToSpendWarnings(
        [row(0, 4_000)],
        { 0: selection({ type: 'refund' }) },
        inputs(),
    );

    expect(result[0]).toBeNull();
});

test('rows from different months do not affect each other', () => {
    const rows = [row(0, -6_000, '2026-10-30'), row(1, -3_000, '2026-11-02')];
    const result = computeImportToSpendWarnings(
        rows,
        { 0: selection(), 1: selection() },
        inputs(),
    );

    expect(result[0]).toBeNull();
    expect(result[1]).toBeNull();
});

test('invalid rows and rows without date are ignored', () => {
    const result = computeImportToSpendWarnings(
        [row(0, -9_000, '2026-11-02', false), row(1, -9_000, null)],
        { 0: selection(), 1: selection() },
        inputs(),
    );

    expect(result[0]).toBeNull();
    expect(result[1]).toBeNull();
});
