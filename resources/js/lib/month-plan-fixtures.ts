import type {
    MonthPace,
    MonthPlanGroup,
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
        group_id: null,
        sort_order: 0,
        is_archived: false,
        plan_minor_units: 0,
        actual_minor_units: 0,
        has_positive_amount: false,
        in_plan: true,
        deviation_minor_units: null,
        is_over_plan: false,
        is_settled: false,
        is_idle_even_annual: false,
        rhythm: 'running',
        year_execution_minor_units: 0,
        current_plan_minor_units: 0,
        even_annual_remaining: null,
        approved_minor_units: null,
        is_join_candidate: false,
        collected: null,
        year_comparison: null,
        ...overrides,
    };
}

export const runningExpense = item({
    category_id: 1,
    group_id: 102,
    sort_order: 10,
    name: 'Jedzenie',
    plan_minor_units: 200_000,
    actual_minor_units: 80_000,
    has_positive_amount: true,
    deviation_minor_units: -120_000,
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
    group_id: 101,
    sort_order: 20,
    name: 'Czynsz',
    rhythm: 'fixed_charge',
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
    group_id: 201,
    sort_order: 10,
    name: 'Wynagrodzenie',
    type: 'income',
    rhythm: null,
    plan_minor_units: 500_000,
    actual_minor_units: 500_000,
    has_positive_amount: true,
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
    group_id: 201,
    sort_order: 20,
    name: 'Premia',
    type: 'income',
    rhythm: null,
    plan_minor_units: 100_000,
    has_positive_amount: true,
    deviation_minor_units: -100_000,
});

export const idleEvenAnnual = item({
    category_id: 4,
    sort_order: 150,
    name: 'Prezent urodzinowy',
    rhythm: 'even_annual',
    is_idle_even_annual: true,
    plan_minor_units: 0,
    even_annual_remaining: {
        available_limit_minor_units: 30_000,
        months_left: 2,
    },
});

/** Even Annual in the current month, with a year line and the rest of the year (X > 0). */
export const evenAnnualLeft = item({
    category_id: 12,
    sort_order: 80,
    name: 'Rekolekcje',
    year_execution_minor_units: 100_000,
    current_plan_minor_units: 238_000,
    rhythm: 'even_annual',
    plan_minor_units: 19_833,
    deviation_minor_units: -19_833,
    approved_minor_units: 19_833,
    year_comparison: {
        year_minor_units: 100_000,
        annual_plan_minor_units: 238_000,
        remaining_minor_units: 138_000,
        is_over_year: false,
    },
    even_annual_remaining: {
        available_limit_minor_units: 138_000,
        months_left: 2,
    },
});

/** Even Annual with the year plan used up (X = 0). */
export const evenAnnualUsedUp = item({
    category_id: 13,
    sort_order: 90,
    name: 'Ubezpieczenie samochodu',
    year_execution_minor_units: 54_100,
    current_plan_minor_units: 54_100,
    rhythm: 'even_annual',
    plan_minor_units: 4_512,
    deviation_minor_units: -4_512,
    approved_minor_units: 4_512,
    year_comparison: {
        year_minor_units: 54_100,
        annual_plan_minor_units: 54_100,
        remaining_minor_units: 0,
        is_over_year: false,
    },
    even_annual_remaining: { available_limit_minor_units: 0, months_left: 2 },
});

/** Even Annual over the year plan (X < 0). */
export const evenAnnualOver = item({
    category_id: 14,
    sort_order: 100,
    name: 'Wakacje',
    year_execution_minor_units: 130_000,
    current_plan_minor_units: 120_000,
    rhythm: 'even_annual',
    plan_minor_units: 10_000,
    actual_minor_units: 20_000,
    has_positive_amount: true,
    deviation_minor_units: 10_000,
    is_over_plan: true,
    approved_minor_units: 10_000,
    year_comparison: {
        year_minor_units: 130_000,
        annual_plan_minor_units: 120_000,
        remaining_minor_units: -10_000,
        is_over_year: true,
    },
    even_annual_remaining: {
        available_limit_minor_units: -10_000,
        months_left: 2,
    },
});

