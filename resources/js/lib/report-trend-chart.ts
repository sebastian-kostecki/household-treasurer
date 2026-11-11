import type { ReportTrendMonth } from '../types/reports';

export type ReportTrendChartBar = {
    /** 1 to 12. */
    month: number;
    income: number;
    expense: number;
};

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
