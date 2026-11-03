import { expect, test } from 'vite-plus/test';
import {
    MAX_PLAN_AMOUNT_MINOR_UNITS,
    parsePlanAmountInput,
} from './month-plan-amount';

test('empty input is invalid', () => {
    expect(parsePlanAmountInput('')).toBeNull();
});

test('a trailing comma is read as whole units', () => {
    expect(parsePlanAmountInput('12,')).toBe(1200);
});

test('negative amounts are invalid', () => {
    expect(parsePlanAmountInput('-1')).toBeNull();
});

test('the upper bound is accepted and one above it is not', () => {
    expect(parsePlanAmountInput('10000000000')).toBe(
        MAX_PLAN_AMOUNT_MINOR_UNITS,
    );
    expect(parsePlanAmountInput('10000000000,01')).toBeNull();
});

test('a second separator is invalid', () => {
    expect(parsePlanAmountInput('12,3,4')).toBeNull();
});
