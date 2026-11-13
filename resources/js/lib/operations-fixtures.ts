import type { CapitalMovementRowData } from '@/components/CapitalMovementRow.vue';
import type { FinancialOperationRowData } from '@/components/FinancialOperationRow.vue';
import type { OwnTransferRowData } from '@/components/OwnTransferRow.vue';
import type { OperationsListSection } from '@/components/OperationsActiveFilterChips.vue';
import type {
    OperationsFilterAccount,
    OperationsFilterCategory,
    OperationsFilterTermDeposit,
    OperationsListFilters,
} from '@/components/OperationsFilters.vue';
import type { PaginatedCollection } from '@/components/PaginationLinks.vue';
import { EMPTY_OPERATIONS_FILTERS } from '@/lib/operations-list-query';

const mainAccount = {
    account_name: 'Konto osobiste',
    account_type: 'bank',
    account_bank: 'pko_bp',
    account_mark_url: '/marks/pko-bp.svg',
} as const;

const savingsAccount = {
    account_name: 'Konto oszczędnościowe',
    account_type: 'bank',
    account_bank: 'mbank',
    account_mark_url: '/marks/mbank.jpg',
} as const;

const cashAccount = {
    account_name: 'Gotówka',
    account_type: 'cash',
    account_bank: null,
    account_mark_url: null,
} as const;

const bankWithoutLogoAccount = {
    account_name: 'Konto w ING',
    account_type: 'bank',
    account_bank: 'ing',
    account_mark_url: null,
} as const;

const otherBankArchivedAccount = {
    account_name:
        'Wspólne konto rodzinne na wydatki bieżące, rachunki i opłaty za przedszkole',
    account_type: 'bank',
    account_bank: 'other',
    account_mark_url: null,
} as const;

const baseOperation = {
    counterparty: null,
    raw_statement_description: null,
    account_id: 1,
    category_id: 1,
    category_icon: 'shopping-cart',
    category_color: '#0d9488',
    category_archived: false,
    sheet_operation: false,
    ...mainAccount,
} as const;

export const incomeOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 1,
    type: 'income',
    operation_date: '2026-11-04',
    amount_minor_units: 850000,
    description: 'Wynagrodzenie',
    category_label: 'Wynagrodzenie',
};

export const expenseOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 3,
    type: 'expense',
    operation_date: '2026-11-04',
    amount_minor_units: 12345,
    description: 'Zakupy spożywcze',
    category_label: 'Jedzenie',
};

export const refundOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 2,
    type: 'expense',
    operation_date: '2026-11-04',
    amount_minor_units: -4500,
    description: 'Zwrot zamówienia',
    category_label: 'Zakupy',
};

export const bankDescriptionOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 4,
    type: 'expense',
    operation_date: '2026-11-03',
    amount_minor_units: 6990,
    description: 'Abonament telefoniczny',
    category_label: 'Rachunki',
    raw_statement_description:
        'PLATNOSC KARTA 30.10.2026 OPERATOR TELEKOMUNIKACYJNY SA WARSZAWA PL',
};

export const counterpartyOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 5,
    type: 'expense',
    operation_date: '2026-11-03',
    amount_minor_units: 24999,
    description: 'Faktura październik',
    counterparty: 'Sklep Techniczny Sp. z o.o.',
    category_label: 'Dom',
};

export const cashOperation: FinancialOperationRowData = {
    ...baseOperation,
    ...cashAccount,
    id: 6,
    account_id: 3,
    type: 'expense',
    operation_date: '2026-11-03',
    amount_minor_units: 1850,
    description: 'Bułki i kawa',
    category_label: 'Jedzenie',
};

export const bankWithoutLogoOperation: FinancialOperationRowData = {
    ...baseOperation,
    ...bankWithoutLogoAccount,
    id: 7,
    account_id: 4,
    type: 'expense',
    operation_date: '2026-11-03',
    amount_minor_units: 4200,
    description: 'Opłata za konto',
    category_label: 'Rachunki',
};

