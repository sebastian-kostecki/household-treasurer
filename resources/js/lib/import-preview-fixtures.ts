import { DEFAULT_CATEGORY_COLOR } from './category-appearance';
import type { AccountPickerOption } from './account-identity';
import type {
    EntryToSpendInputs,
    ToSpendWarning,
} from './entry-to-spend-warning';
import type {
    MonthRemainingContext,
    MonthRemainingWarningState,
} from './import-month-remaining-warnings';
import type { PairCandidate } from './import-pair-selection';
import type { Rhythm } from '../types/rhythm';
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
    account_type: 'bank',
    account_bank: 'mbank',
    account_mark_url: '/marks/mbank.jpg',
    date: '2026-10-12',
    description: 'Przelew na oszczędności',
    converts_financial_operation: false,
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
    invalid_reason: 'future_date',
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

export const ownTransferPairRow = row(8, {
    operation_date: '2026-10-13',
    description: 'Wpłata z konta oszczędnościowego',
    amount_minor_units: 50_000,
    import_pair_candidate: true,
    pair_candidates: [
        {
            kind: 'own_transfer',
            id: 601,
            account_id: 2,
            account_name: 'Konto oszczędnościowe',
            account_type: 'bank',
            account_bank: 'mbank',
            account_mark_url: '/marks/mbank.jpg',
            date: '2026-10-13',
            description: 'Przelew własny',
            converts_financial_operation: false,
        },
    ],
});

export const ownTransferPairNoLogoRow = row(10, {
    operation_date: '2026-10-16',
    description: 'Wpłata z konta oszczędnościowego (ING)',
    amount_minor_units: 30_000,
    import_pair_candidate: true,
    pair_candidates: [
        {
            kind: 'financial_operation',
            id: 701,
            account_id: 2,
            account_name: 'Konto oszczędnościowe',
            account_type: 'bank',
            account_bank: 'ing',
            account_mark_url: null,
            date: '2026-10-16',
            description: 'Przelew na konto',
            converts_financial_operation: false,
        },
    ],
});

export const refundRow = row(9, {
    operation_date: '2026-10-15',
    description: 'Zwrot za zakupy',
    counterparty: 'Biedronka',
    amount_minor_units: 6_400,
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
    ownTransferPairRow,
    ownTransferPairNoLogoRow,
    refundRow,
];

export const importPreviewMonthRemainingContext: MonthRemainingContext = {
    today: '2026-10-20',
    budgets: [],
};

export const importPreviewEntryToSpend: EntryToSpendInputs = {
    budgets: [],
};

function everyMonth(rhythm: Rhythm): Record<number, Rhythm> {
    return Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => [index + 1, rhythm]),
    );
}

export const incomeCategories: CategoryRow[] = [
    {
        id: 1,
        name: 'Wynagrodzenie',
        icon: 'briefcase',
        color: DEFAULT_CATEGORY_COLOR,
        budget_year: 2026,
        rhythms: null,
    },
    {
        id: 2,
        name: 'Odsetki',
        icon: 'landmark',
        color: DEFAULT_CATEGORY_COLOR,
        budget_year: 2026,
        rhythms: null,
    },
];

export const expenseCategories: CategoryRow[] = [
    {
        id: 11,
        name: 'Jedzenie',
        icon: 'shopping-cart',
        color: DEFAULT_CATEGORY_COLOR,
        budget_year: 2026,
        rhythms: everyMonth('running'),
    },
    {
        id: 12,
        name: 'Czynsz',
        icon: 'house',
        color: DEFAULT_CATEGORY_COLOR,
        group: { id: 1, name: 'Mieszkanie' },
        budget_year: 2026,
        rhythms: everyMonth('fixed_charge'),
    },
    {
        id: 13,
        name: 'Prąd',
        icon: 'zap',
        color: DEFAULT_CATEGORY_COLOR,
        group: { id: 1, name: 'Mieszkanie' },
        budget_year: 2026,
        rhythms: everyMonth('even_annual'),
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
    mark_url: '/marks/mbank.jpg',
    current_balance_minor_units: 120_000,
};

export const termDeposits: TermDepositRow[] = [
    {
        id: 1,
        name: 'Lokata 12M',
        bank: 'lokaty_facto',
        mark_url: '/marks/lokaty-facto.svg',
        opening_date: '2026-01-15',
    },
    {
        id: 2,
        name: 'Lokata Revolut',
        bank: 'revolut',
        mark_url: '/marks/revolut.svg',
        opening_date: '2026-01-15',
    },
    {
        id: 3,
        name: 'Lokata ING',
        bank: 'ing',
        mark_url: null,
        opening_date: '2026-01-15',
    },
];

export const noTermDeposits: TermDepositRow[] = [];

export const sampleToSpendWarning: ToSpendWarning = {
    year: 2026,
    month: 10,
    toSpendAfterMinorUnits: -45_000,
};

/** A later month of an Irregular period, pushed below zero by the Collected the row used up. */
export const sampleLaterMonthToSpendWarning: ToSpendWarning = {
    year: 2026,
    month: 11,
    toSpendAfterMinorUnits: -20_000,
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
    selections[9] = {
        ...selections[9],
        selected: true,
        type: 'refund',
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
