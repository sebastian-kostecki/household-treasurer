import type { PeriodReportEntry } from '../types/reports';

export type ReportChartBar = { index: number; name: string; value: number };

/**
 * First-level bars of a Period Report chart (ADR-0071), one per table row and
 * in the same order. A row whose amount is not positive keeps its place but
 * gets no bar. When no row has a positive amount there is nothing to draw, so
 * the list is empty and the table below stands alone.
 */
export function reportChartBars(
    entries: PeriodReportEntry[],
): ReportChartBar[] {
    const bars = entries.map((entry, index) => ({
        index,
        name: entry.name,
        value: Math.max(0, entry.amount_minor_units),
    }));

    return bars.some((bar) => bar.value > 0) ? bars : [];
}
