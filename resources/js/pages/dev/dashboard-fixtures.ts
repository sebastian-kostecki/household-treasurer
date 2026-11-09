import { DEFAULT_CATEGORY_COLOR } from '../../lib/category-appearance';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import type { DashboardPageProps } from '@/types/dashboard';

export type DashboardScenario = {
    key: string;
    title: string;
    props: DashboardPageProps;
};

const zeroTotals = {
    initial_plan_minor_units: 0,
    current_plan_minor_units: 0,
    execution_minor_units: 0,
    remaining_minor_units: 0,
    percent_complete: null,
};

function category(
    id: number,
    name: string,
    icon: string,
    color: string,
    plan: number,
    execution: number,
    overrides: Partial<DashboardCategory> = {},
): DashboardCategory {
    return {
        id,
        name,
        icon,
        color,
        initial_plan_minor_units: plan,
        current_plan_minor_units: plan,
        execution_minor_units: execution,
        remaining_minor_units: plan - execution,
        percent_complete:
            plan === 0 ? null : Math.round((execution / plan) * 100),
        is_over_limit: execution > plan,
        is_archived: false,
        ...overrides,
    };
}

const incomeCategories = [
    category(
        1,
        'Wynagrodzenie',
        'wallet',
        DEFAULT_CATEGORY_COLOR,
        1200000,
        600000,
    ),
    category(2, 'Premia', 'coins', DEFAULT_CATEGORY_COLOR, 200000, 0),
];

const expenseCategories = [
    category(
        3,
        'Zakupy spożywcze',
        'shopping-cart',
        DEFAULT_CATEGORY_COLOR,
        180000,
        90000,
        {
            is_paced: true,
        },
    ),
    category(4, 'Dom', 'home', DEFAULT_CATEGORY_COLOR, 250000, 125000),
    category(5, 'Transport', 'car', DEFAULT_CATEGORY_COLOR, 60000, 25000, {
        is_paced: true,
    }),
];

const defaultProps: DashboardPageProps = {
    accounts: [
        {
            id: 1,
            name: 'Konto osobiste',
            type: 'bank',
            bank: 'mbank',
            mark_url: '/marks/mbank.jpg',
            current_balance_minor_units: 845050,
            is_negative: false,
            is_archived: false,
        },
        {
            id: 2,
            name: 'Gotówka',
            type: 'cash',
            bank: null,
            mark_url: null,
            current_balance_minor_units: 32000,
            is_negative: false,
            is_archived: false,
        },
    ],
    termDeposits: [
        {
            id: 1,
            name: 'Lokata roczna',
            bank: 'pko_bp',
            mark_url: '/marks/pko-bp.svg',
            end_date: '2026-12-31',
            current_balance_minor_units: 1000000,
            is_negative: false,
            is_archived: false,
        },
    ],
    spendable_liquidity_minor_units: 877050,
    total_funds_minor_units: 1877050,
    budget_year: 2026,
    budget: { year: 2026, phase: 'execution' },
    incomeCategories,
    expenseCategories,
    incomeTotals: {
        initial_plan_minor_units: 1400000,
        current_plan_minor_units: 1400000,
        execution_minor_units: 600000,
        remaining_minor_units: 800000,
        percent_complete: 43,
    },
    expenseTotals: {
        initial_plan_minor_units: 490000,
        current_plan_minor_units: 490000,
        execution_minor_units: 240000,
        remaining_minor_units: 250000,
        percent_complete: 49,
    },
    year_elapsed_as_of: '2026-09-23',
    year_elapsed_percent: 73,
    dashboardNotices: [
        {
            severity: 'exceeded',
            kind: 'month_to_spend_negative',
            month: 9,
            amount_minor_units: -12000,
        },
        {
            severity: 'warning',
            kind: 'month_plan_not_approved',
            month: 10,
        },
    ],
    month_to_spend: { state: 'approved', to_spend_minor_units: 154000 },
    month_pace: {
        as_of: '2026-09-23',
        elapsed_day: 23,
        days_in_month: 30,
        pool_plan_minor_units: 400000,
        pool_actual_minor_units: 240000,
        percent: 60,
        is_ahead: false,
    },
    current_month: 9,
    planSummary: {
        income: {
            initial_plan_minor_units: 1400000,
            current_plan_minor_units: 1400000,
            execution_minor_units: 600000,
            remaining_minor_units: 800000,
            percent_complete: 43,
        },
        expense: {
            initial_plan_minor_units: 490000,
            current_plan_minor_units: 490000,
            execution_minor_units: 240000,
            remaining_minor_units: 250000,
            percent_complete: 49,
        },
        planned_balance_minor_units: 910000,
    },
};

