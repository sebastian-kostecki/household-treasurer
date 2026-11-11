import { describe, expect, it } from 'vite-plus/test';
import { reportDifferenceTone } from './report-difference';

describe('reportDifferenceTone', () => {
    it('treats lower expenses as favorable and higher as unfavorable', () => {
        expect(reportDifferenceTone(-647, 'expense')).toBe('favorable');
        expect(reportDifferenceTone(657, 'expense')).toBe('unfavorable');
    });

    it('treats higher income as favorable and lower as unfavorable', () => {
        expect(reportDifferenceTone(3000, 'income')).toBe('favorable');
        expect(reportDifferenceTone(-1, 'income')).toBe('unfavorable');
    });

    it('treats a higher result as favorable', () => {
        expect(reportDifferenceTone(4100, 'result')).toBe('favorable');
        expect(reportDifferenceTone(-4100, 'result')).toBe('unfavorable');
    });

    it('has no tone for zero or a missing difference', () => {
        expect(reportDifferenceTone(0, 'expense')).toBeNull();
        expect(reportDifferenceTone(null, 'income')).toBeNull();
    });
});