/** Even Annual in a future month: no year line, only "zostało X na Y mies." (X > 0, X = 0, X < 0). */
export const evenAnnualFutureLeft = item({
    category_id: 15,
    sort_order: 170,
    name: 'Prezenty świąteczne',
    rhythm: 'even_annual',
    plan_minor_units: 25_000,
    even_annual_remaining: {
        available_limit_minor_units: 275_000,
        months_left: 12,
    },
});

export const evenAnnualFutureUsedUp = item({
    category_id: 16,
    sort_order: 180,
    name: 'Przegląd roczny',
    rhythm: 'even_annual',
    plan_minor_units: 5_000,
    even_annual_remaining: { available_limit_minor_units: 0, months_left: 12 },
});

export const evenAnnualFutureOver = item({
    category_id: 17,
    sort_order: 190,
    name: 'Naprawy',
    rhythm: 'even_annual',
    plan_minor_units: 8_000,
    even_annual_remaining: {
        available_limit_minor_units: -12_000,
        months_left: 12,
    },
});

/** Irregular before any bill: the Collected at the end is the opening 300,00 plus the rate (450,00). */
export const irregularBeforeBill = item({
    category_id: 20,
    sort_order: 110,
    name: 'Serwis auta',
    rhythm: 'irregular',
    plan_minor_units: 15_000,
    deviation_minor_units: 0,
    approved_minor_units: 15_000,
    current_plan_minor_units: 180_000,
    collected: { start_minor_units: 30_000, end_minor_units: 45_000 },
    year_comparison: {
        year_minor_units: 0,
        annual_plan_minor_units: 180_000,
        remaining_minor_units: 180_000,
        is_over_year: false,
    },
});

/** Irregular with a bill covered by the Collected (400,00 against 300,00 + 150,00): Collected 50,00, no surplus. */
export const irregularCovered = item({
    category_id: 21,
    sort_order: 120,
    name: 'Rekolekcje',
    rhythm: 'irregular',
    plan_minor_units: 15_000,
    actual_minor_units: 40_000,
    has_positive_amount: true,
    deviation_minor_units: 0,
    approved_minor_units: 15_000,
    year_execution_minor_units: 40_000,
    current_plan_minor_units: 180_000,
    collected: { start_minor_units: 30_000, end_minor_units: 5_000 },
    year_comparison: {
        year_minor_units: 40_000,
        annual_plan_minor_units: 180_000,
        remaining_minor_units: 140_000,
        is_over_year: false,
    },
});

/** Irregular with a surplus (600,00 against 300,00 + 150,00): Collected 0,00, surplus 150,00, over plan. */
export const irregularSurplus = item({
    category_id: 22,
    sort_order: 130,
    name: 'Remont',
    rhythm: 'irregular',
    plan_minor_units: 15_000,
    actual_minor_units: 60_000,
    has_positive_amount: true,
    deviation_minor_units: 15_000,
    is_over_plan: true,
    approved_minor_units: 15_000,
    year_execution_minor_units: 60_000,
    current_plan_minor_units: 180_000,
    collected: { start_minor_units: 30_000, end_minor_units: 0 },
    year_comparison: {
        year_minor_units: 60_000,
        annual_plan_minor_units: 180_000,
        remaining_minor_units: 120_000,
        is_over_year: false,
    },
});

/** Irregular outside the plan (rate 0): joins with an amount above 0, nothing collected. */
export const irregularOutsidePlan = item({
    category_id: 23,
    sort_order: 140,
    name: 'Naprawa dachu',
    rhythm: 'irregular',
    in_plan: false,
    plan_minor_units: 0,
    deviation_minor_units: 0,
    collected: { start_minor_units: 0, end_minor_units: 0 },
});

/** Irregular in a future month: the forecast of the Collected with the bills that already exist. */
export const irregularFuture = item({
    category_id: 24,
    sort_order: 200,
    name: 'Serwis auta',
    rhythm: 'irregular',
    plan_minor_units: 15_000,
    deviation_minor_units: 0,
    collected: { start_minor_units: 45_000, end_minor_units: 60_000 },
});

