import { expect, test } from 'vite-plus/test';
import { previewHandEntryWarnings } from './hand-entry-warning-preview';
import type { HandEntryPreviewInput } from './hand-entry-warning-preview';

const accounts = [
    { id: 1, current_balance_minor_units: 100_000 },
    { id: 2, current_balance_minor_units: -20_000 },
];
const termDeposits = [{ id: 7, current_balance_minor_units: 50_000 }];
const categories = [
    {
        id: 10,
        planned_amount_minor_units: 100_000,
        execution_minor_units: 30_000,
    },
];

function input(
    overrides: Partial<HandEntryPreviewInput> &
        Pick<HandEntryPreviewInput, 'proposal'>,
): HandEntryPreviewInput {
    return {
        mode: 'create',
        accounts,
        termDeposits,
        categories,
        editedRecord: null,
        ...overrides,
    };
}

test('create expense: balance and limit before and after, no flags', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'expense',
                account_id: 1,
                category_id: 10,
                amount_minor_units: 20_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceBeforeMinorUnits: 100_000,
        accountBalanceAfterMinorUnits: 80_000,
        termDepositBalanceBeforeMinorUnits: null,
        termDepositBalanceAfterMinorUnits: null,
        availableLimitBeforeMinorUnits: 70_000,
        availableLimitAfterMinorUnits: 50_000,
        showNegativeBalanceWarning: false,
        showOverLimitWarning: false,
    });
});

test('create expense over the limit and below zero raises both flags', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'expense',
                account_id: 1,
                category_id: 10,
                amount_minor_units: 120_000,
            },
        }),
    );

    expect(result.accountBalanceAfterMinorUnits).toBe(-20_000);
    expect(result.availableLimitAfterMinorUnits).toBe(-50_000);
    expect(result.showNegativeBalanceWarning).toBe(true);
    expect(result.showOverLimitWarning).toBe(true);
});

test('create refund raises balance and limit, without over-limit flag', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'refund',
                account_id: 1,
                category_id: 10,
                amount_minor_units: 5_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceBeforeMinorUnits: 100_000,
        accountBalanceAfterMinorUnits: 105_000,
        availableLimitBeforeMinorUnits: 70_000,
        availableLimitAfterMinorUnits: 75_000,
        showOverLimitWarning: false,
    });
});

test('edit expense unchanged: before equals after for balance and limit', () => {
    // stored balance 700, expense of 300 already included in it and in execution
    const result = previewHandEntryWarnings({
        mode: 'edit',
        accounts: [{ id: 1, current_balance_minor_units: 700 }],
        categories: [
            {
                id: 10,
                planned_amount_minor_units: 1_000,
                execution_minor_units: 300,
            },
        ],
        editedRecord: {
            account_id: 1,
            category_id: 10,
            signed_amount_minor_units: 300,
        },
        proposal: {
            kind: 'expense',
            account_id: 1,
            category_id: 10,
            amount_minor_units: 300,
        },
    });

    expect(result.accountBalanceBeforeMinorUnits).toBe(700);
    expect(result.accountBalanceAfterMinorUnits).toBe(700);
    expect(result.availableLimitBeforeMinorUnits).toBe(700);
    expect(result.availableLimitAfterMinorUnits).toBe(700);
    expect(result.showNegativeBalanceWarning).toBe(false);
    expect(result.showOverLimitWarning).toBe(false);
});

test('edit expense with a changed account: before is the balance of the new account', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 1,
                category_id: 10,
                signed_amount_minor_units: 10_000,
            },
            proposal: {
                kind: 'expense',
                account_id: 2,
                category_id: 10,
                amount_minor_units: 10_000,
            },
        }),
    );

    expect(result.accountBalanceBeforeMinorUnits).toBe(-20_000);
    expect(result.accountBalanceAfterMinorUnits).toBe(-30_000);
    expect(result.showNegativeBalanceWarning).toBe(true);
});

test('edit refund: stored negative amount is taken back', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 1,
                category_id: 10,
                signed_amount_minor_units: -4_000,
            },
            proposal: {
                kind: 'refund',
                account_id: 1,
                category_id: 10,
                amount_minor_units: 4_000,
            },
        }),
    );

    expect(result.accountBalanceBeforeMinorUnits).toBe(100_000);
    expect(result.accountBalanceAfterMinorUnits).toBe(100_000);
    expect(result.availableLimitBeforeMinorUnits).toBe(70_000);
    expect(result.availableLimitAfterMinorUnits).toBe(70_000);
});

test('create income adds to the balance, no flags, no limit', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'income',
                account_id: 2,
                category_id: 10,
                amount_minor_units: 50_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceBeforeMinorUnits: -20_000,
        accountBalanceAfterMinorUnits: 30_000,
        availableLimitBeforeMinorUnits: null,
        availableLimitAfterMinorUnits: null,
        showNegativeBalanceWarning: false,
    });
});

