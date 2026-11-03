import { describe, expect, it } from 'vite-plus/test';
import { formatSignedMoney } from './format';

describe('formatSignedMoney', () => {
    it('prefixes positive amounts with a plus', () => {
        expect(formatSignedMoney(123456)).toBe('+1 234,56 zł');
    });

    it('keeps the minus on negative amounts', () => {
        expect(formatSignedMoney(-5000)).toBe('-50,00 zł');
    });

    it('shows zero without a sign', () => {
        expect(formatSignedMoney(0)).toBe('0,00 zł');
    });
});
