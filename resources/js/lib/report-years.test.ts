import { describe, expect, it } from 'vite-plus/test';
import {
    formatYearsChange,
    reportYearsCutLabel,
    reportYearsCutShort,
    reportYearsInitialRange,
    reportYearsSparkBars,
} from './report-years';

describe('reportYearsCutLabel', () => {
    it('names the cut day with the genitive month', () => {
        expect(
            reportYearsCutLabel(
                { month: 11, day: 14 },
                'listopada',
                'Do :day :month',
            ),
        ).toBe('Do 14 listopada');
        expect(
            reportYearsCutLabel(
                { month: 2, day: 29 },
                'lutego',
                'Do :day :month',
            ),
        ).toBe('Do 29 lutego');
    });
});

describe('reportYearsCutShort', () => {
    it('writes the day and a two-digit month', () => {
        expect(reportYearsCutShort({ month: 11, day: 14 })).toBe('14.11');
        expect(reportYearsCutShort({ month: 2, day: 9 })).toBe('9.02');
    });
});

describe('formatYearsChange', () => {
    it('signs a rise with plus and a fall with the minus sign', () => {
        expect(formatYearsChange(194)).toBe('+19,4%');
        expect(formatYearsChange(-83)).toBe('−8,3%');
        expect(formatYearsChange(5)).toBe('+0,5%');
    });

    it('keeps zero unsigned and a missing value as a dash', () => {
        expect(formatYearsChange(0)).toBe('0,0%');
        expect(formatYearsChange(null)).toBe('—');
    });
});

describe('reportYearsInitialRange', () => {
    const mode = {} as never;

    it('opens on the same day cut when the server says so', () => {
        expect(
            reportYearsInitialRange({
                same_day: mode,
                default_range: 'same_day',
            }),
        ).toBe('same-day');
    });

    it('opens on full years in January although the same day cut exists', () => {
        expect(
            reportYearsInitialRange({ same_day: mode, default_range: 'full' }),
        ).toBe('full');
    });

    it('opens on full years without a current year', () => {
        expect(
            reportYearsInitialRange({
                same_day: null,
                default_range: 'same_day',
            }),
        ).toBe('full');
    });
});

describe('reportYearsSparkBars', () => {
    const columns = [2024, 2025, 2026];

    it('scales to the maximum and marks the current year', () => {
        expect(
            reportYearsSparkBars([5000, 10000, 2500], columns, 2026),
        ).toEqual([
            { year: 2024, heightRatio: 0.5, isEmpty: false, isCurrent: false },
            { year: 2025, heightRatio: 1, isEmpty: false, isCurrent: false },
            { year: 2026, heightRatio: 0.25, isEmpty: false, isCurrent: true },
        ]);
    });

    it('marks a missing year empty and draws negative and zero amounts as zero', () => {
        const bars = reportYearsSparkBars([null, -300, 0], columns, null);

        expect(bars.map((bar) => bar.isEmpty)).toEqual([true, false, false]);
        expect(bars.map((bar) => bar.heightRatio)).toEqual([0, 0, 0]);
        expect(bars.map((bar) => bar.isCurrent)).toEqual([false, false, false]);
    });

    it('draws a negative amount as a zero bar next to a positive maximum', () => {
        expect(
            reportYearsSparkBars([-300, 0, 1000], columns, 2026).map(
                (bar) => bar.heightRatio,
            ),
        ).toEqual([0, 0, 1]);
    });

    it('draws only zeros when no amount is positive', () => {
        expect(
            reportYearsSparkBars([-100, -200, 0], columns, 2026).map(
                (bar) => bar.heightRatio,
            ),
        ).toEqual([0, 0, 0]);
    });
});
