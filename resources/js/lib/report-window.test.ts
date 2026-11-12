import { describe, expect, it } from 'vite-plus/test';
import type { ReportWindow } from '../types/reports';
import {
    reportCoverageNote,
    reportMonthNamesFrom,
    reportMonthRangeLabel,
    reportWindowLabel,
    reportWindowRange,
} from './report-window';

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

describe('reportMonthNamesFrom', () => {
    it('reads the twelve month names January first', () => {
        const names = reportMonthNamesFrom({
            month_name_1: 'styczeń',
            month_name_12: 'grudzień',
        });

        expect(names).toHaveLength(12);
        expect(names[0]).toBe('styczeń');
        expect(names[11]).toBe('grudzień');
        expect(names[5]).toBe('');
    });
});

describe('reportWindowRange', () => {
    it('carries the window dates and its label', () => {
        expect(
            reportWindowRange(
                { from: '2025-11-01', through: '2026-10-31' },
                monthNames,
                template,
            ),
        ).toEqual({
            from: '2025-11-01',
            through: '2026-10-31',
            label: 'listopad 2025 – październik 2026',
        });
    });
});

describe('reportCoverageNote', () => {
    const templates = {
        note: 'Bez budżetu w realizacji: :missing. Kwoty obejmują :covered (:count z 12 miesięcy).',
        range: template,
    };
    const window: ReportWindow = {
        from: '2025-11-01',
        through: '2026-10-31',
        covered_from: '2026-01-01',
        covered_through: '2026-10-31',
        covered_month_count: 10,
        missing_from: '2025-11-01',
        missing_through: '2025-12-31',
    };

    it('names the missing months at the start of the window', () => {
        expect(reportCoverageNote(window, monthNames, templates)).toBe(
            'Bez budżetu w realizacji: listopad 2025 – grudzień 2025. Kwoty obejmują styczeń 2026 – październik 2026 (10 z 12 miesięcy).',
        );
    });

    it('names the missing months at the end of the window', () => {
        expect(
            reportCoverageNote(
                {
                    ...window,
                    covered_from: '2025-11-01',
                    covered_through: '2025-12-31',
                    covered_month_count: 2,
                    missing_from: '2026-01-01',
                    missing_through: '2026-10-31',
                },
                monthNames,
                templates,
            ),
        ).toBe(
            'Bez budżetu w realizacji: styczeń 2026 – październik 2026. Kwoty obejmują listopad 2025 – grudzień 2025 (2 z 12 miesięcy).',
        );
    });

    it('collapses a one-month gap to one month', () => {
        expect(
            reportCoverageNote(
                {
                    from: '2026-01-01',
                    through: '2026-12-31',
                    covered_from: '2026-01-01',
                    covered_through: '2026-11-30',
                    covered_month_count: 11,
                    missing_from: '2026-12-01',
                    missing_through: '2026-12-31',
                },
                monthNames,
                templates,
            ),
        ).toBe(
            'Bez budżetu w realizacji: grudzień 2026. Kwoty obejmują styczeń 2026 – listopad 2026 (11 z 12 miesięcy).',
        );
    });

    it('is null with full or no coverage', () => {
        expect(
            reportCoverageNote(
                {
                    ...window,
                    covered_from: '2025-11-01',
                    covered_month_count: 12,
                    missing_from: null,
                    missing_through: null,
                },
                monthNames,
                templates,
            ),
        ).toBeNull();
        expect(
            reportCoverageNote(
                {
                    ...window,
                    covered_from: null,
                    covered_through: null,
                    covered_month_count: 0,
                    missing_from: '2025-11-01',
                    missing_through: '2026-10-31',
                },
                monthNames,
                templates,
            ),
        ).toBeNull();
    });

    it('fills every placeholder literally, without reading replacement patterns', () => {
        const names = monthNames.map((name) =>
            name === 'listopad' ? '$&listopad' : name,
        );

        expect(
            reportCoverageNote(window, names, {
                note: ':missing | :count | :count',
                range: template,
            }),
        ).toBe('$&listopad 2025 – grudzień 2025 | 10 | 10');
    });
});
