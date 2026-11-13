import type { CapitalMovementFormAccount } from '@/components/CapitalMovementFormDialog.vue';
import type {
    CapitalMovementFormMovement,
    CapitalMovementFormTermDeposit,
} from '@/components/CapitalMovementFormDialog.vue';
import type {
    ExpenseFormAccount,
    ExpenseFormCategory,
    ExpenseFormIncomeCategory,
    ExpenseFormOperation,
} from '@/components/ExpenseFormDialog.vue';
import type {
    IncomeFormAccount,
    IncomeFormCategory,
    IncomeFormExpenseCategory,
    IncomeFormOperation,
} from '@/components/IncomeFormDialog.vue';
import type { OperationCategoryDialogOperation } from '@/components/OperationCategoryDialog.vue';
import type {
    OwnTransferFormAccount,
    OwnTransferFormTransfer,
} from '@/components/OwnTransferFormDialog.vue';
import type { EntryToSpendInputs } from '@/lib/entry-to-spend-warning';
import type { Rhythm } from '@/types/rhythm';
import type { HandEntryMonthRemainingInputs } from '@/lib/hand-entry-month-remaining-preview';

const now = new Date();
const year = now.getFullYear();
const month = now.getMonth() + 1;

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

const today = `${year}-${pad(month)}-${pad(now.getDate())}`;
const monthStart = `${year}-${pad(month)}-01`;
const openingDate = `${year - 1}-01-01`;
const previousMonth = month === 1 ? 12 : month - 1;
const previousMonthYear = month === 1 ? year - 1 : year;

/** A day of the month before the current one: the later month with a plan is the current one. */
export const dialogPreviousMonthDate = `${previousMonthYear}-${pad(previousMonth)}-10`;

export const dialogAccounts: ExpenseFormAccount[] &
    IncomeFormAccount[] &
    OwnTransferFormAccount[] &
    CapitalMovementFormAccount[] = [
    {
        id: 1,
        name: 'Konto osobiste',
        type: 'bank',
        bank: 'ing',
        mark_url: null,
        opening_date: openingDate,
        current_balance_minor_units: 450000,
    },
    {
        id: 2,
        name: 'Konto oszczędnościowe',
        type: 'bank',
        bank: 'mbank',
        mark_url: null,
        opening_date: openingDate,
        current_balance_minor_units: 4500,
    },
];

export const dialogExpenseCategories: ExpenseFormCategory[] &
    IncomeFormExpenseCategory[] = [
    {
        id: 1,
        name: 'Jedzenie',
        icon: 'shopping-cart',
        color: 'blue',
        budget_year: year,
        planned_amount_minor_units: 120000,
        execution_minor_units: 90000,
    },
    {
        id: 2,
        name: 'Rozrywka',
        icon: 'ticket',
        color: 'purple',
        budget_year: year,
        planned_amount_minor_units: 80000,
        execution_minor_units: 76000,
    },
    {
        id: 3,
        name: 'Czynsz',
        icon: 'home',
        color: 'orange',
        group: { id: 1, name: 'Mieszkanie' },
        budget_year: year,
        planned_amount_minor_units: 300000,
        execution_minor_units: 300000,
    },
    {
        id: 4,
        name: 'Prąd',
        icon: 'zap',
        color: 'yellow',
        group: { id: 1, name: 'Mieszkanie' },
        budget_year: year,
        planned_amount_minor_units: 25000,
        execution_minor_units: 12000,
    },
    {
        id: 5,
        name: 'Rekolekcje',
        icon: 'tag',
        color: 'teal',
        budget_year: year,
        planned_amount_minor_units: 120000,
        execution_minor_units: 21000,
    },
];

export const dialogIncomeCategories: ExpenseFormIncomeCategory[] &
    IncomeFormCategory[] = [
    {
        id: 10,
        name: 'Wynagrodzenie',
        icon: 'wallet',
        color: 'green',
        budget_year: year,
    },
    {
        id: 11,
        name: 'Odsetki',
        icon: 'landmark',
        color: 'teal',
        budget_year: year,
    },
];

export const dialogTermDeposits: CapitalMovementFormTermDeposit[] = [
    {
        id: 1,
        name: 'Lokata 12M',
        opening_date: openingDate,
        current_balance_minor_units: 1000000,
    },
    {
        id: 2,
        name: 'Lokata awaryjna',
        opening_date: openingDate,
        current_balance_minor_units: 25000,
    },
];

function allMonths(rhythm: Rhythm): Record<number, Rhythm> {
    return Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => [index + 1, rhythm]),
    );
}

