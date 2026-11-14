import { describe, expect, it } from 'vite-plus/test';
import {
    remTokenToPixels,
    reportClosedRangeLabel,
    reportInProgressLabel,
    reportMonthBars,
    reportMonthBarsAccessibleList,
    reportPanelCaption,
    reportPanelLayout,
    resolveSelectedCategory,
} from './report-category-panel';
import type { RankedReportCategory } from './report-ranking';

function ranked(id: number, position: number): RankedReportCategory {
    return {
        id,
        name: `K${id}`,
        icon: null,
        color: 'green',
        amount_minor_units: 1000,
        share_tenths_percent: null,
        is_archived: false,
        has_operations: true,
        previous_minor_units: null,
        difference_minor_units: null,
        groupName: null,
        position,
    };
}

const monthNames = [
    'styczeń',
    'luty',
    'marzec',
    'kwiecień',
    'maj',
    'czerwiec',
    'lipiec',
    'sierpień',
    'wrzesień',
    'październik',
    'listopad',
    'grudzień',
];
const chips = [
    'sty',
    'lut',
    'mar',
    'kwi',
    'maj',
    'cze',
    'lip',
    'sie',
    'wrz',
    'paź',
    'lis',
    'gru',
];

describe('resolveSelectedCategory', () => {
    const expenses = [ranked(1, 1), ranked(2, 2)];
    const incomes = [ranked(7, 1)];

    it('prefers the requested category from either list', () => {
        expect(resolveSelectedCategory(2, expenses, incomes)?.category.id).toBe(
            2,
        );
        expect(resolveSelectedCategory(7, expenses, incomes)).toMatchObject({
            type: 'income',
        });
    });

    it('falls back to the first expense, then the first income, then null', () => {
        expect(
            resolveSelectedCategory(null, expenses, incomes)?.category.id,
        ).toBe(1);
        expect(
            resolveSelectedCategory(99, expenses, incomes)?.category.id,
        ).toBe(1);
        expect(resolveSelectedCategory(null, [], incomes)).toMatchObject({
            type: 'income',
        });
        expect(resolveSelectedCategory(null, [], [])).toBeNull();
    });
});

describe('reportMonthBars', () => {
    it('draws future months of the current year empty despite their amount', () => {
        const months = [100, 200, 400, null, 0, 50, 0, 0, 0, 0, 300, 999];
        const bars = reportMonthBars(months, 10, 11, 3);

        expect(bars[9].state).toBe('past');
        expect(bars[10].state).toBe('in-progress');
        expect(bars[11].state).toBe('future');
        expect(bars[11].ratio).toBe(0);
        expect(bars[2].ratio).toBe(1);
        expect(bars[10].ratio).toBe(0.75);
        expect(bars[3].ratio).toBe(0);
    });

    it('marks only the highest month of the server, none for null', () => {
        const months = Array.from({ length: 12 }, () => 100);

        expect(
            reportMonthBars(months, 12, null, 5)
                .filter((bar) => bar.isHighest)
                .map((bar) => bar.month),
        ).toEqual([5]);
        expect(
            reportMonthBars(months, 12, null, null).some(
                (bar) => bar.isHighest,
            ),
        ).toBe(false);
    });

    it('draws a negative month as a zero bar in a past year', () => {
        const bars = reportMonthBars(
            [-500, 200, ...Array<null>(10).fill(null)],
            12,
            null,
            2,
        );

        expect(bars[0].ratio).toBe(0);
        expect(bars[0].amountMinorUnits).toBe(-500);
        expect(bars.every((bar) => bar.state === 'past')).toBe(true);
    });

    it('treats every month of a future year as future', () => {
        const bars = reportMonthBars(
            Array<number>(12).fill(100),
            0,
            null,
            null,
        );

        expect(
            bars.every((bar) => bar.state === 'future' && bar.ratio === 0),
        ).toBe(true);
    });
});

describe('labels', () => {
    it('names the closed range', () => {
        expect(reportClosedRangeLabel(10, chips)).toBe('sty–paź');
        expect(reportClosedRangeLabel(12, chips)).toBe('sty–gru');
        expect(reportClosedRangeLabel(1, chips)).toBe('sty');
        expect(reportClosedRangeLabel(0, chips)).toBeNull();
    });

    it('names the month in progress', () => {
        expect(
            reportInProgressLabel(
                11,
                '2026-11-14',
                monthNames,
                ':month do :date (w toku)',
            ),
        ).toBe('Listopad do 14.11 (w toku)');
    });

    it('lists the months that are not future', () => {
        const bars = reportMonthBars(
            [100, null, ...Array<number>(10).fill(5)],
            2,
            3,
            null,
        );
        const list = reportMonthBarsAccessibleList(
            bars,
            monthNames,
            (amount) => `${amount} zł`,
            '—',
        );

        expect(list).toEqual(['Styczeń: 100 zł', 'Luty: —', 'Marzec: 5 zł']);
    });

    it('builds the caption with and without a group and for income', () => {
        const labels = {
            expense: 'Wydatek',
            income: 'Przychód',
            group: 'grupa :name',
            rhythm: 'rytm :rhythm',
        };

        expect(
            reportPanelCaption('expense', 'Codzienne', 'bieżąca', labels),
        ).toBe('Wydatek · grupa Codzienne · rytm bieżąca');
        expect(reportPanelCaption('expense', null, 'bieżąca', labels)).toBe(
            'Wydatek · rytm bieżąca',
        );
        expect(reportPanelCaption('income', 'Praca', null, labels)).toBe(
            'Przychód · grupa Praca',
        );
    });
});

describe('layout', () => {
    it('is unknown before the first measure, then aside or sheet', () => {
        expect(reportPanelLayout(0, 960)).toBe('unknown');
        expect(reportPanelLayout(959, 960)).toBe('sheet');
        expect(reportPanelLayout(960, 960)).toBe('aside');
    });

    it('converts a rem token', () => {
        expect(remTokenToPixels('60rem', 16)).toBe(960);
        expect(remTokenToPixels(' 37.5rem ', 16)).toBe(600);
        expect(remTokenToPixels('960px', 16)).toBeNull();
    });
});
