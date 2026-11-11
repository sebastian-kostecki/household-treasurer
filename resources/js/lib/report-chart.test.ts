import { describe, expect, it } from 'vite-plus/test';
import type { PeriodReportEntry } from '../types/reports';
import { reportChartBars } from './report-chart';

function category(id: number, name: string, amount: number): PeriodReportEntry {
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
        previous_minor_units: null,
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
            { index: 0, name: 'Żywność', value: 100_000 },
            { index: 1, name: 'Elektronika', value: 0 },
            { index: 2, name: 'Kino', value: 0 },
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
});
