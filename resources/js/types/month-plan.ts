import type { Rhythm } from './rhythm';

export type MonthStripState = 'summary' | 'approved' | 'proposal';

export type MonthPace = {
    as_of: string;
    elapsed_day: number;
    days_in_month: number;
    pool_plan_minor_units: number;
    pool_actual_minor_units: number;
    percent: number | null;
    is_ahead: boolean;
};

export type MonthYearComparison = {
    year_minor_units: number;
    annual_plan_minor_units: number;
    remaining_minor_units: number;
    is_over_year: boolean;
};

export type MonthPlanItem = {
    category_id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    /** Category Group of the Category on the list; null when it stands outside any group. */
    group_id: number | null;
    /** Place of the Category in the one order of its type (`categories.sort_order`). */
    sort_order: number;
    is_archived: boolean;
    plan_minor_units: number;
    actual_minor_units: number;
    has_positive_amount: boolean;
    in_plan: boolean;
    deviation_minor_units: number | null;
    is_over_plan: boolean;
    is_settled: boolean;
    /** Even Annual without a plan amount and without operations in the month (ADR-0072). */
    is_idle_even_annual: boolean;
    /** The Rhythm of the viewed month (ADR-0066); null for an income item. */
    rhythm: Rhythm | null;
    /** Execution of the whole year of the Category, for the over-plan reading of an Even Annual item. */
    year_execution_minor_units: number;
    /** Current Plan of the Category in the year. */
    current_plan_minor_units: number;
    /** "Zostało X na Y mies." of an Even Annual item in the current and future months. */
    even_annual_remaining: {
        available_limit_minor_units: number;
        months_left: number;
    } | null;
    approved_minor_units: number | null;
    is_join_candidate: boolean;
    /** The Collected of an irregular item at the start and at the end of the viewed month (ADR-0073); null for any other Rhythm. */
    collected: {
        start_minor_units: number;
        end_minor_units: number;
    } | null;
    year_comparison: MonthYearComparison | null;
};

export type MonthPlanGroup = {
    id: number;
    name: string;
    sort_order: number;
};

export type MonthSummaryTotals = {
    approved_minor_units: number;
    final_minor_units: number;
    actual_minor_units: number;
    deviation_minor_units: number;
};

export type MonthSummary = {
    income: MonthSummaryTotals;
    expense: MonthSummaryTotals;
    set_aside: {
        approved_minor_units: number;
        final_minor_units: number;
        real_minor_units: number;
        difference_minor_units: number;
    };
    year_draw_minor_units: number;
};

export type MonthPlanReading = {
    state: 'proposal' | 'approved' | 'none';
    approved_at: string | null;
    can_approve: boolean;
    can_change: boolean;
    to_spend_minor_units: number | null;
    to_spend_income_minor_units: number | null;
    to_spend_expense_minor_units: number | null;
    month_states: (MonthStripState | null)[];
    set_aside_minor_units: number;
    spendable_liquidity_minor_units: number | null;
    month_pace: MonthPace | null;
    income_items: MonthPlanItem[];
    expense_items: MonthPlanItem[];
    income_groups: MonthPlanGroup[];
    expense_groups: MonthPlanGroup[];
    year_totals: {
        income: MonthYearComparison;
        expense: MonthYearComparison;
    } | null;
    summary: MonthSummary | null;
};
