import { describe, expect, it } from 'vite-plus/test';
import { formatSignedMoney, parseMoneyInputToMinorUnits } from './format';

describe('parseMoneyInputToMinorUnits', () => {
    it('parses plain, spaced and signed amounts', () => {
        expect(parseMoneyInputToMinorUnits('1200,50')).toBe(120050);
        expect(parseMoneyInputToMinorUnits('1 234,56')).toBe(123456);
        expect(parseMoneyInputToMinorUnits('12,')).toBe(1200);
        expect(parseMoneyInputToMinorUnits('-50')).toBe(-5000);
    });

    it('rejects empty, malformed and multi-separator input', () => {
        expect(parseMoneyInputToMinorUnits('')).toBeNull();
        expect(parseMoneyInputToMinorUnits('-')).toBeNull();
        expect(parseMoneyInputToMinorUnits('12,3,4')).toBeNull();
        expect(parseMoneyInputToMinorUnits('1.234,56')).toBeNull();
    });

    it('rejects amounts beyond the safe integer range', () => {
        expect(parseMoneyInputToMinorUnits('9'.repeat(25))).toBeNull();
    });
});

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
