import { describe, expect, it } from 'vite-plus/test';
import type { ReportTrendMonth } from '../types/reports';
import { reportTrendChartBars } from './report-trend-chart';

function months(
    overrides: Record<number, Partial<ReportTrendMonth>> = {},
): ReportTrendMonth[] {
    return Array.from({ length: 12 }, (_, index) => ({
        month: index + 1,
        income_minor_units: null,
        expense_minor_units: null,
        result_minor_units: null,
        ...overrides[index + 1],
    }));
}

describe('reportTrendChartBars', () => {
    it('gives 12 bars in month order with income and expense', () => {
        const bars = reportTrendChartBars(
            months({
                1: {
                    income_minor_units: 500_000,
                    expense_minor_units: 300_000,
                },
                12: { expense_minor_units: 100_000 },
            }),
        );

        expect(bars).toHaveLength(12);
        expect(bars.map((bar) => bar.month)).toEqual([
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,
        ]);
        expect(bars[0]).toEqual({
            month: 1,
            income: 500_000,
            expense: 300_000,
        });
        expect(bars[11]).toEqual({ month: 12, income: 0, expense: 100_000 });
    });

    it('draws null and negative amounts as zero', () => {
        const bars = reportTrendChartBars(
            months({
                2: { income_minor_units: -5_000, expense_minor_units: 7_000 },
                4: { income_minor_units: 9_000, expense_minor_units: -2_000 },
            }),
        );

        expect(bars[1]).toEqual({ month: 2, income: 0, expense: 7_000 });
        expect(bars[2]).toEqual({ month: 3, income: 0, expense: 0 });
        // A month with only a refund has a negative expense: no bar below zero.
        expect(bars[3]).toEqual({ month: 4, income: 9_000, expense: 0 });
    });

    it('is empty when no value is positive', () => {
        expect(reportTrendChartBars(months())).toEqual([]);
        expect(
            reportTrendChartBars(
                months({
                    3: { income_minor_units: 0, expense_minor_units: -1 },
                }),
            ),
        ).toEqual([]);
    });
});
