export type MonthItemKind = 'bill' | 'running';

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
};