export const otherBankArchivedOperation: FinancialOperationRowData = {
    ...baseOperation,
    ...otherBankArchivedAccount,
    id: 8,
    account_id: 5,
    type: 'expense',
    operation_date: '2026-11-03',
    amount_minor_units: 1000,
    description: 'Opłata za przedszkole',
    category_label: 'Rachunki',
};

/** Operacja z wczytanego arkusza: menu ma „Zmień kategorię” zamiast „Edytuj” (ADR-0089). */
export const sheetOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 9,
    account_id: 6,
    account_name: 'Arkusz 2025',
    account_type: 'bank',
    account_bank: 'other',
    account_mark_url: null,
    type: 'expense',
    operation_date: '2025-03-31',
    amount_minor_units: 48210,
    description: 'Artykuły spożywcze',
    category_label: 'Jedzenie',
    sheet_operation: true,
};

/** Dzień z dodatnią sumą (dochód, zwrot, wydatek), potem dzień z ujemną. */
export const financialOperationFixtures: FinancialOperationRowData[] = [
    incomeOperation,
    refundOperation,
    expenseOperation,
    bankDescriptionOperation,
    counterpartyOperation,
    cashOperation,
    bankWithoutLogoOperation,
    otherBankArchivedOperation,
    sheetOperation,
];

/** Sumy dnia liczy serwer; tu: 8500,00 + 45,00 − 123,45 oraz −69,90 − 249,99. */
export const operationDaySumsFixture: Record<string, number> = {
    '2026-11-04': 842155,
    '2026-11-03': -31989,
};

export const ownTransferFixture: OwnTransferRowData = {
    id: 1,
    transfer_date: '2026-10-28',
    amount_minor_units: 100000,
    description: 'Odłożenie oszczędności',
    raw_statement_descriptions: [],
    source_account_id: 1,
    destination_account_id: 2,
    source_account_name: mainAccount.account_name,
    source_account_type: mainAccount.account_type,
    source_account_bank: mainAccount.account_bank,
    source_account_mark_url: mainAccount.account_mark_url,
    destination_account_name: savingsAccount.account_name,
    destination_account_type: savingsAccount.account_type,
    destination_account_bank: savingsAccount.account_bank,
    destination_account_mark_url: savingsAccount.account_mark_url,
};

export const ownTransferFixtures: OwnTransferRowData[] = [ownTransferFixture];

export const capitalMovementToDeposit: CapitalMovementRowData = {
    id: 1,
    movement_date: '2026-10-27',
    amount_minor_units: 500000,
    description: 'Założenie lokaty',
    direction: 'to_term_deposit',
    account_id: 1,
    term_deposit_id: 1,
    term_deposit_name: 'Lokata 12M',
    term_deposit_bank: 'lokaty_facto',
    term_deposit_mark_url: '/marks/lokaty-facto.svg',
    ...mainAccount,
};

export const capitalMovementFromDeposit: CapitalMovementRowData = {
    ...capitalMovementToDeposit,
    id: 2,
    movement_date: '2026-10-26',
    amount_minor_units: 250000,
    description: 'Wypłata z lokaty',
    direction: 'from_term_deposit',
};

export const capitalMovementFixtures: CapitalMovementRowData[] = [
    capitalMovementToDeposit,
    capitalMovementFromDeposit,
];

export function paginate<T>(
    data: T[],
    currentPage = 1,
    lastPage = 3,
): PaginatedCollection<T> {
    const pages = Array.from({ length: lastPage }, (_, index) => index + 1);

    return {
        data,
        current_page: currentPage,
        last_page: lastPage,
        per_page: data.length,
        total: data.length * lastPage,
        from: 1,
        to: data.length,
        links: [
            {
                url: currentPage > 1 ? '#poprzednia' : null,
                label: '&laquo; Previous',
                active: false,
            },
            ...pages.map((page) => ({
                url: `#strona-${page}`,
                label: String(page),
                active: page === currentPage,
            })),
            {
                url: currentPage < lastPage ? '#nastepna' : null,
                label: 'Next &raquo;',
                active: false,
            },
        ],
    };
}

export const emptyPaginator = paginate<never>([], 1, 1);

