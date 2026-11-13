import { describe, expect, it } from 'vite-plus/test';
import {
    reconciliationDayIsAllowed,
    reconciliationDifference,
    reconciliationLastLead,
    reconciliationLastVerdict,
    reconciliationOperationsHeading,
    reconciliationTruncationNote,
} from './balance-reconciliation';
import type {
    AccountLastReconciliation,
    ReconciliationPreview,
} from '../types/accounts';

const words = {
    reconcile_last: 'Uzgodniono :date',
    reconcile_last_agrees: 'zgadza się',
    reconcile_last_disagrees: 'nie zgadza się, różnica :amount',
    reconcile_operations_since: 'Operacje od :date',
    reconcile_operations_all: 'Wszystkie operacje do tego dnia',
    reconcile_operations_truncated: 'Pokazano :shown z :total',
};

function reading(
    overrides: Partial<AccountLastReconciliation> = {},
): AccountLastReconciliation {
    return {
        reconciled_on: '2026-10-31',
        bank_balance_minor_units: 130000,
        balance_minor_units: 130000,
        difference_minor_units: 0,
        agrees: true,
        ...overrides,
    };
}

function preview(
    overrides: Partial<ReconciliationPreview> = {},
): ReconciliationPreview {
    return {
        reconciled_on: '2026-11-13',
        balance_minor_units: 0,
        previous_reconciled_on: null,
        existing_bank_balance_minor_units: null,
        operations: [],
        operations_count: 0,
        ...overrides,
    };
}

describe('balance reconciliation', () => {
    it('reads the difference as bank balance minus app balance', () => {
        expect(reconciliationDifference(130000, 134000)).toBe(-4000);
        expect(reconciliationDifference(100, 100)).toBe(0);
        expect(reconciliationDifference(-2500, 0)).toBe(-2500);
    });

    it('allows a day between the opening date and today, both included', () => {
        expect(
            reconciliationDayIsAllowed(
                '2026-03-31',
                '2026-01-01',
                '2026-06-01',
            ),
        ).toBe(true);
        expect(
            reconciliationDayIsAllowed(
                '2026-01-01',
                '2026-01-01',
                '2026-06-01',
            ),
        ).toBe(true);
        expect(
            reconciliationDayIsAllowed(
                '2026-06-01',
                '2026-01-01',
                '2026-06-01',
            ),
        ).toBe(true);
        expect(
            reconciliationDayIsAllowed(
                '2025-12-31',
                '2026-01-01',
                '2026-06-01',
            ),
        ).toBe(false);
        expect(
            reconciliationDayIsAllowed(
                '2026-06-02',
                '2026-01-01',
                '2026-06-01',
            ),
        ).toBe(false);
        expect(reconciliationDayIsAllowed('', '2026-01-01', '2026-06-01')).toBe(
            false,
        );
    });

    it('words the line under an Account name', () => {
        expect(reconciliationLastLead(reading(), words)).toBe(
            'Uzgodniono 31.10.2026',
        );
        expect(reconciliationLastVerdict(reading(), words)).toBe('zgadza się');
        expect(
            reconciliationLastVerdict(
                reading({ agrees: false, difference_minor_units: -1240 }),
                words,
            ).replace(/\s/g, ' '),
        ).toBe('nie zgadza się, różnica -12,40 zł'.replace(/\s/g, ' '));
    });

    it('heads the operations list by the previous reconciliation', () => {
        expect(reconciliationOperationsHeading(preview(), words)).toBe(
            'Wszystkie operacje do tego dnia',
        );
        expect(
            reconciliationOperationsHeading(
                preview({ previous_reconciled_on: '2026-09-30' }),
                words,
            ),
        ).toBe('Operacje od 30.09.2026');
    });

    it('notes a truncated operations list only when it is truncated', () => {
        expect(reconciliationTruncationNote(preview(), words)).toBeNull();
        expect(
            reconciliationTruncationNote(
                preview({
                    operations_count: 340,
                    operations: Array.from({ length: 100 }, (_, index) => ({
                        key: `operation-${index}`,
                        kind: 'income' as const,
                        date: '2026-11-01',
                        description: 'x',
                        amount_minor_units: 1,
                    })),
                }),
                words,
            ),
        ).toBe('Pokazano 100 z 340');
    });
});
