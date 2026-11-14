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
    /** Operations in the current period; false for a row that exists only through the comparison. */
    has_operations: boolean;
    /** Amount in the comparison range; null without a comparison or without operations there. */
    previous_minor_units: number | null;
    /** Current minus previous (no operations count as 0); null only without a comparison. */
    difference_minor_units: number | null;
};

export type PeriodReportGroupEntry = {
    kind: 'group';
    id: number;
    name: string;
    amount_minor_units: number;
    share_tenths_percent: number | null;
    has_operations: boolean;
    previous_minor_units: number | null;
    difference_minor_units: number | null;
    categories: PeriodReportCategory[];
};

export type PeriodReportCategoryEntry = PeriodReportCategory & {
    kind: 'category';
};

export type PeriodReportEntry =
    | PeriodReportGroupEntry
    | PeriodReportCategoryEntry;

export type PeriodReportComparison = {
    /** Year of the compared Budget (the selected year minus one). */
    year: number;
    /** First and last day of the compared range, `Y-m-d`. */
    from: string;
    through: string;
    /** True when a period in progress is cut before the end of its equivalent. */
    is_partial: boolean;
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    income_difference_minor_units: number;
    expense_difference_minor_units: number;
    result_difference_minor_units: number;
};

export type PeriodReportData = {
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    /** The report has at least one row, from either range (unlike a row's `has_operations`, which is the current period only). */
    has_operations: boolean;
    comparison: PeriodReportComparison | null;
    income: PeriodReportEntry[];
    expense: PeriodReportEntry[];
};

/** Mirror of `App\Support\ReportTrend::toArray()` (ADR-0077). Cells are minor units; null is a month without an operation. */

export type ReportTrendCategory = {
    /** Id of the list Category (not of the budget line). */
    id: number;
    name: string;
    icon: string | null;
    color: string;
    is_archived: boolean;
    amount_minor_units: number;
    /** 12 cells, January first. */
    months: (number | null)[];
};

export type ReportTrendGroupEntry = {
    kind: 'group';
    id: number;
    name: string;
    amount_minor_units: number;
    months: (number | null)[];
    categories: ReportTrendCategory[];
};

export type ReportTrendEntry =
    | ReportTrendGroupEntry
    | (ReportTrendCategory & { kind: 'category' });

export type ReportTrendMonth = {
    /** 1 to 12. */
    month: number;
    income_minor_units: number | null;
    expense_minor_units: number | null;
    result_minor_units: number | null;
};

export type ReportTrendData = {
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    has_operations: boolean;
    /** Month of today when the Budget year is the current one. */
    in_progress_month: number | null;
    months: ReportTrendMonth[];
    income: ReportTrendEntry[];
    expense: ReportTrendEntry[];
};

/** Mirror of `App\Support\ReportTwelveMonthWindow::toArray()` (ADR-0081); dates are `Y-m-d`. */
export type ReportWindow = {
    /** First and last day of the 12 closed months. */
    from: string;
    through: string;
    /** The stretch of the window covered by Budgets in execution; null when none is. */
    covered_from: string | null;
    covered_through: string | null;
    /** 0 to 12. */
    covered_month_count: number;
    /** The stretch without a Budget in execution; null when the window is fully covered. */
    missing_from: string | null;
    missing_through: string | null;
};

/** A date range a report row links to in Operations; `label` names it for screen readers. */
export type ReportRange = {
    from: string;
    through: string;
    label: string;
};

/** Mirror of `App\Support\ReportYears::toArray()` (ADR-0097). Cells are minor units in the order of `columns`; null is "—" (no Budget Line in the year). */
export type ReportYearsCells = {
    cells: (number | null)[];
    /** Change from the first year with an amount to the final year, tenths of a percent; null without both. */
    change_tenths: number | null;
    change_first_year: number | null;
    /** Years with the highest amount of the row (all of them on a tie). */
    max_years: number[];
};

