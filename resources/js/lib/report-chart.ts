import type { PeriodReportEntry } from '../types/reports';

export type ReportChartBar = {
    index: number;
    name: string;
    value: number;
    /** The same range a year earlier; null when the report has no comparison. */
    previousValue: number | null;
};

/**
 * First-level bars of a Period Report chart (ADR-0071), one per table row and
 * in the same order. A row whose amounts are not positive keeps its place but
 * gets no bar. With a comparison a row has two values (current and a year
 * earlier) and is drawn when either is positive. When no row has a positive
 * value there is nothing to draw, so the list is empty and the table below
 * stands alone.
 */
export function reportChartBars(
    entries: PeriodReportEntry[],
    hasComparison = false,
): ReportChartBar[] {
    const bars = entries.map((entry, index) => ({
        index,
        name: entry.name,
        value: Math.max(0, entry.amount_minor_units),
        previousValue: hasComparison
            ? Math.max(0, entry.previous_minor_units ?? 0)
            : null,
    }));

    return bars.some((bar) => bar.value > 0 || (bar.previousValue ?? 0) > 0)
        ? bars
        : [];
}
