export type MonthItemKind = 'bill' | 'running';

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
    month_item_kind: MonthItemKind | null;
    is_one_off: boolean;
    is_archived: boolean;
    plan_minor_units: number;
    actual_minor_units: number;
    has_positive_amount: boolean;
    can_change_kind: boolean;
    in_plan: boolean;
    deviation_minor_units: number | null;
    is_over_plan: boolean;
    is_settled: boolean;
    available_limit_minor_units: number | null;
    is_idle_one_off: boolean;
    approved_minor_units: number | null;
    is_join_candidate: boolean;
    year_comparison: MonthYearComparison | null;
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
    year_totals: {
        income: MonthYearComparison;
        expense: MonthYearComparison;
    } | null;
    summary: MonthSummary | null;
};
