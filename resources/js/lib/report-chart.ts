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

/** Colour of the same period a year earlier (token, ADR-0071). */
export const REPORT_CHART_PREVIOUS_COLOR = 'var(--chart-2)';

/** Colour of the current period: income `--success`, expense `--chart-1`. */
export function reportChartColor(type: 'income' | 'expense'): string {
    return type === 'income' ? 'var(--success)' : 'var(--chart-1)';
}

export type ReportChartLegendItem = {
    key: string;
    color: string;
    label: string;
};

/**
 * Legend of the bar chart with a comparison: the current year and the year
 * before. The one source for the chart and for its placeholder, so the legend
 * has the same height before and after the lazy fragment arrives.
 * `labelTemplate` is `t.reports.chart_legend_previous` (with `:year`).
 */
export function reportChartLegend(
    type: 'income' | 'expense',
    labelTemplate: string,
    budgetYear: number | null | undefined,
    comparisonYear: number | null | undefined,
): ReportChartLegendItem[] {
    const label = (year: number | null | undefined): string =>
        labelTemplate.replace(':year', String(year ?? ''));

    return [
        {
            key: 'current',
            color: reportChartColor(type),
            label: label(budgetYear),
        },
        {
            key: 'previous',
            color: REPORT_CHART_PREVIOUS_COLOR,
            label: label(comparisonYear),
        },
    ];
}

const ROW_HEIGHT_REM = 3;
const COMPARED_ROW_HEIGHT_REM = 4.5;
const AXIS_HEIGHT_REM = 2;

/** Width of the category names left of the bars, in px (chart axis). */
export const REPORT_CHART_NAME_WIDTH_PX = 110;

/** Number of amount ticks on the value axis of the bar chart. */
export const REPORT_CHART_VALUE_TICKS = 3;

/**
 * Height of the bar chart in rem: the one source for the chart and for the
 * placeholder that holds its place while the lazy fragment loads, so the page
 * does not jump. Zero when there are no bars (nothing is drawn).
 */
export function reportChartHeightRem(
    barCount: number,
    compared: boolean,
): number {
    if (barCount <= 0) {
        return 0;
    }

    return (
        barCount * (compared ? COMPARED_ROW_HEIGHT_REM : ROW_HEIGHT_REM) +
        AXIS_HEIGHT_REM
    );
}
