import { describe, expect, it } from 'vite-plus/test';
import { meterBarClass } from './dashboard-categories';

describe('meterBarClass', () => {
    it('uses foreground within the plan', () => {
        expect(meterBarClass('expense', 40, false)).toBe('bg-foreground');
        expect(meterBarClass('income', 100, false)).toBe('bg-foreground');
    });

    it('uses destructive for an expense over the limit', () => {
        expect(meterBarClass('expense', 120, true)).toBe('bg-destructive');
    });

    it('uses success for income above the plan', () => {
        expect(meterBarClass('income', 130, false)).toBe('bg-success');
    });

    it('uses muted without a percentage', () => {
        expect(meterBarClass('expense', null, false)).toBe('bg-muted');
    });
});
