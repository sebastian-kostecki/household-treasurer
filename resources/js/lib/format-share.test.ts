import { describe, expect, it } from 'vite-plus/test';
import { formatShareTenths } from './format-share';

describe('formatShareTenths', () => {
    it('formats tenths of a percent with a decimal comma', () => {
        expect(formatShareTenths(333)).toBe('33,3%');
        expect(formatShareTenths(1000)).toBe('100,0%');
        expect(formatShareTenths(0)).toBe('0,0%');
        expect(formatShareTenths(5)).toBe('0,5%');
    });

    it('marks a negative share with a minus sign', () => {
        expect(formatShareTenths(-133)).toBe('−13,3%');
        expect(formatShareTenths(-1)).toBe('−0,1%');
    });

    it('shows a dash when there is no share', () => {
        expect(formatShareTenths(null)).toBe('—');
    });
});
