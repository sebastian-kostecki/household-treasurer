import { describe, expect, it } from 'vite-plus/test';
import {
    monthItemDeviationTone,
    monthSummaryDeviationTone,
} from './month-plan-deviation-tone';

const base = {
    deviation: 0 as number | null,
    overPlan: false,
    type: 'expense' as 'expense' | 'income',
    isArchived: false,
};

describe('monthItemDeviationTone', () => {
    it('mutes an unreadable deviation', () => {
        expect(monthItemDeviationTone({ ...base, deviation: null })).toBe(
            'text-muted-foreground',
        );
    });

    it('leaves an archived line without tone, even over plan', () => {
        expect(
            monthItemDeviationTone({
                ...base,
                deviation: 5000,
                overPlan: true,
                isArchived: true,
            }),
        ).toBe('');
    });

    it('marks an over-plan row destructive, also with a negative deviation of the month', () => {
        expect(
            monthItemDeviationTone({
                ...base,
                deviation: 5000,
                overPlan: true,
            }),
        ).toBe('text-destructive');
        expect(
            monthItemDeviationTone({
                ...base,
                deviation: -5000,
                overPlan: true,
            }),
        ).toBe('text-destructive');
    });

    it('marks an expense below plan success', () => {
        expect(monthItemDeviationTone({ ...base, deviation: -12000 })).toBe(
            'text-success',
        );
    });

    it('leaves an expense with a non-negative deviation, not over plan, without tone', () => {
        expect(monthItemDeviationTone({ ...base, deviation: 2166700 })).toBe(
            '',
        );
        expect(monthItemDeviationTone({ ...base, deviation: 0 })).toBe('');
    });

    it('marks income above plan success', () => {
        expect(
            monthItemDeviationTone({ ...base, type: 'income', deviation: 100 }),
        ).toBe('text-success');
    });

    it('leaves income at or below plan without tone', () => {
        expect(
            monthItemDeviationTone({ ...base, type: 'income', deviation: 0 }),
        ).toBe('');
        expect(
            monthItemDeviationTone({
                ...base,
                type: 'income',
                deviation: -100,
            }),
        ).toBe('');
    });
});

describe('monthSummaryDeviationTone', () => {
    it('reads an expense by benefit', () => {
        expect(monthSummaryDeviationTone('expense', -1)).toBe('text-success');
        expect(monthSummaryDeviationTone('expense', 1)).toBe(
            'text-destructive',
        );
    });

    it('reads income and set aside by sign', () => {
        for (const key of ['income', 'set_aside'] as const) {
            expect(monthSummaryDeviationTone(key, 1)).toBe('text-success');
            expect(monthSummaryDeviationTone(key, -1)).toBe('text-destructive');
        }
    });

    it('leaves zero without tone', () => {
        for (const key of ['income', 'expense', 'set_aside'] as const) {
            expect(monthSummaryDeviationTone(key, 0)).toBe('');
        }
    });
});
