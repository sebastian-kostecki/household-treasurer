import { describe, expect, it } from 'vite-plus/test';
import {
    remTokenToPixels,
    reportClosedRangeLabel,
    reportInProgressLabel,
    reportMonthBars,
    reportMonthBarsAccessibleList,
    reportPanelHighestYearsLabel,
    reportPanelYearsChangeLabel,
    reportPanelYearsRangeLabel,
    reportYearBars,
    reportYearBarsAccessibleList,
    reportPanelCaption,
    reportPanelLayout,
    reportPanelOperationsLabel,
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

    it('keeps a dollar sign of a group name as typed', () => {
        const labels = {
            expense: 'Wydatek',
            income: 'Przychód',
            group: 'grupa :name',
            rhythm: 'rytm :rhythm',
        };

        expect(
            reportPanelCaption('expense', "Dom $& $' $`", null, labels),
        ).toBe("Wydatek · grupa Dom $& $' $`");
    });

    it('names the Operations button with the Category and the year', () => {
        const template = 'Operacje: :name, :year';

        expect(reportPanelOperationsLabel(template, 'Kino', 2026)).toBe(
            'Operacje: Kino, 2026',
        );
        expect(reportPanelOperationsLabel(template, 'Bar $&', 2026)).toBe(
            'Operacje: Bar $&, 2026',
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

const columns = [2020, 2021, 2022, 2023];
const money = (minorUnits: number): string => `${minorUnits / 100} zł`;

describe('reportYearBars', () => {
    it('scales to the largest amount and marks the current year', () => {
        const bars = reportYearBars([1000, 2000, 4000, 2000], columns, 2023);

        expect(bars.map((bar) => bar.ratio)).toEqual([0.25, 0.5, 1, 0.5]);
        expect(bars.map((bar) => bar.isCurrent)).toEqual([
            false,
            false,
            false,
            true,
        ]);
        expect(bars.every((bar) => bar.state === 'amount')).toBe(true);
    });

    it('marks a year without a line as none with a zero ratio', () => {
        const bars = reportYearBars([1000, null, 2000, 2000], columns, null);

        expect(bars[1]).toMatchObject({ state: 'none', ratio: 0 });
        expect(bars.some((bar) => bar.isCurrent)).toBe(false);
    });

    it('draws a negative or zero year as a zero bar', () => {
        const bars = reportYearBars([-500, 0, 2000, 1000], columns, 2023);

        expect(bars.map((bar) => bar.ratio)).toEqual([0, 0, 1, 0.5]);
        expect(bars[0].state).toBe('amount');
    });

    it('gives zero ratios when no amount is positive', () => {
        const bars = reportYearBars([-500, 0, null, -1], columns, 2023);

        expect(bars.map((bar) => bar.ratio)).toEqual([0, 0, 0, 0]);
    });
});

describe('reportYearBarsAccessibleList', () => {
    it('lists every column, a missing line with its label and the exact negative amount', () => {
        const cells = [1000, null, -500, 2000];
        const bars = reportYearBars(cells, columns, 2023);

        expect(
            reportYearBarsAccessibleList(bars, cells, money, 'brak pozycji'),
        ).toEqual([
            '2020: 10 zł',
            '2021: brak pozycji',
            '2022: -5 zł',
            '2023: 20 zł',
        ]);
    });
});

describe('years rows of the panel', () => {
    it('labels the change from the first year with an amount', () => {
        expect(reportPanelYearsChangeLabel(2022, 2020, 'Zmiana od :year')).toBe(
            'Zmiana od 2022',
        );
        expect(reportPanelYearsChangeLabel(null, 2020, 'Zmiana od :year')).toBe(
            'Zmiana od 2020',
        );
    });

    it('names the highest year, all of them on a tie, and a dash without one', () => {
        const cells = [1000, 4000, 4000, 2000];

        expect(
            reportPanelHighestYearsLabel([2021], cells, columns, money),
        ).toBe('2021 · 40 zł');
        expect(
            reportPanelHighestYearsLabel([2021, 2022], cells, columns, money),
        ).toBe('2021, 2022 · 40 zł');
        expect(reportPanelHighestYearsLabel([], cells, columns, money)).toBe(
            '—',
        );
    });

    it('labels the range in both modes', () => {
        const labels = {
            sameDay: 'Każdy rok do :date',
            full: 'Pełne lata',
        };

        expect(
            reportPanelYearsRangeLabel(
                'same_day',
                { month: 11, day: 14 },
                'listopada',
                labels,
            ),
        ).toBe('Każdy rok do 14 listopada');
        expect(reportPanelYearsRangeLabel('full', null, null, labels)).toBe(
            'Pełne lata',
        );
        expect(
            reportPanelYearsRangeLabel(
                'full',
                { month: 11, day: 14 },
                'listopada',
                labels,
            ),
        ).toBe('Pełne lata');
    });
});
