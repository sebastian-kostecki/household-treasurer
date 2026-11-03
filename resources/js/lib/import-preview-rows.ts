import { parseMoneyInputToMinorUnits } from './format';
import {
    locksTypeToOwnTransfer,
    uniquePairCandidate,
    type PairCandidate,
    type PairRowSelection,
} from './import-pair-selection';

export type ImportRow = {
    index: number;
    operation_date: string | null;
    description: string | null;
    counterparty: string | null;
    raw_statement_description: string | null;
    amount_minor_units: number | null;
    valid: boolean;
    invalid_reason: string | null;
    import_duplicate: boolean;
    import_pair_candidate: boolean;
    pair_candidates: PairCandidate[];
};

export type CategoryRow = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
    is_paced: boolean;
};

export type TermDepositRow = {
    id: number;
    name: string;
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

export function initialSelection(row: ImportRow): RowSelection {
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

    return {
        ...emptySelection(row),
        type: locksTypeToOwnTransfer(row.pair_candidates)
            ? 'own_transfer'
            : defaultRowType(row),
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

export function interestSplitValid(
    row: ImportRow,
    selection: RowSelection,
): boolean {
    const interestAmount = parsedInterestAmountMinorUnits(selection);

    if (interestAmount === null) {
        return selection.interest_category_id === '';
    }

    const fileAmount = row.amount_minor_units ?? 0;

    if (
        interestAmount <= 0 ||
        interestAmount >= fileAmount ||
        fileAmount <= 0
    ) {
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

export function rowAmountClass(row: ImportRow): string {
    if ((row.amount_minor_units ?? 0) < 0) {
        return 'text-destructive';
    }

    return 'text-success';
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
