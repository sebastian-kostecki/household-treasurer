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
import type { PoolRhythm } from './month-remaining-calculator';

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

function everyMonth(rhythm: PoolRhythm): Record<number, PoolRhythm> {
    return Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => [index + 1, rhythm]),
    );
}

function context(year2026Rhythm: PoolRhythm): MonthRemainingContext {
    const budget = (year: number, rhythm: PoolRhythm) => ({
        id: year,
        year,
        is_draft: false,
        categories: [
            {
                id: CATEGORY_ID,
                rhythms: everyMonth(rhythm),
                planned_amount_minor_units: 10_000,
            },
        ],
        execution_by_category_and_month: {},
    });

    return {
        today: '2027-03-15',
        budgets: [budget(2026, year2026Rhythm), budget(2027, 'running')],
    };
}

describe('computeImportMonthRemainingWarnings', () => {
    it('reads the rhythm of the list category from the year of the row, not of the first year', () => {
        const importRow = row('2027-03-10');
        const expenseCategories = [
            {
                id: CATEGORY_ID,
                budget_year: 2026,
                rhythms: everyMonth('even_annual'),
            },
            {
                id: CATEGORY_ID,
                budget_year: 2027,
                rhythms: everyMonth('running'),
            },
        ];

        const warnings = computeImportMonthRemainingWarnings(
            [importRow],
            { 0: selection(importRow) },
            expenseCategories,
            context('even_annual'),
            {},
        );

        expect(warnings[0].showCategoryWarning).toBe(true);
    });

    it('stays silent when the category of the row year is even annual', () => {
        const importRow = row('2026-03-10');
        const expenseCategories = [
            {
                id: CATEGORY_ID,
                budget_year: 2026,
                rhythms: everyMonth('even_annual'),
            },
            {
                id: CATEGORY_ID,
                budget_year: 2027,
                rhythms: everyMonth('running'),
            },
        ];

        const warnings = computeImportMonthRemainingWarnings(
            [importRow],
            { 0: selection(importRow) },
            expenseCategories,
            context('even_annual'),
            {},
        );

        expect(warnings[0].showCategoryWarning).toBe(false);
    });

    it('warns for a fixed charge and takes the rhythm of the month of the row', () => {
        const importRow = row('2027-03-10');
        const rhythms = {
            ...everyMonth('running'),
            3: 'even_annual' as const,
        };

        const warnings = computeImportMonthRemainingWarnings(
            [importRow],
            { 0: selection(importRow) },
            [{ id: CATEGORY_ID, budget_year: 2027, rhythms }],
            context('fixed_charge'),
            {},
        );

        expect(warnings[0].showCategoryWarning).toBe(false);
    });
});
