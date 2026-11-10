import type {
    MonthPace,
    MonthPlanItem,
    MonthPlanReading,
    MonthSummary,
    MonthYearComparison,
} from '@/types/month-plan';

function item(overrides: Partial<MonthPlanItem>): MonthPlanItem {
    return {
        category_id: 1,
        name: 'Kategoria',
        icon: 'wallet',
        color: '#4f46e5',
        type: 'expense',
        month_item_kind: 'running',
        is_one_off: false,
        is_archived: false,
        plan_minor_units: 0,
        actual_minor_units: 0,
        has_positive_amount: false,
        can_change_kind: true,
        in_plan: true,
        deviation_minor_units: null,
        is_over_plan: false,
        is_settled: false,
        available_limit_minor_units: null,
        is_idle_one_off: false,
        approved_minor_units: null,
        is_join_candidate: false,
        year_comparison: null,
        ...overrides,
    };
}

export const runningExpense = item({
    category_id: 1,
    name: 'Jedzenie',
    plan_minor_units: 200_000,
    actual_minor_units: 80_000,
    has_positive_amount: true,
    deviation_minor_units: 120_000,
    approved_minor_units: 180_000,
    year_comparison: {
        year_minor_units: 1_020_000,
        annual_plan_minor_units: 2_400_000,
        remaining_minor_units: 1_380_000,
        is_over_year: false,
    },
});

export const paidBill = item({
    category_id: 2,
    name: 'Czynsz',
    month_item_kind: 'bill',
    plan_minor_units: 150_000,
    actual_minor_units: 150_000,
    has_positive_amount: true,
    deviation_minor_units: 0,
    is_settled: true,
    approved_minor_units: 150_000,
    year_comparison: {
        year_minor_units: 1_650_000,
        annual_plan_minor_units: 1_800_000,
        remaining_minor_units: 150_000,
        is_over_year: false,
    },
});

export const settledIncome = item({
    category_id: 3,
    name: 'Wynagrodzenie',
    type: 'income',
    month_item_kind: null,
    plan_minor_units: 500_000,
    actual_minor_units: 500_000,
    has_positive_amount: true,
    can_change_kind: false,
    deviation_minor_units: 0,
    is_settled: true,
    approved_minor_units: 450_000,
    year_comparison: {
        year_minor_units: 5_500_000,
        annual_plan_minor_units: 6_000_000,
        remaining_minor_units: 500_000,
        is_over_year: false,
    },
});

export const waitingIncome = item({
    category_id: 10,
    name: 'Premia',
    type: 'income',
    month_item_kind: null,
    plan_minor_units: 100_000,
    has_positive_amount: true,
    can_change_kind: false,
    deviation_minor_units: -100_000,
});

export const idleOneOff = item({
    category_id: 4,
    name: 'Prezent urodzinowy',
    is_one_off: true,
    is_idle_one_off: true,
    plan_minor_units: 0,
    available_limit_minor_units: 30_000,
});

export const archivedExpense = item({
    category_id: 5,
    name: 'Siłownia',
    is_archived: true,
    plan_minor_units: 12_000,
    actual_minor_units: 12_000,
    has_positive_amount: true,
    deviation_minor_units: 0,
    approved_minor_units: 15_000,
    can_change_kind: false,
});

export const outsidePlanExpense = item({
    category_id: 6,
    name: 'Zakupy dodatkowe',
    in_plan: false,
    actual_minor_units: 4_500,
    deviation_minor_units: -4_500,
    is_over_plan: true,
    can_change_kind: false,
});

export const overPlanExpense = item({
    category_id: 7,
    name: 'Paliwo',
    plan_minor_units: 30_000,
    actual_minor_units: 41_000,
    has_positive_amount: true,
    deviation_minor_units: -11_000,
    is_over_plan: true,
    approved_minor_units: 35_000,
    year_comparison: {
        year_minor_units: 372_000,
        annual_plan_minor_units: 360_000,
        remaining_minor_units: -12_000,
        is_over_year: true,
    },
});

export const longNameExpense = item({
    category_id: 8,
    name: 'Bardzo długa nazwa kategorii, która sprawdza zawijanie w wąskiej kolumnie',
    plan_minor_units: 99_900,
    actual_minor_units: 10_000,
    has_positive_amount: true,
    deviation_minor_units: 89_900,
});

export const lockedKindExpense = item({
    category_id: 9,
    name: 'Ubezpieczenie',
    month_item_kind: 'bill',
    plan_minor_units: 25_000,
    has_positive_amount: true,
    can_change_kind: false,
});

/** A list category without a budget line: plan 0, joins the plan only with an amount above 0. */
export const joinCandidateExpense = item({
    category_id: 11,
    name: 'Domeny',
    in_plan: false,
    is_join_candidate: true,
    can_change_kind: false,
    year_comparison: {
        year_minor_units: 0,
        annual_plan_minor_units: 0,
        remaining_minor_units: 0,
        is_over_year: false,
    },
});