test('create income on a negative balance that stays negative does not flag', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'income',
                account_id: 2,
                amount_minor_units: 5_000,
            },
        }),
    );

    expect(result.accountBalanceBeforeMinorUnits).toBe(-20_000);
    expect(result.accountBalanceAfterMinorUnits).toBe(-15_000);
    expect(result.showNegativeBalanceWarning).toBe(false);
});

test('edit income unchanged: before equals after', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: { account_id: 1, signed_amount_minor_units: 25_000 },
            proposal: {
                kind: 'income',
                account_id: 1,
                amount_minor_units: 25_000,
            },
        }),
    );

    expect(result.accountBalanceBeforeMinorUnits).toBe(100_000);
    expect(result.accountBalanceAfterMinorUnits).toBe(100_000);
});

test('transfer reads the source account', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'own_transfer',
                account_id: 2,
                source_account_id: 1,
                amount_minor_units: 130_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceBeforeMinorUnits: 100_000,
        accountBalanceAfterMinorUnits: -30_000,
        termDepositBalanceBeforeMinorUnits: null,
        availableLimitBeforeMinorUnits: null,
        showNegativeBalanceWarning: true,
    });
});

test('edit transfer unchanged: before equals after', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 2,
                source_account_id: 1,
                signed_amount_minor_units: 10_000,
            },
            proposal: {
                kind: 'own_transfer',
                account_id: 2,
                source_account_id: 1,
                amount_minor_units: 10_000,
            },
        }),
    );

    expect(result.accountBalanceBeforeMinorUnits).toBe(100_000);
    expect(result.accountBalanceAfterMinorUnits).toBe(100_000);
});

test('capital movement to a term deposit lowers the account and raises the deposit', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'capital_movement',
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                amount_minor_units: 30_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceBeforeMinorUnits: 100_000,
        accountBalanceAfterMinorUnits: 70_000,
        termDepositBalanceBeforeMinorUnits: 50_000,
        termDepositBalanceAfterMinorUnits: 80_000,
        availableLimitBeforeMinorUnits: null,
        showNegativeBalanceWarning: false,
        showTermDepositNegativeBalanceWarning: false,
    });
});

test('capital movement from a term deposit over its balance flags the deposit', () => {
    const result = previewHandEntryWarnings(
        input({
            proposal: {
                kind: 'capital_movement',
                account_id: 1,
                term_deposit_id: 7,
                direction: 'from_term_deposit',
                amount_minor_units: 60_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceAfterMinorUnits: 160_000,
        termDepositBalanceAfterMinorUnits: -10_000,
        showTermDepositNegativeBalanceWarning: true,
    });
});

test('edit capital movement: before is the stored balance of account and deposit', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                signed_amount_minor_units: 30_000,
            },
            proposal: {
                kind: 'capital_movement',
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                amount_minor_units: 30_000,
            },
        }),
    );

    expect(result.accountBalanceBeforeMinorUnits).toBe(100_000);
    expect(result.termDepositBalanceBeforeMinorUnits).toBe(50_000);
});

test('edit capital movement to a deposit unchanged: before equals after for account and deposit', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                signed_amount_minor_units: -30_000,
            },
            proposal: {
                kind: 'capital_movement',
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                amount_minor_units: 30_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceBeforeMinorUnits: 100_000,
        accountBalanceAfterMinorUnits: 100_000,
        termDepositBalanceBeforeMinorUnits: 50_000,
        termDepositBalanceAfterMinorUnits: 50_000,
        showNegativeBalanceWarning: false,
        showTermDepositNegativeBalanceWarning: false,
    });
});

test('edit capital movement from a deposit unchanged: before equals after for account and deposit', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 1,
                term_deposit_id: 7,
                direction: 'from_term_deposit',
                signed_amount_minor_units: 30_000,
            },
            proposal: {
                kind: 'capital_movement',
                account_id: 1,
                term_deposit_id: 7,
                direction: 'from_term_deposit',
                amount_minor_units: 30_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceAfterMinorUnits: 100_000,
        termDepositBalanceAfterMinorUnits: 50_000,
    });
});

test('edit capital movement with a larger amount applies only the difference', () => {
    const result = previewHandEntryWarnings(
        input({
            mode: 'edit',
            editedRecord: {
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                signed_amount_minor_units: -30_000,
            },
            proposal: {
                kind: 'capital_movement',
                account_id: 1,
                term_deposit_id: 7,
                direction: 'to_term_deposit',
                amount_minor_units: 40_000,
            },
        }),
    );

    expect(result).toMatchObject({
        accountBalanceAfterMinorUnits: 90_000,
        termDepositBalanceAfterMinorUnits: 60_000,
    });
});
