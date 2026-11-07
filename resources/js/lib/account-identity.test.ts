import { describe, expect, it } from 'vite-plus/test';
import {
    accountAvatarContent,
    accountShortMark,
    bankLabel,
    bankOptions,
} from './account-identity';

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

describe('accountAvatarContent', () => {
    it('shows the logo of a bank with a mark_url', () => {
        expect(
            accountAvatarContent(
                { type: 'bank', bank: 'mbank', mark_url: '/marks/mbank.jpg' },
                false,
            ),
        ).toEqual({ kind: 'logo', src: '/marks/mbank.jpg' });
    });

    it('shows zł for cash even with a stray mark_url', () => {
        expect(
            accountAvatarContent(
                { type: 'cash', bank: null, mark_url: '/marks/bogus.svg' },
                false,
            ),
        ).toEqual({ kind: 'mark', text: 'zł' });
    });

    it('shows the icon for other and for a missing bank', () => {
        expect(
            accountAvatarContent(
                { type: 'bank', bank: 'other', mark_url: null },
                false,
            ),
        ).toEqual({ kind: 'icon' });
        expect(
            accountAvatarContent(
                { type: 'bank', bank: null, mark_url: null },
                false,
            ),
        ).toEqual({ kind: 'icon' });
    });

    it('shows the short mark of a bank without a logo', () => {
        expect(
            accountAvatarContent(
                { type: 'bank', bank: 'ing', mark_url: null },
                false,
            ),
        ).toEqual({ kind: 'mark', text: 'ING' });
    });

    it('falls back to the short mark, not the icon, when the logo fails', () => {
        expect(
            accountAvatarContent(
                { type: 'bank', bank: 'pko_bp', mark_url: '/marks/pko-bp.svg' },
                true,
            ),
        ).toEqual({ kind: 'mark', text: 'PKO' });
    });
});