const zeroAmountsProps: DashboardPageProps = {
    ...defaultProps,
    accounts: [
        {
            ...defaultProps.accounts[0],
            current_balance_minor_units: 0,
        },
    ],
    termDeposits: [],
    spendable_liquidity_minor_units: 0,
    total_funds_minor_units: 0,
    dashboardNotices: [],
    month_to_spend: { state: 'proposal', to_spend_minor_units: 0 },
    month_pace: {
        as_of: '2026-09-23',
        elapsed_day: 23,
        days_in_month: 30,
        pool_plan_minor_units: 0,
        pool_actual_minor_units: 0,
        percent: null,
        is_ahead: false,
    },
    planSummary: {
        income: zeroTotals,
        expense: zeroTotals,
        planned_balance_minor_units: 0,
    },
    incomeCategories: [
        category(1, 'Wynagrodzenie', 'wallet', DEFAULT_CATEGORY_COLOR, 0, 0),
    ],
    expenseCategories: [
        category(
            3,
            'Zakupy spożywcze',
            'shopping-cart',
            DEFAULT_CATEGORY_COLOR,
            0,
            0,
        ),
    ],
    incomeTotals: zeroTotals,
    expenseTotals: zeroTotals,
};

const negativeProps: DashboardPageProps = {
    ...defaultProps,
    accounts: [
        {
            ...defaultProps.accounts[0],
            current_balance_minor_units: -45000,
            is_negative: true,
        },
    ],
    termDeposits: [],
    spendable_liquidity_minor_units: -45000,
    total_funds_minor_units: -45000,
    month_to_spend: { state: 'approved', to_spend_minor_units: -23000 },
    month_pace: {
        as_of: '2026-09-23',
        elapsed_day: 23,
        days_in_month: 30,
        pool_plan_minor_units: 400000,
        pool_actual_minor_units: 423000,
        percent: 106,
        is_ahead: true,
    },
    expenseCategories: [
        category(
            3,
            'Zakupy spożywcze',
            'shopping-cart',
            DEFAULT_CATEGORY_COLOR,
            180000,
            230000,
            {
                is_paced: true,
            },
        ),
        expenseCategories[1],
    ],
    expenseTotals: {
        initial_plan_minor_units: 430000,
        current_plan_minor_units: 430000,
        execution_minor_units: 355000,
        remaining_minor_units: 75000,
        percent_complete: 83,
    },
    planSummary: {
        ...defaultProps.planSummary!,
        planned_balance_minor_units: -50000,
    },
    dashboardNotices: [
        {
            severity: 'exceeded',
            kind: 'negative_account',
            id: 1,
            name: 'Konto osobiste',
            type: 'bank',
            bank: 'mbank',
            mark_url: '/marks/mbank.jpg',
            amount_minor_units: -45000,
        },
        {
            severity: 'exceeded',
            kind: 'month_to_spend_negative',
            month: 9,
            amount_minor_units: -23000,
        },
    ],
};

const emptyNewUserProps: DashboardPageProps = {
    accounts: [],
    termDeposits: [],
    spendable_liquidity_minor_units: 0,
    total_funds_minor_units: 0,
    budget_year: 2026,
    budget: null,
    incomeCategories: [],
    expenseCategories: [],
    incomeTotals: zeroTotals,
    expenseTotals: zeroTotals,
    year_elapsed_as_of: null,
    year_elapsed_percent: null,
    dashboardNotices: [],
    month_to_spend: null,
    month_pace: null,
    current_month: null,
    planSummary: null,
};