export const incomeRows: MonthPlanItem[] = [settledIncome, waitingIncome];

export const expenseRows: MonthPlanItem[] = [
    runningExpense,
    paidBill,
    overPlanExpense,
    longNameExpense,
    lockedKindExpense,
    outsidePlanExpense,
    archivedExpense,
    idleOneOff,
];

export const yearTotals: {
    income: MonthYearComparison;
    expense: MonthYearComparison;
} = {
    income: {
        year_minor_units: 5_500_000,
        annual_plan_minor_units: 6_000_000,
        remaining_minor_units: 500_000,
        is_over_year: false,
    },
    expense: {
        year_minor_units: 2_100_000,
        annual_plan_minor_units: 2_000_000,
        remaining_minor_units: -100_000,
        is_over_year: true,
    },
};

export const paceInNorm: MonthPace = {
    as_of: '2026-11-05',
    elapsed_day: 5,
    days_in_month: 30,
    pool_plan_minor_units: 200_000,
    pool_actual_minor_units: 62_000,
    percent: 31,
    is_ahead: false,
};

export const paceAhead: MonthPace = {
    ...paceInNorm,
    pool_actual_minor_units: 120_000,
    percent: 60,
    is_ahead: true,
};

export const paceNoPlan: MonthPace = {
    ...paceInNorm,
    pool_plan_minor_units: 0,
    pool_actual_minor_units: 0,
    percent: null,
    is_ahead: false,
};

export const pastSummary: MonthSummary = {
    income: {
        approved_minor_units: 500_000,
        final_minor_units: 520_000,
        actual_minor_units: 520_000,
        deviation_minor_units: 0,
    },
    expense: {
        approved_minor_units: 380_000,
        final_minor_units: 390_000,
        actual_minor_units: 412_000,
        deviation_minor_units: 22_000,
    },
    set_aside: {
        approved_minor_units: 50_000,
        final_minor_units: 50_000,
        real_minor_units: 40_000,
        difference_minor_units: -10_000,
    },
    year_draw_minor_units: 0,
};

/** As the server sends it in April: a past month without a plan, two summaries, the approved current month, proposals ahead. */
export const allMonthStates: MonthPlanReading['month_states'] = [
    null,
    'summary',
    'summary',
    'approved',
    'proposal',
    'proposal',
    'proposal',
    'proposal',
    'proposal',
    'proposal',
    'proposal',
    'proposal',
];

export function buildMonthPlanReading(
    overrides: Partial<MonthPlanReading> = {},
): MonthPlanReading {
    return {
        state: 'proposal',
        approved_at: null,
        can_approve: true,
        can_change: false,
        to_spend_minor_units: 120_000,
        to_spend_income_minor_units: 500_000,
        to_spend_expense_minor_units: 380_000,
        month_states: allMonthStates,
        set_aside_minor_units: 0,
        spendable_liquidity_minor_units: 500_000,
        month_pace: null,
        income_items: incomeRows,
        expense_items: expenseRows,
        year_totals: null,
        summary: null,
        ...overrides,
    };
}

/** Current approved month: pace within the norm, year lines, changed amounts. */
export const approvedReading = buildMonthPlanReading({
    state: 'approved',
    approved_at: '2026-11-01T08:00:00Z',
    can_approve: false,
    can_change: true,
    month_pace: paceInNorm,
    year_totals: yearTotals,
    set_aside_minor_units: 50_000,
});

/** Approved month where spending runs ahead of the elapsed days. */
export const approvedAheadReading = buildMonthPlanReading({
    ...approvedReading,
    month_pace: paceAhead,
});

/** Approved month without a running plan: the pace has no percent. */
export const approvedNoPaceReading = buildMonthPlanReading({
    ...approvedReading,
    month_pace: paceNoPlan,
});

/** Future proposal: no pace yet. */
export const futureProposalReading = buildMonthPlanReading({
    month_pace: null,
    year_totals: yearTotals,
});

/** Past approved month with a summary. */
export const pastReading = buildMonthPlanReading({
    state: 'approved',
    approved_at: '2026-09-01T08:00:00Z',
    can_approve: false,
    can_change: false,
    month_pace: null,
    year_totals: yearTotals,
    set_aside_minor_units: 50_000,
    summary: pastSummary,
});

/** Negative To Spend, drawing past the spendable liquidity. */
export const negativeDrawReading = buildMonthPlanReading({
    state: 'approved',
    can_approve: false,
    can_change: true,
    to_spend_minor_units: -45_000,
    to_spend_income_minor_units: 500_000,
    to_spend_expense_minor_units: 545_000,
    set_aside_minor_units: -600_000,
    spendable_liquidity_minor_units: 500_000,
    month_pace: paceInNorm,
});
