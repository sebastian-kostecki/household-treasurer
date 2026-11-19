import { describe, expect, it } from 'vite-plus/test';
import { authYearProgress } from './auth-year-progress';

describe('authYearProgress', () => {
    it('marks January 1st as the current month with the rest in the future', () => {
        const progress = authYearProgress('2026-01-01');

        expect(progress.year).toBe(2026);
        expect(progress.elapsedPercent).toBe(0);
        expect(progress.months).toEqual([
            'current',
            ...Array<'future'>(11).fill('future'),
        ]);
    });

    it('reaches 100 percent on December 31st with eleven past months', () => {
        const progress = authYearProgress('2026-12-31');

        expect(progress.elapsedPercent).toBe(100);
        expect(progress.months.slice(0, 11)).toEqual(
            Array<'past'>(11).fill('past'),
        );
        expect(progress.months[11]).toBe('current');
    });

    it('counts a leap-year February 29th as day 60 of 366', () => {
        const progress = authYearProgress('2028-02-29');

        expect(progress.elapsedPercent).toBe(16);
        expect(progress.months[0]).toBe('past');
        expect(progress.months[1]).toBe('current');
        expect(progress.months[2]).toBe('future');
    });

    it('switches the year and the month on the day it is given', () => {
        const lastDay = authYearProgress('2026-12-31');
        const firstDay = authYearProgress('2027-01-01');

        expect(lastDay.year).toBe(2026);
        expect(firstDay.year).toBe(2027);
        expect(firstDay.elapsedPercent).toBe(0);
        expect(firstDay.months[0]).toBe('current');
        expect(authYearProgress('2026-06-30').months[5]).toBe('current');
        expect(authYearProgress('2026-07-01').months[5]).toBe('past');
    });

    it('keeps the percentage whole across a daylight-saving change', () => {
        const progress = authYearProgress('2026-03-29');

        expect(Number.isInteger(progress.elapsedPercent)).toBe(true);
        expect(progress.elapsedPercent).toBe(24);
    });
});