/** Plan of the current month: To Spend is 50,00 zł. */
export const dialogEntryToSpend: EntryToSpendInputs = {
    budgets: [
        {
            year,
            categories: [
                { id: 1, type: 'expense', rhythms: allMonths('running') },
                { id: 2, type: 'expense', rhythms: allMonths('running') },
                { id: 3, type: 'expense', rhythms: allMonths('fixed_charge') },
                { id: 5, type: 'expense', rhythms: allMonths('irregular') },
                { id: 10, type: 'income', rhythms: null },
            ],
            months: [
                {
                    month,
                    set_aside_minor_units: 0,
                    items: [
                        {
                            category_id: 10,
                            plan_minor_units: 505000,
                            actual_minor_units: 505000,
                            positive_amount_count: 1,
                        },
                        {
                            category_id: 1,
                            plan_minor_units: 120000,
                            actual_minor_units: 90000,
                            positive_amount_count: 5,
                        },
                        {
                            category_id: 2,
                            plan_minor_units: 80000,
                            actual_minor_units: 76000,
                            positive_amount_count: 3,
                        },
                        {
                            category_id: 3,
                            plan_minor_units: 300000,
                            actual_minor_units: 300000,
                            positive_amount_count: 1,
                        },
                        // Nieregularna: rachunek 200,00 zł pokryty zebranym (290,00 zł na początek miesiąca).
                        {
                            category_id: 5,
                            plan_minor_units: 0,
                            actual_minor_units: 20000,
                            positive_amount_count: 1,
                        },
                    ],
                },
            ],
        },
    ],
    // Okres "Rekolekcji": 300,00 zł zebrane na początek poprzedniego miesiąca.
    // Rachunek 200,00 zł z poprzedniego miesiąca zużywa zebrane tak, że bieżący
    // miesiąc schodzi poniżej zera (stan z ostrzeżeniem o późniejszym miesiącu).
    irregular_periods: [
        {
            category_id: 5,
            start_year: previousMonthYear,
            start_month: previousMonth,
            opening_minor_units: 30000,
            months: [
                {
                    year: previousMonthYear,
                    month: previousMonth,
                    rate_minor_units: 0,
                    actual_minor_units: 1000,
                },
                {
                    year,
                    month,
                    rate_minor_units: 0,
                    actual_minor_units: 20000,
                },
            ],
        },
    ],
};

/** A budget without a plan for the current month (draft). */
export const dialogHandEntryMonthRemaining: HandEntryMonthRemainingInputs = {
    today,
    budgets: [
        {
            year,
            is_draft: false,
            categories: [
                {
                    id: 1,
                    rhythms: allMonths('running'),
                    planned_amount_minor_units: 120000,
                    monthly_execution_minor_units: {},
                },
            ],
        },
    ],
};

export const dialogExpenseOperation: ExpenseFormOperation = {
    id: 1,
    operation_date: today,
    amount_minor_units: 4500,
    description: 'Zakupy tygodniowe',
    counterparty: 'Biedronka',
    raw_statement_description: 'BIEDRONKA 1234 WARSZAWA',
    account_id: 1,
    category_id: 1,
};

export const dialogRefundOperation: ExpenseFormOperation = {
    id: 2,
    operation_date: today,
    amount_minor_units: -1500,
    description: 'Zwrot towaru',
    counterparty: 'Biedronka',
    raw_statement_description: null,
    account_id: 1,
    category_id: 1,
};

export const dialogIncomeOperation: IncomeFormOperation = {
    id: 3,
    operation_date: monthStart,
    amount_minor_units: 505000,
    description: 'Wynagrodzenie',
    counterparty: 'Pracodawca',
    raw_statement_description: 'PRZELEW WYNAGRODZENIE',
    account_id: 1,
    category_id: 10,
};

/** An operation of a Sheet Account: only its Category changes (ADR-0089). */
export const dialogSheetOperation: OperationCategoryDialogOperation = {
    id: 6,
    type: 'expense',
    operation_date: `${year}-01-31`,
    amount_minor_units: 48210,
    description: 'Artykuły spożywcze',
    account_name: `Arkusz ${year}`,
    category_id: 2,
    category_label: 'Rozrywka',
    category_icon: 'ticket',
    category_color: 'purple',
    category_archived: false,
};

/** The same operation on the archived Category the load created, which is not on the year's list. */
export const dialogSheetOperationArchivedCategory: OperationCategoryDialogOperation =
    {
        ...dialogSheetOperation,
        category_id: 99,
        category_label: 'Kosmetyki',
        category_icon: 'sparkles',
        category_color: 'pink',
        category_archived: true,
    };

export const dialogOwnTransfer: OwnTransferFormTransfer = {
    id: 4,
    transfer_date: today,
    amount_minor_units: 20000,
    description: 'Odłożone na oszczędności',
    raw_statement_descriptions: [
        'PRZELEW WLASNY NA KONTO OSZCZEDNOSCIOWE',
        'PRZELEW PRZYCHODZACY OD KONTO OSOBISTE',
    ],
    source_account_id: 1,
    destination_account_id: 2,
};

export const dialogCapitalMovement: CapitalMovementFormMovement = {
    id: 5,
    movement_date: today,
    amount_minor_units: 100000,
    description: 'Wpłata na lokatę',
    direction: 'to_term_deposit',
    account_id: 1,
    term_deposit_id: 1,
};
