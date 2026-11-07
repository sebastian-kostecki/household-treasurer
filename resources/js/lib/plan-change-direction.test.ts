import { describe, expect, it } from 'vite-plus/test';
import {
    planChangeDirection,
    planChangeToneClass,
} from './plan-change-direction';

describe('planChangeDirection', () => {
    it('marks a higher expense plan as unfavorable', () => {
        expect(planChangeDirection(100, 150, 'expense')).toEqual({
            direction: 'up',
            tone: 'unfavorable',
        });
    });

    it('marks a lower expense plan as favorable', () => {
        expect(planChangeDirection(150, 100, 'expense')).toEqual({
            direction: 'down',
            tone: 'favorable',
        });
    });

    it('marks a higher income plan as favorable', () => {
        expect(planChangeDirection(100, 150, 'income')).toEqual({
            direction: 'up',
            tone: 'favorable',
        });
    });

    it('marks a lower income plan as unfavorable', () => {
        expect(planChangeDirection(150, 100, 'income')).toEqual({
            direction: 'down',
            tone: 'unfavorable',
        });
    });

    it('returns null when the plan did not change', () => {
        expect(planChangeDirection(100, 100, 'expense')).toBeNull();
        expect(planChangeDirection(0, 0, 'income')).toBeNull();
    });
});

describe('planChangeToneClass', () => {
    it('maps the tone to a colour utility', () => {
        expect(planChangeToneClass('favorable', false)).toBe('text-success');
        expect(planChangeToneClass('unfavorable', false)).toBe(
            'text-destructive',
        );
    });

    it('lets muted win over the tone', () => {
        expect(planChangeToneClass('favorable', true)).toBe(
            'text-muted-foreground',
        );
        expect(planChangeToneClass('unfavorable', true)).toBe(
            'text-muted-foreground',
        );
    });
});
