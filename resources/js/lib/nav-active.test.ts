import { describe, expect, it } from 'vite-plus/test';
import { isCurrentSection } from './nav-active';

describe('isCurrentSection', () => {
    it('matches nested paths of the section', () => {
        expect(isCurrentSection('/operations', '/operations/import')).toBe(
            true,
        );
        expect(
            isCurrentSection('/operations', '/operations/import/preview'),
        ).toBe(true);
    });

    it('does not match a path that only shares a prefix', () => {
        expect(isCurrentSection('/budget', '/budgets/1')).toBe(false);
    });

    it('matches the exact path', () => {
        expect(isCurrentSection('/budget', '/budget')).toBe(true);
    });

    it('ignores trailing slashes', () => {
        expect(isCurrentSection('/operations', '/operations/')).toBe(true);
        expect(isCurrentSection('/operations/', '/operations')).toBe(true);
    });

    it('uses the pathname of an absolute href', () => {
        expect(isCurrentSection('http://localhost/accounts', '/accounts')).toBe(
            true,
        );
    });

    it('does not match the root for another section', () => {
        expect(isCurrentSection('/dashboard', '/')).toBe(false);
    });
});
