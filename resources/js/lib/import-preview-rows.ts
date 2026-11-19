import type { AccountBank } from './account-identity';
import type { CategoryPickerGroup } from './category-appearance';
import { parseMoneyInputToMinorUnits } from './format';
import type { Rhythm } from '../types/rhythm';
import {
    locksTypeToOwnTransfer,
    uniquePairCandidate,
    type PairCandidate,
    type PairRowSelection,
} from './import-pair-selection';

export type ImportBudgetYearState = 'execution' | 'draft' | 'missing';

/** Keyed by year; PHP encodes an empty map as `[]`, so a missing key means no note. */
export type ImportBudgetYearStates = Partial<
    Record<string, ImportBudgetYearState>
>;

export type ImportRow = {
    index: number;
    operation_date: string | null;
    description: string | null;
    counterparty: string | null;
    raw_statement_description: string | null;
    amount_minor_units: number | null;
    valid: boolean;
    /** Code of `ImportRowInvalidReason`; an unknown value is shown as received. */
    invalid_reason: string | null;
    import_duplicate: boolean;
    import_pair_candidate: boolean;
    pair_candidates: PairCandidate[];
    category_suggestion?: { income: number | null; expense: number | null };
};

export type CategoryRow = {
    id: number;
    name: string;
    icon: string;
    color: string;
    group?: CategoryPickerGroup | null;
    budget_year: number;
    /** Rhythm by month 1..12 of the year in `budget_year`; null for an income. */
    rhythms?: Record<number, Rhythm> | null;
};

export type TermDepositRow = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    opening_date: string;
};

export type RowSelection = PairRowSelection & {
    category_id: number | '';
    term_deposit_id: number | '';
    interest_amount_input: string;
    interest_category_id: number | '';
};

export function defaultRowType(
    row: ImportRow,
): 'income' | 'expense' | 'refund' {
    if ((row.amount_minor_units ?? 0) < 0) {
        return 'expense';
    }

    return 'income';
}

export function emptySelection(row: ImportRow): RowSelection {
    return {
        selected: false,
        type: defaultRowType(row),
        category_id: '',
        other_account_id: '',
        term_deposit_id: '',
        interest_amount_input: '',
        interest_category_id: '',
        pair_first_leg_kind: '',
        pair_first_leg_id: '',
    };
}

export function suggestedCategoryId(
    row: ImportRow,
    type: RowSelection['type'],
    incomeCategories: CategoryRow[],
    expenseCategories: CategoryRow[],
): number | '' {
    const suggestion = row.category_suggestion;

    if (suggestion === undefined) {
        return '';
    }

    let suggestedId: number | null;

    if (type === 'income') {
        suggestedId = suggestion.income;
    } else if (type === 'expense' || type === 'refund') {
        suggestedId = suggestion.expense;
    } else {
        return '';
    }

    if (suggestedId === null) {
        return '';
    }

    const isAvailable = categoriesForRow(
        row,
        type,
        incomeCategories,
        expenseCategories,
    ).some((category) => category.id === suggestedId);

    return isAvailable ? suggestedId : '';
}

export function showsCategorySuggestion(
    row: ImportRow,
    selection: RowSelection,
    incomeCategories: CategoryRow[],
    expenseCategories: CategoryRow[],
): boolean {
    return (
        selection.category_id !== '' &&
        selection.category_id ===
            suggestedCategoryId(
                row,
                selection.type,
                incomeCategories,
                expenseCategories,
            )
    );
}

export function initialSelection(
    row: ImportRow,
    incomeCategories: CategoryRow[],
    expenseCategories: CategoryRow[],
): RowSelection {
    const uniqueCandidate = uniquePairCandidate(row.pair_candidates);

    if (uniqueCandidate !== null) {
        return {
            selected: false,
            type: 'own_transfer',
            category_id: '',
            other_account_id: uniqueCandidate.account_id,
            term_deposit_id: '',
            interest_amount_input: '',
            interest_category_id: '',
            pair_first_leg_kind: uniqueCandidate.kind,
            pair_first_leg_id: uniqueCandidate.id,
        };
    }

    const type = locksTypeToOwnTransfer(row.pair_candidates)
        ? 'own_transfer'
        : defaultRowType(row);

    return {
        ...emptySelection(row),
        type,
        category_id: suggestedCategoryId(
            row,
            type,
            incomeCategories,
            expenseCategories,
        ),
    };
}

export function categoriesForRow(
    row: ImportRow,
    type: RowSelection['type'],
    incomeCategories: CategoryRow[],
    expenseCategories: CategoryRow[],
): CategoryRow[] {
    if (!row.operation_date) {
        return [];
    }

    const year = Number(row.operation_date.slice(0, 4));
    const source = type === 'income' ? incomeCategories : expenseCategories;

    return source.filter((category) => category.budget_year === year);
}

export function rowCategoriesEmpty(
    row: ImportRow,
    type: RowSelection['type'],
    incomeCategories: CategoryRow[],
    expenseCategories: CategoryRow[],
): boolean {
    return (
        categoriesForRow(row, type, incomeCategories, expenseCategories)
            .length === 0
    );
}