export const archivedExpense = item({
    category_id: 5,
    sort_order: 70,
    name: 'Siłownia',
    is_archived: true,
    plan_minor_units: 12_000,
    actual_minor_units: 12_000,
    has_positive_amount: true,
    deviation_minor_units: 0,
    approved_minor_units: 15_000,
});

export const outsidePlanExpense = item({
    category_id: 6,
    sort_order: 60,
    name: 'Zakupy dodatkowe',
    in_plan: false,
    actual_minor_units: 4_500,
    deviation_minor_units: 4_500,
    is_over_plan: true,
});

export const overPlanExpense = item({
    category_id: 7,
    group_id: 102,
    sort_order: 30,
    name: 'Paliwo',
    year_execution_minor_units: 372_000,
    current_plan_minor_units: 360_000,
    plan_minor_units: 30_000,
    actual_minor_units: 41_000,
    has_positive_amount: true,
    deviation_minor_units: 11_000,
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
    sort_order: 40,
    name: 'Bardzo długa nazwa kategorii, która sprawdza zawijanie w wąskiej kolumnie',
    plan_minor_units: 99_900,
    actual_minor_units: 10_000,
    has_positive_amount: true,
    deviation_minor_units: -89_900,
});

/** Five- and six-figure amounts for the 390 px check: plan 123 456,78, actual 156,80, deviation -123 299,98. */
export const longAmountsExpense = item({
    category_id: 21,
    sort_order: 200,
    name: 'Remont domu',
    plan_minor_units: 12_345_678,
    actual_minor_units: 15_680,
    has_positive_amount: true,
    deviation_minor_units: -12_329_998,
    approved_minor_units: 12_345_678,
});

/** An expense below plan, not over it: the Deviation is `success` (ADR-0092). */
export const belowPlanExpense = item({
    category_id: 22,
    sort_order: 210,
    name: 'Kosmetyki',
    plan_minor_units: 50_000,
    actual_minor_units: 30_000,
    has_positive_amount: true,
    deviation_minor_units: -20_000,
    approved_minor_units: 50_000,
});

/** Even Annual with a positive Deviation of the month, year not over: no tone. */
export const evenAnnualAboveMonth = item({
    category_id: 23,
    sort_order: 220,
    name: 'Przegląd roczny',
    rhythm: 'even_annual',
    plan_minor_units: 10_000,
    actual_minor_units: 31_667,
    has_positive_amount: true,
    year_execution_minor_units: 31_667,
    current_plan_minor_units: 120_000,
    deviation_minor_units: 21_667,
    approved_minor_units: 10_000,
});

/** Even Annual over the year with a negative Deviation of the month: `destructive` by the year. */
export const evenAnnualOverYearBelowMonth = item({
    category_id: 24,
    sort_order: 230,
    name: 'Ubezpieczenie domu',
    rhythm: 'even_annual',
    plan_minor_units: 20_000,
    actual_minor_units: 5_000,
    has_positive_amount: true,
    year_execution_minor_units: 130_000,
    current_plan_minor_units: 120_000,
    deviation_minor_units: -15_000,
    is_over_plan: true,
    approved_minor_units: 20_000,
});

export const fixedChargeUnpaid = item({
    category_id: 9,
    group_id: 101,
    sort_order: 50,
    name: 'Ubezpieczenie',
    rhythm: 'fixed_charge',
    plan_minor_units: 25_000,
    has_positive_amount: true,
});

/** A list category without a budget line: plan 0, joins the plan only with an amount above 0. */
export const joinCandidateExpense = item({
    category_id: 11,
    sort_order: 160,
    name: 'Domeny',
    in_plan: false,
    is_join_candidate: true,
    year_comparison: {
        year_minor_units: 0,
        annual_plan_minor_units: 0,
        remaining_minor_units: 0,
        is_over_year: false,
    },
});

/** Category Groups of the expense list: Codzienne first, Mieszkanie after the ungrouped „Bardzo długa nazwa…”. */
export const expenseGroups: MonthPlanGroup[] = [
    { id: 102, name: 'Codzienne', sort_order: 10 },
    { id: 101, name: 'Mieszkanie', sort_order: 50 },
];

