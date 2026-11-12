import type { ReportTrendMonth } from '../types/reports';

export type ReportTrendChartBar = {
    /** 1 to 12. */
    month: number;
    income: number;
    expense: number;
};

/** Income `--success`, expense `--chart-1` (tokens, ADR-0077). */
export const REPORT_TREND_INCOME_COLOR = 'var(--success)';
export const REPORT_TREND_EXPENSE_COLOR = 'var(--chart-1)';

/**
 * Legend of the Trend chart. The one source for the chart and for its
 * placeholder, so the legend has the same height before and after the lazy
 * fragment arrives. `labels` is `t.reports` (`income`, `expense`).
 */
export function reportTrendLegend(labels: Record<string, string>): {
    key: string;
    color: string;
    label: string;
}[] {
    return [
        {
            key: 'income',
            color: REPORT_TREND_INCOME_COLOR,
            label: labels.income,
        },
        {
            key: 'expense',
            color: REPORT_TREND_EXPENSE_COLOR,
            label: labels.expense,
        },
    ];
}

/**
 * The legend note "<month> w toku", or null when no month is in progress.
 * `reports` is `t.reports` (`trend_legend_in_progress`), `budget` is
 * `t.budget` (`month_name_N`).
 */
export function reportTrendInProgressNote(
    inProgressMonth: number | null,
    reports: Record<string, string>,
    budget: Record<string, string>,
): string | null {
    return inProgressMonth === null
        ? null
        : reports.trend_legend_in_progress.replace(
              ':month',
              budget[`month_name_${inProgressMonth}`],
          );
}

/**
 * Bars of the Trend chart (ADR-0077): two values per month, taken from the
 * same `months` as the Przychody and Wydatki rows of the measures table. A
 * month without an operation (null) or with a negative net amount is drawn
 * as zero. When no value is positive there is nothing to draw, so the list is
 * empty and the table stands alone.
 */
export function reportTrendChartBars(
    months: ReportTrendMonth[],
): ReportTrendChartBar[] {
    const bars = months.map((month) => ({
        month: month.month,
        income: Math.max(0, month.income_minor_units ?? 0),
        expense: Math.max(0, month.expense_minor_units ?? 0),
    }));

    return bars.some((bar) => bar.income > 0 || bar.expense > 0) ? bars : [];
}
