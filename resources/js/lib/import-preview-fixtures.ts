import type { AccountPickerOption } from './account-identity';
import type { ToSpendWarning } from './entry-to-spend-warning';
import type { MonthRemainingWarningState } from './import-month-remaining-warnings';
import type { PairCandidate } from './import-pair-selection';
import {
    emptySelection,
    type CategoryRow,
    type ImportRow,
    type RowSelection,
    type TermDepositRow,
} from './import-preview-rows';

const pairCandidate: PairCandidate = {
    kind: 'financial_operation',
    id: 501,
    account_id: 2,
    account_name: 'Konto oszczędnościowe',
    date: '2026-10-12',
    description: 'Przelew na oszczędności',
    converts_financial_operation: false,
    reason: 'Ta sama kwota i data w drugim koncie',
};

function row(index: number, overrides: Partial<ImportRow>): ImportRow {
    return {
        index,
        operation_date: '2026-10-12',
        description: 'Opis operacji',
        counterparty: null,
        raw_statement_description: null,
        amount_minor_units: -12_500,
        valid: true,
        invalid_reason: null,
        import_duplicate: false,
        import_pair_candidate: false,
        pair_candidates: [],
        category_suggestion: { income: null, expense: null },
        ...overrides,
    };
}

export const incomeRow = row(0, {
    operation_date: '2026-10-10',
    description: 'Wynagrodzenie październik',
    counterparty: 'Acme Sp. z o.o.',
    amount_minor_units: 850_000,
});

export const expenseRow = row(1, {
    operation_date: '2026-10-11',
    description: 'Zakupy spożywcze',
    counterparty: 'Biedronka',
    raw_statement_description: 'PŁATNOŚĆ KARTĄ 12.10 BIEDRONKA 1234 WARSZAWA',
    amount_minor_units: -23_480,
});

export const duplicateRow = row(2, {
    operation_date: '2026-10-05',
    description: 'Czynsz',
    amount_minor_units: -180_000,
    import_duplicate: true,
});

export const pairRow = row(3, {
    description: 'Przelew na oszczędności',
    amount_minor_units: -100_000,
    import_pair_candidate: true,
    pair_candidates: [pairCandidate],
});

export const invalidRow = row(4, {
    operation_date: '2026-12-31',
    description: 'Operacja z przyszłości',
    amount_minor_units: -4_900,
    valid: false,
    invalid_reason: 'Data operacji nie może być w przyszłości.',
});

export const nullAmountRow = row(5, {
    description: 'Wiersz walutowy',
    amount_minor_units: null,
});

export const capitalMovementRow = row(6, {
    operation_date: '2026-10-20',
    description: 'Wypłata z lokaty',
    amount_minor_units: 1_012_345,
});

export const suggestedCategoryRow = row(7, {
    operation_date: '2026-10-14',
    description: 'Zakupy spożywcze',
    counterparty: 'Żabka',
    amount_minor_units: -3_290,
    category_suggestion: { income: null, expense: 11 },
});

export const importPreviewRows: ImportRow[] = [
    incomeRow,
    expenseRow,
    duplicateRow,
    pairRow,
    invalidRow,
    nullAmountRow,
    capitalMovementRow,
    suggestedCategoryRow,
];

export const incomeCategories: CategoryRow[] = [
    {
        id: 1,
        name: 'Wynagrodzenie',
        icon: 'briefcase',
        color: '#16a34a',
        budget_year: 2026,
        is_paced: false,
    },
    {
        id: 2,
        name: 'Odsetki',
        icon: 'landmark',
        color: '#0d9488',
        budget_year: 2026,
        is_paced: false,
    },
];

export const expenseCategories: CategoryRow[] = [
    {
        id: 11,
        name: 'Jedzenie',
        icon: 'shopping-cart',
        color: '#0d9488',
        budget_year: 2026,
        is_paced: true,
    },
    {
        id: 12,
        name: 'Mieszkanie',
        icon: 'house',
        color: '#7c3aed',
        budget_year: 2026,
        is_paced: false,
    },
];

export const otherAccounts: Array<
    AccountPickerOption & { current_balance_minor_units: number }
> = [
    {
        id: 2,
        name: 'Konto oszczędnościowe',
        type: 'bank',
        bank: 'ing',
        mark_url: null,
        current_balance_minor_units: 2_500_000,
    },
    {
        id: 3,
        name: 'Gotówka',
        type: 'cash',
        bank: null,
        mark_url: null,
        current_balance_minor_units: 30_000,
    },
];

export const importAccount: AccountPickerOption & {
    current_balance_minor_units: number;
} = {
    id: 1,
    name: 'Konto osobiste',
    type: 'bank',
    bank: 'mbank',
    mark_url: null,
    current_balance_minor_units: 120_000,
};

export const termDeposits: TermDepositRow[] = [
    { id: 1, name: 'Lokata 12M', opening_date: '2026-01-15' },
];

export const noTermDeposits: TermDepositRow[] = [];

export const sampleToSpendWarning: ToSpendWarning = {
    year: 2026,
    month: 10,
    toSpendAfterMinorUnits: -45_000,
};

export const sampleMonthRemainingWarning: MonthRemainingWarningState = {
    showCategoryWarning: true,
    showPoolWarning: true,
    periodLabel: 'Październik 2026',
};

export function importPreviewSelections(): Record<number, RowSelection> {
    const selections: Record<number, RowSelection> = Object.fromEntries(
        importPreviewRows.map((previewRow) => [
            previewRow.index,
            emptySelection(previewRow),
        ]),
    );

    selections[1] = {
        ...selections[1],
        selected: true,
        type: 'expense',
        category_id: 11,
    };
    selections[3] = {
        ...selections[3],
        selected: true,
        type: 'own_transfer',
        other_account_id: 2,
        pair_first_leg_kind: pairCandidate.kind,
        pair_first_leg_id: pairCandidate.id,
    };
    selections[7] = {
        ...selections[7],
        type: 'expense',
        category_id: 11,
    };
    selections[6] = {
        ...selections[6],
        selected: true,
        type: 'capital_movement',
        term_deposit_id: 1,
        interest_amount_input: '123,45',
        interest_category_id: 2,
    };

    return selections;
}