export const incomeGroups: MonthPlanGroup[] = [
    { id: 201, name: 'Wynagrodzenia', sort_order: 10 },
];

export const incomeRows: MonthPlanItem[] = [settledIncome, waitingIncome];

export const expenseRows: MonthPlanItem[] = [
    runningExpense,
    paidBill,
    overPlanExpense,
    longNameExpense,
    fixedChargeUnpaid,
    outsidePlanExpense,
    archivedExpense,
    evenAnnualLeft,
    evenAnnualUsedUp,
    evenAnnualOver,
    irregularBeforeBill,
    irregularCovered,
    irregularSurplus,
    irregularOutsidePlan,
    idleEvenAnnual,
];

/** Future month: the Even Annual rows carry only "zostało X na Y mies.". */
export const futureExpenseRows: MonthPlanItem[] = [
    runningExpense,
    paidBill,
    evenAnnualFutureLeft,
    evenAnnualFutureUsedUp,
    evenAnnualFutureOver,
    irregularFuture,
    {
        ...idleEvenAnnual,
        even_annual_remaining: {
            available_limit_minor_units: 30_000,
            months_left: 12,
        },
    },
].map((row) => ({ ...row, year_comparison: null }));

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
        set_aside_default: null,
        spendable_liquidity_minor_units: 500_000,
        month_pace: null,
        income_items: incomeRows,
        expense_items: expenseRows,
        income_groups: [],
        expense_groups: [],
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
    expense_items: futureExpenseRows,
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

/** Rows of the Deviation tone states: long amounts, below plan, annual with a positive month, annual over the year. */
export const deviationToneRows: MonthPlanItem[] = [
    longAmountsExpense,
    belowPlanExpense,
    overPlanExpense,
    evenAnnualAboveMonth,
    evenAnnualOverYearBelowMonth,
    archivedExpense,
];

export const deviationToneReading = buildMonthPlanReading({
    state: 'approved',
    can_approve: false,
    can_change: true,
    month_pace: paceInNorm,
    year_totals: yearTotals,
    income_items: [],
    expense_items: deviationToneRows,
});

/** Summary card, the favourable difference: less expense, more income, more set aside. */
export const favourableSummary: MonthSummary = {
    income: {
        ...pastSummary.income,
        actual_minor_units: 540_000,
        deviation_minor_units: 20_000,
    },
    expense: {
        ...pastSummary.expense,
        actual_minor_units: 370_000,
        deviation_minor_units: -20_000,
    },
    set_aside: {
        ...pastSummary.set_aside,
        real_minor_units: 60_000,
        difference_minor_units: 10_000,
    },
    year_draw_minor_units: 0,
};

/** Summary card, the unfavourable difference: less income, more expense, less set aside. */
export const unfavourableSummary: MonthSummary = {
    income: {
        ...pastSummary.income,
        actual_minor_units: 480_000,
        deviation_minor_units: -20_000,
    },
    expense: pastSummary.expense,
    set_aside: pastSummary.set_aside,
    year_draw_minor_units: 0,
};

/** Summary card, no difference: no tone. */
export const evenSummary: MonthSummary = {
    income: pastSummary.income,
    expense: {
        ...pastSummary.expense,
        actual_minor_units: 390_000,
        deviation_minor_units: 0,
    },
    set_aside: {
        ...pastSummary.set_aside,
        real_minor_units: 50_000,
        difference_minor_units: 0,
    },
    year_draw_minor_units: 0,
};

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

/**
 * Current approved month with Category Groups: Codzienne (over plan), a Category
 * without a group, Mieszkanie; the archived row still has its group (Codzienne)
 * and stands at the end, outside the group's figures (ADR-0079).
 */
export const groupedReading = buildMonthPlanReading({
    ...approvedReading,
    expense_items: approvedReading.expense_items.map((row) =>
        row.category_id === archivedExpense.category_id
            ? { ...row, group_id: 102 }
            : row,
    ),
    income_groups: incomeGroups,
    expense_groups: expenseGroups,
});