const noBudgetProps: DashboardPageProps = {
    ...emptyNewUserProps,
    accounts: defaultProps.accounts,
    termDeposits: defaultProps.termDeposits,
    spendable_liquidity_minor_units:
        defaultProps.spendable_liquidity_minor_units,
    total_funds_minor_units: defaultProps.total_funds_minor_units,
};

const draftProps: DashboardPageProps = {
    ...noBudgetProps,
    budget: { year: 2026, phase: 'draft' },
    planSummary: defaultProps.planSummary,
};

const noCategoriesProps: DashboardPageProps = {
    ...defaultProps,
    incomeCategories: [],
    expenseCategories: [],
    incomeTotals: zeroTotals,
    expenseTotals: zeroTotals,
    dashboardNotices: [],
    planSummary: {
        income: zeroTotals,
        expense: zeroTotals,
        planned_balance_minor_units: 0,
    },
};

function totalsOf(categories: DashboardCategory[]): DashboardTypeTotals {
    const plan = categories.reduce(
        (sum, c) => sum + c.current_plan_minor_units,
        0,
    );
    const execution = categories.reduce(
        (sum, c) => sum + c.execution_minor_units,
        0,
    );

    return {
        initial_plan_minor_units: plan,
        current_plan_minor_units: plan,
        execution_minor_units: execution,
        remaining_minor_units: plan - execution,
        percent_complete:
            plan === 0 ? null : Math.round((execution / plan) * 100),
    };
}

function withCategories(
    income: DashboardCategory[],
    expense: DashboardCategory[],
    overrides: Partial<DashboardPageProps> = {},
): DashboardPageProps {
    const incomeTotals = totalsOf(income);
    const expenseTotals = totalsOf(expense);

    return {
        ...defaultProps,
        incomeCategories: income,
        expenseCategories: expense,
        incomeTotals,
        expenseTotals,
        planSummary: {
            income: incomeTotals,
            expense: expenseTotals,
            planned_balance_minor_units:
                incomeTotals.current_plan_minor_units -
                expenseTotals.current_plan_minor_units,
        },
        ...overrides,
    };
}

const manyIcons = ['wallet', 'coins', 'shopping-cart', 'home', 'car'];

function manyCategories(
    startId: number,
    count: number,
    label: string,
    paced: boolean,
): DashboardCategory[] {
    return Array.from({ length: count }, (_, index) =>
        category(
            startId + index,
            `${label} ${index + 1}`,
            manyIcons[index % manyIcons.length],
            DEFAULT_CATEGORY_COLOR,
            100000 + index * 10000,
            40000 + index * 5000,
            { is_paced: paced && index % 2 === 0 },
        ),
    );
}

const manyCategoriesProps = withCategories(
    manyCategories(100, 8, 'Źródło dochodu', false),
    manyCategories(200, 9, 'Kategoria wydatku', true),
);

const incomeOver100Props = withCategories(
    [
        category(
            1,
            'Wynagrodzenie',
            'wallet',
            DEFAULT_CATEGORY_COLOR,
            1200000,
            1260000,
        ),
        category(2, 'Premia', 'coins', DEFAULT_CATEGORY_COLOR, 200000, 200000),
    ],
    expenseCategories,
);

const overrunProps = withCategories(incomeCategories, [
    category(
        3,
        'Zakupy spożywcze',
        'shopping-cart',
        DEFAULT_CATEGORY_COLOR,
        180000,
        230000,
        { is_paced: true },
    ),
    category(4, 'Dom', 'home', DEFAULT_CATEGORY_COLOR, 250000, 300000),
    category(5, 'Transport', 'car', DEFAULT_CATEGORY_COLOR, 60000, 25000, {
        is_paced: true,
    }),
]);

