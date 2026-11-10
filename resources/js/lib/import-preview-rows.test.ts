import { describe, expect, it } from 'vite-plus/test';
import {
    importRowHasNotes,
    initialSelection,
    showsCategorySuggestion,
    suggestedCategoryId,
    type CategoryRow,
    type ImportRow,
    type ImportRowNoteKind,
} from './import-preview-rows';

const kinds: ImportRowNoteKind[] = [
    'invalidReason',
    'pairReason',
    'negativeBalance',
    'toSpend',
    'categoryMonthRemaining',
    'poolMonthRemaining',
];

function flags(active?: ImportRowNoteKind): Record<ImportRowNoteKind, boolean> {
    return Object.fromEntries(
        kinds.map((kind) => [kind, kind === active]),
    ) as Record<ImportRowNoteKind, boolean>;
}

describe('importRowHasNotes', () => {
    it('is false when no note applies', () => {
        expect(importRowHasNotes(flags())).toBe(false);
    });

    it.each(kinds)('is true for %s alone', (kind) => {
        expect(importRowHasNotes(flags(kind))).toBe(true);
    });
});

function category(id: number, budgetYear: number): CategoryRow {
    return {
        id,
        name: `Kategoria ${id}`,
        icon: 'tag',
        color: '#000000',
        budget_year: budgetYear,
        rhythms: null,
    };
}

const incomeCategories = [category(1, 2026), category(2, 2025)];
const expenseCategories = [category(11, 2026), category(12, 2025)];

function suggestionRow(overrides: Partial<ImportRow> = {}): ImportRow {
    return {
        index: 0,
        operation_date: '2026-10-12',
        description: 'Opis',
        counterparty: null,
        raw_statement_description: null,
        amount_minor_units: -1_000,
        valid: true,
        invalid_reason: null,
        import_duplicate: false,
        import_pair_candidate: false,
        pair_candidates: [],
        category_suggestion: { income: 1, expense: 11 },
        ...overrides,
    };
}

describe('suggestedCategoryId', () => {
    const suggest = (
        row: ImportRow,
        type: Parameters<typeof suggestedCategoryId>[1],
    ) => suggestedCategoryId(row, type, incomeCategories, expenseCategories);

    it('picks the income suggestion for income', () => {
        expect(suggest(suggestionRow(), 'income')).toBe(1);
    });

    it('picks the expense suggestion for expense', () => {
        expect(suggest(suggestionRow(), 'expense')).toBe(11);
    });

    it('picks the expense suggestion for refund on a positive row', () => {
        const row = suggestionRow({ amount_minor_units: 1_000 });

        expect(suggest(row, 'refund')).toBe(11);
    });

    it('is empty for own_transfer and capital_movement', () => {
        expect(suggest(suggestionRow(), 'own_transfer')).toBe('');
        expect(suggest(suggestionRow(), 'capital_movement')).toBe('');
    });

    it('is empty for an id from another year', () => {
        const row = suggestionRow({
            category_suggestion: { income: 2, expense: 12 },
        });

        expect(suggest(row, 'income')).toBe('');
        expect(suggest(row, 'expense')).toBe('');
    });

    it('is empty for an id missing from the list', () => {
        const row = suggestionRow({
            category_suggestion: { income: 99, expense: 98 },
        });

        expect(suggest(row, 'income')).toBe('');
        expect(suggest(row, 'expense')).toBe('');
    });

    it('is empty when the suggestion is null', () => {
        const row = suggestionRow({
            category_suggestion: { income: null, expense: null },
        });

        expect(suggest(row, 'expense')).toBe('');
    });

    it('is empty for a row from an older session without the suggestion key', () => {
        const row: ImportRow = {
            ...suggestionRow(),
            category_suggestion: undefined,
        };

        expect(suggest(row, 'income')).toBe('');
        expect(suggest(row, 'expense')).toBe('');
    });

    it('is empty for a row without a date', () => {
        expect(
            suggest(suggestionRow({ operation_date: null }), 'expense'),
        ).toBe('');
    });
});

describe('initialSelection', () => {
    it('starts with the suggestion for the default type', () => {
        const expense = initialSelection(
            suggestionRow(),
            incomeCategories,
            expenseCategories,
        );
        const income = initialSelection(
            suggestionRow({ amount_minor_units: 1_000 }),
            incomeCategories,
            expenseCategories,
        );

        expect(expense.type).toBe('expense');
        expect(expense.category_id).toBe(11);
        expect(income.type).toBe('income');
        expect(income.category_id).toBe(1);
    });

    it('leaves the category empty for a unique pair candidate', () => {
        const row = suggestionRow({
            import_pair_candidate: true,
            pair_candidates: [
                {
                    kind: 'financial_operation',
                    id: 501,
                    account_id: 2,
                    account_name: 'Konto',
                    account_type: 'bank',
                    account_bank: 'mbank',
                    account_mark_url: '/marks/mbank.jpg',
                    date: '2026-10-12',
                    description: 'Przelew',
                    converts_financial_operation: false,
                },
            ],
        });
        const selection = initialSelection(
            row,
            incomeCategories,
            expenseCategories,
        );

        expect(selection.type).toBe('own_transfer');
        expect(selection.category_id).toBe('');
    });

    it('never selects the row', () => {
        const selection = initialSelection(
            suggestionRow(),
            incomeCategories,
            expenseCategories,
        );

        expect(selection.selected).toBe(false);
    });
});

describe('showsCategorySuggestion', () => {
    it('follows the chosen category', () => {
        const row = suggestionRow();
        const selection = initialSelection(
            row,
            incomeCategories,
            expenseCategories,
        );
        const shows = () =>
            showsCategorySuggestion(
                row,
                selection,
                incomeCategories,
                expenseCategories,
            );

        expect(shows()).toBe(true);

        selection.category_id = '';
        expect(shows()).toBe(false);

        selection.category_id = 12;
        expect(shows()).toBe(false);

        selection.category_id = 11;
        expect(shows()).toBe(true);
    });
});
