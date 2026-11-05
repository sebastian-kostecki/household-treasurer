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

const mainAccount = {
    account_name: 'Konto osobiste',
    account_type: 'bank',
    account_bank: 'ing',
    account_mark_url: null,
} as const;

const savingsAccount = {
    account_name: 'Konto oszczędnościowe',
    account_type: 'bank',
    account_bank: 'mbank',
    account_mark_url: null,
} as const;

const baseOperation = {
    counterparty: null,
    raw_statement_description: null,
    account_id: 1,
    category_id: 1,
    category_icon: 'shopping-cart',
    category_color: '#0d9488',
    ...mainAccount,
} as const;

export const incomeOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 1,
    type: 'income',
    operation_date: '2026-11-04',
    amount_minor_units: 850000,
    description: 'Wynagrodzenie',
    category_name: 'Wynagrodzenie',
};

export const expenseOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 3,
    type: 'expense',
    operation_date: '2026-11-04',
    amount_minor_units: 12345,
    description: 'Zakupy spożywcze',
    category_name: 'Jedzenie',
};

export const refundOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 2,
    type: 'expense',
    operation_date: '2026-11-04',
    amount_minor_units: -4500,
    description: 'Zwrot zamówienia',
    category_name: 'Zakupy',
};

export const bankDescriptionOperation: FinancialOperationRowData = {
    ...baseOperation,
    id: 4,
    type: 'expense',
    operation_date: '2026-11-03',
    amount_minor_units: 6990,
    description: 'Abonament telefoniczny',
    category_name: 'Rachunki',
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
    category_name: 'Dom',
};

/** Dzień z dodatnią sumą (dochód, zwrot, wydatek), potem dzień z ujemną. */
export const financialOperationFixtures: FinancialOperationRowData[] = [
    incomeOperation,
    refundOperation,
    expenseOperation,
    bankDescriptionOperation,
    counterpartyOperation,
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
    source_account_mark_url: null,
    destination_account_name: savingsAccount.account_name,
    destination_account_type: savingsAccount.account_type,
    destination_account_bank: savingsAccount.account_bank,
    destination_account_mark_url: null,
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

export const noFiltersFixture: OperationsListFilters = {
    q: null,
    year: null,
    category_name: null,
    category_type: null,
    account_id: null,
    term_deposit_id: null,
    date_from: null,
    date_to: null,
};

export const searchFiltersFixture: OperationsListFilters = {
    ...noFiltersFixture,
    q: 'zakupy',
};

export const filterCategoriesFixture: OperationsFilterCategory[] = [
    {
        name: 'Wynagrodzenie',
        type: 'income',
        icon: 'banknote',
        color: '#0d9488',
    },
    { name: 'Jedzenie', type: 'expense', icon: 'utensils', color: '#0d9488' },
];

export const filterAccountsFixture: OperationsFilterAccount[] = [
    { id: 1, name: mainAccount.account_name, is_archived: false },
    { id: 2, name: savingsAccount.account_name, is_archived: false },
];

export const filterTermDepositsFixture: OperationsFilterTermDeposit[] = [
    { id: 1, name: 'Lokata 12M' },
];

export const sectionTotalsFixture: Record<OperationsListSection, number> = {
    financial_operations: 15,
    own_transfers: 3,
    capital_movements: 6,
};
