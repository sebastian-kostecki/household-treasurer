import { describe, expect, it } from 'vite-plus/test';
import type { PeriodReportEntry } from '../types/reports';
import { reportChartBars, reportChartHeightRem } from './report-chart';

function category(
    id: number,
    name: string,
    amount: number,
    previous: number | null = null,
): PeriodReportEntry {
    return {
        kind: 'category',
        id,
        name,
        icon: null,
        color: 'slate',
        amount_minor_units: amount,
        share_tenths_percent: null,
        is_archived: false,
        has_operations: true,
        previous_minor_units: previous,
        difference_minor_units: null,
    };
}

describe('reportChartBars', () => {
    it('keeps the table order and gives a row without a positive amount no bar', () => {
        expect(
            reportChartBars([
                category(1, 'Żywność', 100_000),
                category(2, 'Elektronika', -20_000),
                category(3, 'Kino', 0),
            ]),
        ).toEqual([
            { index: 0, name: 'Żywność', value: 100_000, previousValue: null },
            { index: 1, name: 'Elektronika', value: 0, previousValue: null },
            { index: 2, name: 'Kino', value: 0, previousValue: null },
        ]);
    });

    it('draws nothing when no row has a positive amount', () => {
        expect(
            reportChartBars([
                category(1, 'Elektronika', -20_000),
                category(2, 'Kino', 0),
            ]),
        ).toEqual([]);
    });

    it('draws nothing without rows', () => {
        expect(reportChartBars([])).toEqual([]);
    });

    it('gives every row both values with a comparison', () => {
        expect(
            reportChartBars(
                [
                    category(1, 'Żywność', 100_000, 80_000),
                    category(2, 'Kino', 5_000, null),
                ],
                true,
            ),
        ).toEqual([
            {
                index: 0,
                name: 'Żywność',
                value: 100_000,
                previousValue: 80_000,
            },
            { index: 1, name: 'Kino', value: 5_000, previousValue: 0 },
        ]);
    });

    it('draws a row that has only the year before', () => {
        expect(
            reportChartBars([category(1, 'Prezenty', 0, 150_000)], true),
        ).toEqual([
            { index: 0, name: 'Prezenty', value: 0, previousValue: 150_000 },
        ]);
    });

    it('clamps non-positive amounts of both series to zero', () => {
        expect(
            reportChartBars(
                [
                    category(1, 'Żywność', 10_000, -4_000),
                    category(2, 'Kino', -2_000, 3_000),
                ],
                true,
            ),
        ).toEqual([
            { index: 0, name: 'Żywność', value: 10_000, previousValue: 0 },
            { index: 1, name: 'Kino', value: 0, previousValue: 3_000 },
        ]);
    });

    it('draws nothing with a comparison when no value is positive', () => {
        expect(
            reportChartBars(
                [
                    category(1, 'Kino', 0, -100),
                    category(2, 'Elektronika', -5, null),
                ],
                true,
            ),
        ).toEqual([]);
    });
});

describe('reportChartHeightRem', () => {
    it('gives each bar 3 rem plus 2 rem for the axis', () => {
        expect(reportChartHeightRem(6, false)).toBe(20);
        expect(reportChartHeightRem(2, false)).toBe(8);
    });

    it('gives each compared bar 4.5 rem plus 2 rem for the axis', () => {
        expect(reportChartHeightRem(6, true)).toBe(29);
    });

    it('is zero without bars, so no chart and no placeholder take room', () => {
        expect(reportChartHeightRem(0, false)).toBe(0);
        expect(reportChartHeightRem(0, true)).toBe(0);
    });
});
