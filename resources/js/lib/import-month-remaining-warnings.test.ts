import { describe, expect, it } from 'vite-plus/test';
import {
    computeImportMonthRemainingWarnings,
    type MonthRemainingContext,
} from './import-month-remaining-warnings';
import {
    emptySelection,
    type ImportRow,
    type RowSelection,
} from './import-preview-rows';

const CATEGORY_ID = 7;

function row(operationDate: string): ImportRow {
    return {
        index: 0,
        operation_date: operationDate,
        description: null,
        counterparty: null,
        raw_statement_description: null,
        amount_minor_units: -50_000,
        valid: true,
        invalid_reason: null,
        import_duplicate: false,
        import_pair_candidate: false,
        pair_candidates: [],
    };
}

function selection(importRow: ImportRow): RowSelection {
    return {
        ...emptySelection(importRow),
        selected: true,
        type: 'expense',
        category_id: CATEGORY_ID,
    };
}

function context(year2026Paced: boolean): MonthRemainingContext {
    const budget = (year: number, isPaced: boolean) => ({
        id: year,
        year,
        is_draft: false,
        categories: [
            {
                id: CATEGORY_ID,
                is_paced: isPaced,
                planned_amount_minor_units: 10_000,
            },
        ],
        execution_by_category_and_month: {},
    });

    return {
        today: '2027-03-15',
        budgets: [budget(2026, year2026Paced), budget(2027, true)],
    };
}

describe('computeImportMonthRemainingWarnings', () => {
    it('reads is_paced of the list category from the year of the row, not of the first year', () => {
        const importRow = row('2027-03-10');
        const expenseCategories = [
            { id: CATEGORY_ID, budget_year: 2026, is_paced: false },
            { id: CATEGORY_ID, budget_year: 2027, is_paced: true },
        ];

        const warnings = computeImportMonthRemainingWarnings(
            [importRow],
            { 0: selection(importRow) },
            expenseCategories,
            context(false),
            {},
        );

        expect(warnings[0].showCategoryWarning).toBe(true);
    });

    it('stays silent when the category of the row year is not paced', () => {
        const importRow = row('2026-03-10');
        const expenseCategories = [
            { id: CATEGORY_ID, budget_year: 2026, is_paced: false },
            { id: CATEGORY_ID, budget_year: 2027, is_paced: true },
        ];

        const warnings = computeImportMonthRemainingWarnings(
            [importRow],
            { 0: selection(importRow) },
            expenseCategories,
            context(false),
            {},
        );

        expect(warnings[0].showCategoryWarning).toBe(false);
    });
});
