import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    readYearElapsedMark,
    writeYearElapsedMark,
    YEAR_ELAPSED_MARK_KEY,
} from './year-elapsed-mark-storage';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('readYearElapsedMark', () => {
    it('uses the dashboard-year-elapsed key', () => {
        expect(YEAR_ELAPSED_MARK_KEY).toBe('dashboard-year-elapsed');
    });

    it('is on when nothing is stored', () => {
        expect(readYearElapsedMark({ getItem: () => null })).toBe(true);
    });

    it('is off only for the stored string false', () => {
        const getItem = vi.fn(() => 'false');

        expect(readYearElapsedMark({ getItem })).toBe(false);
        expect(getItem).toHaveBeenCalledWith(YEAR_ELAPSED_MARK_KEY);
    });

    it('is on for the stored string true', () => {
        expect(readYearElapsedMark({ getItem: () => 'true' })).toBe(true);
    });

    it('is on for a garbage value', () => {
        expect(readYearElapsedMark({ getItem: () => 'nope' })).toBe(true);
    });

    it('is on when there is no storage', () => {
        expect(readYearElapsedMark(null)).toBe(true);
    });

    it('is on and logs the cause when reading throws', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const error = new Error('blocked');

        const result = readYearElapsedMark({
            getItem: () => {
                throw error;
            },
        });

        expect(result).toBe(true);
        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn.mock.calls[0]).toContain(error);
    });
});

describe('writeYearElapsedMark', () => {
    it('stores false as the string false', () => {
        const setItem = vi.fn();

        writeYearElapsedMark(false, { setItem });

        expect(setItem).toHaveBeenCalledWith(YEAR_ELAPSED_MARK_KEY, 'false');
    });

    it('stores true as the string true', () => {
        const setItem = vi.fn();

        writeYearElapsedMark(true, { setItem });

        expect(setItem).toHaveBeenCalledWith(YEAR_ELAPSED_MARK_KEY, 'true');
    });

    it('does not throw and logs the cause when writing throws', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const error = new Error('quota');

        expect(() =>
            writeYearElapsedMark(false, {
                setItem: () => {
                    throw error;
                },
            }),
        ).not.toThrow();
        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn.mock.calls[0]).toContain(error);
    });
});
