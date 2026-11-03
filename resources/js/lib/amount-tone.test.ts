import { describe, expect, it } from 'vite-plus/test';
import { amountToneClass } from './amount-tone';

describe('amountToneClass', () => {
    it('marks positive amounts as success', () => {
        expect(amountToneClass(1)).toBe('text-success');
    });

    it('leaves zero neutral', () => {
        expect(amountToneClass(0)).toBe('');
    });

    it('marks negative amounts as destructive', () => {
        expect(amountToneClass(-1)).toBe('text-destructive');
        expect(amountToneClass(-987654321)).toBe('text-destructive');
    });
});