const allNoticeKindsProps: DashboardPageProps = {
    ...defaultProps,
    dashboardNotices: [
        {
            severity: 'exceeded',
            kind: 'negative_account',
            id: 1,
            name: 'Konto osobiste',
            type: 'bank',
            bank: 'mbank',
            mark_url: '/marks/mbank.jpg',
            amount_minor_units: -45000,
        },
        {
            severity: 'exceeded',
            kind: 'negative_term_deposit',
            id: 1,
            name: 'Lokata roczna',
            bank: 'pko_bp',
            mark_url: '/marks/pko-bp.svg',
            amount_minor_units: -10000,
        },
        {
            severity: 'exceeded',
            kind: 'month_to_spend_negative',
            month: 9,
            amount_minor_units: -12000,
        },
        {
            severity: 'exceeded',
            kind: 'month_item_over_plan',
            id: 1,
            name: 'Czynsz',
            icon: 'home',
            color: DEFAULT_CATEGORY_COLOR,
            category_name: 'Dom',
            amount_minor_units: -15000,
        },
        {
            severity: 'exceeded',
            kind: 'one_off_over_limit',
            id: 2,
            name: 'Wakacje',
            icon: 'car',
            color: DEFAULT_CATEGORY_COLOR,
            category_name: 'Transport',
            amount_minor_units: -80000,
        },
        {
            severity: 'warning',
            kind: 'month_plan_not_approved',
            month: 10,
        },
        {
            severity: 'warning',
            kind: 'month_pace_ahead',
            month: 9,
            percent: 106,
            elapsed_day: 23,
            days_in_month: 30,
        },
        {
            severity: 'warning',
            kind: 'month_plan_not_approved',
            month: 11,
        },
    ],
};

const depositsOnlyProps: DashboardPageProps = {
    ...defaultProps,
    accounts: [],
    spendable_liquidity_minor_units: 0,
    total_funds_minor_units: 1000000,
};

const monthStartProps: DashboardPageProps = {
    ...defaultProps,
    month_pace: {
        ...defaultProps.month_pace!,
        as_of: '2026-09-01',
        elapsed_day: 1,
        pool_actual_minor_units: 0,
        percent: 0,
    },
};

const monthEndProps: DashboardPageProps = {
    ...defaultProps,
    month_pace: {
        ...defaultProps.month_pace!,
        as_of: '2026-09-30',
        elapsed_day: 30,
        pool_actual_minor_units: 380000,
        percent: 95,
    },
};

const paceUnknownProps: DashboardPageProps = {
    ...defaultProps,
    month_pace: {
        ...defaultProps.month_pace!,
        pool_plan_minor_units: 0,
        pool_actual_minor_units: 0,
        percent: null,
    },
};

const noMonthPlanProps: DashboardPageProps = {
    ...defaultProps,
    month_to_spend: null,
    month_pace: null,
};

export const dashboardScenarios: DashboardScenario[] = [
    { key: 'default', title: 'default', props: defaultProps },
    { key: 'zero-amounts', title: 'zero-amounts', props: zeroAmountsProps },
    { key: 'negative', title: 'negative', props: negativeProps },
    {
        key: 'empty-new-user',
        title: 'empty-new-user',
        props: emptyNewUserProps,
    },
    { key: 'no-budget', title: 'no-budget', props: noBudgetProps },
    { key: 'draft', title: 'draft', props: draftProps },
    { key: 'no-categories', title: 'no-categories', props: noCategoriesProps },
    {
        key: 'many-categories',
        title: 'many-categories',
        props: manyCategoriesProps,
    },
    {
        key: 'income-over-100',
        title: 'income-over-100',
        props: incomeOver100Props,
    },
    { key: 'overrun', title: 'overrun', props: overrunProps },
    {
        key: 'all-notice-kinds',
        title: 'all-notice-kinds',
        props: allNoticeKindsProps,
    },
    { key: 'deposits-only', title: 'deposits-only', props: depositsOnlyProps },
    { key: 'month-start', title: 'month-start', props: monthStartProps },
    { key: 'month-end', title: 'month-end', props: monthEndProps },
    { key: 'pace-unknown', title: 'pace-unknown', props: paceUnknownProps },
    { key: 'no-month-plan', title: 'no-month-plan', props: noMonthPlanProps },
];
