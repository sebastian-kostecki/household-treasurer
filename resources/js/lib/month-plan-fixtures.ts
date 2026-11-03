import type { MonthPlanItem, MonthPlanReading } from '@/types/month-plan';

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

export const incomeRows: MonthPlanItem[] = [settledIncome];

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

export function buildMonthPlanReading(
    overrides: Partial<MonthPlanReading> = {},
): MonthPlanReading {
    return {
        state: 'proposal',
        approved_at: null,
        can_approve: true,
        can_change: false,
        to_spend_minor_units: 120_000,
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
