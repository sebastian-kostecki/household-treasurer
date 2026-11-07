import { expect, test } from 'vite-plus/test';
import {
    executionClass,
    remainingClass,
    totalsRemainingClass,
} from './budget-category-cells';

test('archived rows never carry a status colour', () => {
    expect(executionClass('income', true)).toBe('');
    expect(remainingClass('expense', true, true)).toBe('');
});

test('active income execution is success, expense execution inherits', () => {
    expect(executionClass('income', false)).toBe('text-success');
    expect(executionClass('expense', false)).toBe('');
});

test('remaining is destructive only for an active expense over limit', () => {
    expect(remainingClass('expense', true, false)).toBe('text-destructive');
    expect(remainingClass('expense', false, false)).toBe(
        'text-muted-foreground',
    );
    expect(remainingClass('income', true, false)).toBe('text-muted-foreground');
});

test('negative expense totals are destructive', () => {
    expect(totalsRemainingClass('expense', -1)).toBe('text-destructive');
    expect(totalsRemainingClass('expense', 0)).toBe('text-muted-foreground');
    expect(totalsRemainingClass('income', -1)).toBe('text-muted-foreground');
});
