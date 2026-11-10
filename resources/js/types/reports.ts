/** Mirror of `App\Support\PeriodReport::toArray()` and the Reports page props. */

export type PeriodReportCategory = {
    /** Id of the list Category (not of the budget line). */
    id: number;
    name: string;
    icon: string | null;
    color: string;
    amount_minor_units: number;
    /** Tenths of a percent of the period's expenses; null for income. */
    share_tenths_percent: number | null;
    is_archived: boolean;
};

export type PeriodReportGroupEntry = {
    kind: 'group';
    id: number;
    name: string;
    amount_minor_units: number;
    share_tenths_percent: number | null;
    categories: PeriodReportCategory[];
};

export type PeriodReportCategoryEntry = PeriodReportCategory & {
    kind: 'category';
};

export type PeriodReportEntry =
    | PeriodReportGroupEntry
    | PeriodReportCategoryEntry;

export type PeriodReportData = {
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    has_operations: boolean;
    income: PeriodReportEntry[];
    expense: PeriodReportEntry[];
};

export type ReportPeriod = {
    kind: 'year' | 'month';
    month: number | null;
    default_month: number;
    is_in_progress: boolean;
};

export type ReportsPageProps = {
    budgetYear: number | null;
    budgetPhase: 'draft' | 'execution' | null;
    period: ReportPeriod;
    report: PeriodReportData | null;
};