export type ReportYearsCategory = ReportYearsCells & {
    kind: 'category';
    /** Id of the list Category (not of the budget line). */
    id: number;
    name: string;
    icon: string;
    color: string;
    is_archived: boolean;
    /** The name opens the category panel of the target year (a row in that year's report, ADR-0101). */
    opens_panel: boolean;
};

export type ReportYearsGroup = ReportYearsCells & {
    kind: 'group';
    id: number;
    name: string;
    categories: ReportYearsCategory[];
};

export type ReportYearsEntry = ReportYearsCategory | ReportYearsGroup;

export type ReportYearsFastest = {
    id: number;
    name: string;
    icon: string;
    color: string;
    growth_tenths: number;
    first_year: number;
};

export type ReportYearsSection = {
    entries: ReportYearsEntry[];
    total: ReportYearsCells;
    first_year: number | null;
    average_yearly_growth_tenths: number | null;
    fastest_growing: ReportYearsFastest | null;
};

export type ReportYearsMode = {
    final_year: number | null;
    expense: ReportYearsSection;
    income: ReportYearsSection;
};

export type ReportYearsData = {
    columns: number[];
    current_year: number | null;
    cut: { month: number; day: number } | null;
    /** Null without a Budget in execution in the current year. */
    same_day: ReportYearsMode | null;
    full: ReportYearsMode;
};

export type ReportPeriod = {
    kind: 'year' | 'month' | 'last-12' | 'trend' | 'years';
    month: number | null;
    default_month: number;
    is_in_progress: boolean;
    /** Only for `last-12`, otherwise null. */
    window: ReportWindow | null;
};

export type ReportRankingMode = 'categories' | 'groups';

/** Mirror of one item of `App\Support\ReportCategoryPanel::toArray()['categories']`. */
export type ReportCategoryPanelItem = {
    /** Id of the list Category. */
    id: number;
    /** `Rhythm` value of the viewed month (`running`, `irregular`, ...); null for income. */
    rhythm: string | null;
    /** 12 cells, January first; null is a month without an operation, 0 a net-zero month. */
    months: (number | null)[];
    /** Average of the closed months, rounded half away from zero; null without a closed month. */
    closed_average_minor_units: number | null;
    /** 1 to 12, among the months that are not future; null when none is positive. */
    highest_month: number | null;
    /** (amount - previous) / previous in tenths of a percent; null without a usable comparison. */
    difference_tenths_percent: number | null;
    /** The row of the Category in the Years report in the mode of the panel (ADR-0101). */
    years: ReportYearsCells;
};

export type ReportCategoryPanelTab = 'months' | 'years';

/** Columns and mode of the `years` of every panel Category (ADR-0101). */
export type ReportCategoryPanelYears = {
    columns: number[];
    current_year: number | null;
    cut: { month: number; day: number } | null;
    /** `same_day` for the current year, `full` for every other one. */
    mode: 'same_day' | 'full';
};

/** Mirror of `App\Support\ReportCategoryPanel::toArray()` (ADR-0097), only for the year in execution with operations. */
export type ReportCategoryPanelData = {
    /** `?panel=years` opens the years tab. */
    tab: ReportCategoryPanelTab;
    /** The `?category` that has a row in the year report; null when absent or unknown. */
    requested_category_id: number | null;
    /** Month of today when the Budget year is the current one. */
    in_progress_month: number | null;
    /** Today, `Y-m-d`, with a month in progress. */
    through: string | null;
    /** 12 for a past year, the months before today's in the current year, 0 for a future year. */
    closed_month_count: number;
    years: ReportCategoryPanelYears;
    categories: ReportCategoryPanelItem[];
};

export type ReportsPageProps = {
    budgetYear: number | null;
    budgetPhase: 'draft' | 'execution' | null;
    period: ReportPeriod;
    report: PeriodReportData | null;
    trend: ReportTrendData | null;
    years: ReportYearsData | null;
    rankingMode: ReportRankingMode;
    categoryPanel: ReportCategoryPanelData | null;
};
