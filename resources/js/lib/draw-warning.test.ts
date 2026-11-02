import { expect, test } from 'vite-plus/test';
import { drawExceedsSpendableLiquidity } from './draw-warning';

test('a draw equal to the liquidity does not warn', () => {
    expect(drawExceedsSpendableLiquidity(-30_000, 30_000)).toBe(false);
});

test('a draw above the liquidity warns', () => {
    expect(drawExceedsSpendableLiquidity(-30_001, 30_000)).toBe(true);
});

test('setting aside never warns', () => {
    expect(drawExceedsSpendableLiquidity(50_000, -10)).toBe(false);
});

test('any draw warns when the liquidity is negative', () => {
    expect(drawExceedsSpendableLiquidity(-1, -500)).toBe(true);
});

test('zero never warns', () => {
    expect(drawExceedsSpendableLiquidity(0, -500)).toBe(false);
});

test('an unknown liquidity does not warn', () => {
    expect(drawExceedsSpendableLiquidity(-30_001, null)).toBe(false);
});