export function eligibleTermDeposits(
    row: ImportRow,
    termDeposits: TermDepositRow[],
): TermDepositRow[] {
    if (!row.operation_date) {
        return [];
    }

    return termDeposits.filter(
        (termDeposit) => termDeposit.opening_date <= row.operation_date!,
    );
}

export function isCreditRow(row: ImportRow): boolean {
    return (row.amount_minor_units ?? 0) > 0;
}

export function parsedInterestAmountMinorUnits(
    selection: RowSelection,
): number | null {
    if (selection.interest_amount_input.trim() === '') {
        return null;
    }

    return parseMoneyInputToMinorUnits(selection.interest_amount_input);
}

export function rowBudgetYearState(
    row: ImportRow,
    states: ImportBudgetYearStates,
): ImportBudgetYearState | null {
    if (row.operation_date === null) {
        return null;
    }

    const year = row.operation_date.slice(0, 4);

    return (Array.isArray(states) ? undefined : states[year]) ?? null;
}

export function rowBudgetYearNotice(
    row: ImportRow,
    selection: RowSelection,
    states: ImportBudgetYearStates,
): { state: 'draft' | 'missing'; year: number } | null {
    if (!row.valid) {
        return null;
    }

    const state = rowBudgetYearState(row, states);

    if (state === null || state === 'execution') {
        return null;
    }

    if (selection.type === 'own_transfer') {
        return null;
    }

    if (
        selection.type === 'capital_movement' &&
        !interestSplitsFileAmount(row, selection)
    ) {
        return null;
    }

    return {
        state,
        year: Number((row.operation_date as string).slice(0, 4)),
    };
}

export function rowNeedsBudgetInExecution(
    row: ImportRow,
    selection: RowSelection,
    states: ImportBudgetYearStates,
): boolean {
    return (
        selection.selected &&
        rowBudgetYearNotice(row, selection, states) !== null
    );
}

/**
 * The interest splits off as income: a credit row with 0 < interest < file
 * amount (`CapitalMovementInterestImportValidator::splitAmounts`).
 */
function interestSplitsFileAmount(
    row: ImportRow,
    selection: RowSelection,
): boolean {
    const interestAmount = parsedInterestAmountMinorUnits(selection);
    const fileAmount = row.amount_minor_units ?? 0;

    return (
        interestAmount !== null &&
        interestAmount > 0 &&
        interestAmount < fileAmount &&
        fileAmount > 0
    );
}

export function interestSplitValid(
    row: ImportRow,
    selection: RowSelection,
): boolean {
    if (parsedInterestAmountMinorUnits(selection) === null) {
        return selection.interest_category_id === '';
    }

    if (!interestSplitsFileAmount(row, selection)) {
        return false;
    }

    return selection.interest_category_id !== '';
}

export function capitalMovementSelectionValid(
    row: ImportRow,
    selection: RowSelection,
    termDeposits: TermDepositRow[],
): boolean {
    if (
        selection.term_deposit_id === '' ||
        eligibleTermDeposits(row, termDeposits).length === 0
    ) {
        return false;
    }

    if (!isCreditRow(row)) {
        return (
            selection.interest_amount_input.trim() === '' &&
            selection.interest_category_id === ''
        );
    }

    return interestSplitValid(row, selection);
}

export function wouldMakeAccountNegative(
    row: ImportRow,
    selection: RowSelection,
    accountBalanceMinorUnits: number,
): boolean {
    if (!selection.selected || selection.type !== 'own_transfer') {
        return false;
    }

    const signedAmount = row.amount_minor_units ?? 0;

    if (signedAmount >= 0) {
        return false;
    }

    return accountBalanceMinorUnits + signedAmount < 0;
}

export function descriptionVariant(
    type: RowSelection['type'],
): 'income' | 'expense' | 'refund' | 'transfer' {
    if (type === 'own_transfer' || type === 'capital_movement') {
        return 'transfer';
    }

    return type;
}

export function rowSelectionValid(
    row: ImportRow,
    selection: RowSelection,
    termDeposits: TermDepositRow[],
): boolean {
    if (!selection.selected) {
        return true;
    }

    if (!row.valid || selection.type === undefined) {
        return false;
    }

    if (selection.type === 'own_transfer') {
        return selection.other_account_id !== '';
    }

    if (selection.type === 'capital_movement') {
        return capitalMovementSelectionValid(row, selection, termDeposits);
    }

    return selection.category_id !== '';
}

export type ImportRowNoteKind =
    | 'invalidReason'
    | 'pairReason'
    | 'negativeBalance'
    | 'toSpend'
    | 'categoryMonthRemaining'
    | 'poolMonthRemaining'
    | 'budgetYear';

/**
 * Whether the row renders its notes block. The record type forces every note
 * kind to be listed, so a new alert cannot silently stay outside the block.
 */
export function importRowHasNotes(
    flags: Record<ImportRowNoteKind, boolean>,
): boolean {
    return Object.values(flags).some(Boolean);
}
