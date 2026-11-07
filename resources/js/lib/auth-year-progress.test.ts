import { describe, expect, it } from 'vite-plus/test';
import { authYearProgress } from './auth-year-progress';

describe('authYearProgress', () => {
    it('marks January 1st as the current month with the rest in the future', () => {
        const progress = authYearProgress(new Date(2026, 0, 1));

        expect(progress.year).toBe(2026);
        expect(progress.elapsedPercent).toBe(0);
        expect(progress.months).toEqual([
            'current',
            ...Array<'future'>(11).fill('future'),
        ]);
    });

    it('reaches 100 percent on December 31st with eleven past months', () => {
        const progress = authYearProgress(new Date(2026, 11, 31));

        expect(progress.elapsedPercent).toBe(100);
        expect(progress.months.slice(0, 11)).toEqual(
            Array<'past'>(11).fill('past'),
        );
        expect(progress.months[11]).toBe('current');
    });

    it('counts a leap-year February 29th as day 60 of 366', () => {
        const progress = authYearProgress(new Date(2028, 1, 29));

        expect(progress.elapsedPercent).toBe(16);
        expect(progress.months[0]).toBe('past');
        expect(progress.months[1]).toBe('current');
        expect(progress.months[2]).toBe('future');
    });

    it('uses local time around midnight, not UTC', () => {
        const lateEvening = authYearProgress(new Date(2026, 5, 30, 23, 59, 59));
        const justAfter = authYearProgress(new Date(2026, 6, 1, 0, 0, 1));

        expect(lateEvening.months[5]).toBe('current');
        expect(justAfter.months[5]).toBe('past');
        expect(justAfter.months[6]).toBe('current');
    });

    it('keeps the percentage whole across a daylight-saving change', () => {
        const progress = authYearProgress(new Date(2026, 2, 29, 12, 0, 0));

        expect(Number.isInteger(progress.elapsedPercent)).toBe(true);
        expect(progress.elapsedPercent).toBe(24);
    });
});
