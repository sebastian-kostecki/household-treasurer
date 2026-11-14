import { describe, expect, it } from 'vite-plus/test';
import { foldDiacritics, matchesSearch, normalizeSearch } from './text-search';

describe('foldDiacritics', () => {
    it('removes Polish diacritics including ł and Ł', () => {
        expect(foldDiacritics('Żywność')).toBe('Zywnosc');
        expect(foldDiacritics('Łódź gęś')).toBe('Lodz ges');
    });
});

describe('normalizeSearch', () => {
    it('folds, lowercases and trims', () => {
        expect(normalizeSearch('  ŁÓDŹ ')).toBe('lodz');
    });
});

describe('matchesSearch', () => {
    it('matches without diacritics typed', () => {
        expect(matchesSearch('prad', ['Prąd'])).toBe(true);
        expect(matchesSearch('ges', ['Gęś'])).toBe(true);
    });

    it('matches ł and Ł both ways', () => {
        expect(matchesSearch('ŁÓDŹ', ['lodz'])).toBe(true);
        expect(matchesSearch('lodz', ['Łódź'])).toBe(true);
        expect(matchesSearch('łódź', ['LODZ'])).toBe(true);
    });

    it('ignores case', () => {
        expect(matchesSearch('CZYNSZ', ['czynsz za mieszkanie'])).toBe(true);
    });

    it('matches everything for an empty or blank query', () => {
        expect(matchesSearch('', ['Prąd'])).toBe(true);
        expect(matchesSearch('   ', [null])).toBe(true);
    });

    it('skips null texts and matches any other text', () => {
        expect(matchesSearch('rata', [null, 'Rata kredytu'])).toBe(true);
        expect(matchesSearch('rata', [null])).toBe(false);
        expect(matchesSearch('rata', [])).toBe(false);
    });

    it('does not match an absent phrase', () => {
        expect(matchesSearch('woda', ['Prąd', 'Gaz'])).toBe(false);
    });
});
