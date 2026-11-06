import { describe, expect, it } from 'vite-plus/test';
import { accountShortMark, bankLabel, bankOptions } from './account-identity';

describe('bankLabel', () => {
    it('reads the bank name from the accounts translations', () => {
        expect(bankLabel({ bank_mbank: 'mBank' }, 'mbank')).toBe('mBank');
    });

    it('falls back to the enum value when the translation is missing', () => {
        expect(bankLabel({}, 'pko_bp')).toBe('pko_bp');
    });
});

describe('accountShortMark', () => {
    it('returns zł for cash', () => {
        expect(accountShortMark({ type: 'cash', bank: null })).toBe('zł');
    });

    it('returns the short mark of a bank', () => {
        expect(accountShortMark({ type: 'bank', bank: 'mbank' })).toBe('mB');
        expect(accountShortMark({ type: 'bank', bank: 'pko_bp' })).toBe('PKO');
    });

    it('returns null for other and for a missing bank', () => {
        expect(accountShortMark({ type: 'bank', bank: 'other' })).toBeNull();
        expect(accountShortMark({ type: 'bank', bank: null })).toBeNull();
    });

    it('gives every listed bank except other a mark of 1 to 3 characters', () => {
        for (const bank of bankOptions.filter((value) => value !== 'other')) {
            const mark = accountShortMark({ type: 'bank', bank });

            expect(mark).not.toBeNull();
            expect(mark!.length).toBeGreaterThanOrEqual(1);
            expect(mark!.length).toBeLessThanOrEqual(3);
        }
    });
});
