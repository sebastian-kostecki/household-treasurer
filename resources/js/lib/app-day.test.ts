import { describe, expect, it } from 'vite-plus/test';
import { appDayMonth, appDayMonthRange, appDayYear } from './app-day';

describe('appDayYear and appDayMonth', () => {
    it('read the year and the month of the App Day', () => {
        expect(appDayYear('2027-01-01')).toBe(2027);
        expect(appDayMonth('2027-01-01')).toBe(1);
        expect(appDayMonth('2026-12-31')).toBe(12);
    });
});

describe('appDayMonthRange', () => {
    it('gives the whole current month', () => {
        expect(appDayMonthRange('2026-11-19', 0)).toEqual({
            from: '2026-11-01',
            to: '2026-11-30',
        });
    });

    it('crosses the year boundary for the previous month in January', () => {
        expect(appDayMonthRange('2027-01-15', -1)).toEqual({
            from: '2026-12-01',
            to: '2026-12-31',
        });
    });

    it('counts 29 days in February of a leap year', () => {
        expect(appDayMonthRange('2028-02-10', 0)).toEqual({
            from: '2028-02-01',
            to: '2028-02-29',
        });
        expect(appDayMonthRange('2027-02-10', 0).to).toBe('2027-02-28');
    });

    it('moves forward over the year end', () => {
        expect(appDayMonthRange('2026-12-31', 1)).toEqual({
            from: '2027-01-01',
            to: '2027-01-31',
        });
    });
});
