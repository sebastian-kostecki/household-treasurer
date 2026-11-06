import { describe, expect, it } from 'vite-plus/test';
import { formatMoney } from './format';
import { importRowAmount } from './import-row-amount';

describe('importRowAmount', () => {
    it.each([6400, -6400])(
        'shows income as a positive green amount for %i',
        (amount) => {
            expect(importRowAmount('income', amount)).toEqual({
                text: '+64,00 zł',
                className: 'text-success',
            });
        },
    );

    it.each([6400, -6400])(
        'shows expense as a negative neutral amount for %i',
        (amount) => {
            expect(importRowAmount('expense', amount)).toEqual({
                text: '−64,00 zł',
                className: 'text-foreground',
            });
        },
    );

    it.each([6400, -6400])(
        'shows refund as a positive green amount for %i',
        (amount) => {
            expect(importRowAmount('refund', amount)).toEqual({
                text: '+64,00 zł',
                className: 'text-success',
            });
        },
    );

    it.each(['own_transfer', 'capital_movement'] as const)(
        'keeps the sign from the file for %s',
        (type) => {
            expect(importRowAmount(type, 6400)).toEqual({
                text: '64,00 zł',
                className: 'text-foreground',
            });
            expect(importRowAmount(type, -6400)).toEqual({
                text: formatMoney(-6400),
                className: 'text-foreground',
            });
            expect(importRowAmount(type, -6400).text).toMatch(/^-64,00/);
        },
    );
});
