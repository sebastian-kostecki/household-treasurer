import { describe, expect, it } from 'vite-plus/test';
import { reportMonthRangeLabel, reportWindowLabel } from './report-window';

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
const template = ':from – :through';

describe('reportWindowLabel', () => {
    it('names the window across the turn of the year', () => {
        expect(
            reportWindowLabel(
                { from: '2025-11-01', through: '2026-10-31' },
                monthNames,
                template,
            ),
        ).toBe('listopad 2025 – październik 2026');
    });

    it('names a window that is the calendar year', () => {
        expect(
            reportWindowLabel(
                { from: '2026-01-01', through: '2026-12-31' },
                monthNames,
                template,
            ),
        ).toBe('styczeń 2026 – grudzień 2026');
    });
});

describe('reportMonthRangeLabel', () => {
    it('collapses a one-month stretch to a single label', () => {
        expect(
            reportMonthRangeLabel(
                '2025-12-01',
                '2025-12-31',
                monthNames,
                template,
            ),
        ).toBe('grudzień 2025');
    });

    it('names a stretch of several months', () => {
        expect(
            reportMonthRangeLabel(
                '2025-11-01',
                '2025-12-31',
                monthNames,
                template,
            ),
        ).toBe('listopad 2025 – grudzień 2025');
    });
});