export const searchFiltersFixture: OperationsListFilters = {
    ...EMPTY_OPERATIONS_FILTERS,
    q: 'zakupy',
};

const dom = { id: 1, name: 'Dom' };
const transport = { id: 2, name: 'Transport' };

/** In the order of the Categories list: Ż before A, groups, one without a group, one archived. */
export const filterCategoriesFixture: OperationsFilterCategory[] = [
    {
        id: 1,
        name: 'Wynagrodzenie',
        type: 'income',
        icon: 'banknote',
        color: '#0d9488',
        group: null,
        is_archived: false,
    },
    {
        id: 2,
        name: 'Żywność',
        type: 'expense',
        icon: 'utensils',
        color: '#0d9488',
        group: null,
        is_archived: false,
    },
    {
        id: 3,
        name: 'Czynsz',
        type: 'expense',
        icon: 'home',
        color: '#e03131',
        group: dom,
        is_archived: false,
    },
    {
        id: 4,
        name: 'Media',
        type: 'expense',
        icon: 'zap',
        color: '#f59f00',
        group: dom,
        is_archived: false,
    },
    {
        id: 5,
        name: 'Auto',
        type: 'expense',
        icon: 'car',
        color: '#1971c2',
        group: transport,
        is_archived: false,
    },
    {
        id: 6,
        name: 'Komunikacja miejska',
        type: 'expense',
        icon: 'bus',
        color: '#7048e8',
        group: transport,
        is_archived: true,
    },
];

export const filterAccountsFixture: OperationsFilterAccount[] = [
    {
        id: 1,
        name: mainAccount.account_name,
        type: mainAccount.account_type,
        bank: mainAccount.account_bank,
        mark_url: mainAccount.account_mark_url,
        is_archived: false,
    },
    {
        id: 2,
        name: savingsAccount.account_name,
        type: savingsAccount.account_type,
        bank: savingsAccount.account_bank,
        mark_url: savingsAccount.account_mark_url,
        is_archived: false,
    },
    {
        id: 3,
        name: cashAccount.account_name,
        type: cashAccount.account_type,
        bank: cashAccount.account_bank,
        mark_url: cashAccount.account_mark_url,
        is_archived: false,
    },
    {
        id: 4,
        name: bankWithoutLogoAccount.account_name,
        type: bankWithoutLogoAccount.account_type,
        bank: bankWithoutLogoAccount.account_bank,
        mark_url: bankWithoutLogoAccount.account_mark_url,
        is_archived: true,
    },
    {
        id: 5,
        name: otherBankArchivedAccount.account_name,
        type: otherBankArchivedAccount.account_type,
        bank: otherBankArchivedAccount.account_bank,
        mark_url: otherBankArchivedAccount.account_mark_url,
        is_archived: true,
    },
];

export const accountFilterFixture: OperationsListFilters = {
    ...EMPTY_OPERATIONS_FILTERS,
    account_id: 1,
};

/** The filter on the archived Category: the picker shows its suffix, the chip does not. */
export const categoryFilterFixture: OperationsListFilters = {
    ...EMPTY_OPERATIONS_FILTERS,
    category_id: 6,
};

export const filterTermDepositsFixture: OperationsFilterTermDeposit[] = [
    {
        id: 1,
        name: 'Lokata 12M',
        bank: 'lokaty_facto',
        mark_url: '/marks/lokaty-facto.svg',
        is_archived: false,
    },
    {
        id: 2,
        name: 'Lokata Revolut',
        bank: 'revolut',
        mark_url: '/marks/revolut.svg',
        is_archived: false,
    },
    {
        id: 3,
        name: 'Lokata ING',
        bank: 'ing',
        mark_url: null,
        is_archived: false,
    },
    {
        id: 4,
        name: 'Lokata 2024',
        bank: 'lokaty_facto',
        mark_url: '/marks/lokaty-facto.svg',
        is_archived: true,
    },
];

export const sectionTotalsFixture: Record<OperationsListSection, number> = {
    financial_operations: 15,
    own_transfers: 3,
    capital_movements: 6,
};
