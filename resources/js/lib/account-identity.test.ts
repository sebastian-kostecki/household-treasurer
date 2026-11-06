import { describe, expect, it } from 'vite-plus/test';
import { bankLabel } from './account-identity';

describe('bankLabel', () => {
    it('reads the bank name from the accounts translations', () => {
        expect(bankLabel({ bank_mbank: 'mBank' }, 'mbank')).toBe('mBank');
    });

    it('falls back to the enum value when the translation is missing', () => {
        expect(bankLabel({}, 'pko_bp')).toBe('pko_bp');
    });
});
