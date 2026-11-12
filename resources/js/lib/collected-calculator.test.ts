import { expect, test } from 'vite-plus/test';
import {
    collectedAtEndMinorUnits,
    collectedChain,
    collectedSurplusMinorUnits,
} from './collected-calculator';

test('a bill below the Collected plus the rate leaves the rest and no surplus (US-02)', () => {
    expect(collectedAtEndMinorUnits(30_000, 15_000, 40_000)).toBe(5_000);
    expect(collectedSurplusMinorUnits(30_000, 15_000, 40_000)).toBe(0);
});

test('a bill above the Collected plus the rate empties it and leaves the surplus (US-02)', () => {
    expect(collectedAtEndMinorUnits(30_000, 15_000, 60_000)).toBe(0);
    expect(collectedSurplusMinorUnits(30_000, 15_000, 60_000)).toBe(15_000);
});

test('a refund adds to the Collected', () => {
    expect(collectedAtEndMinorUnits(5_000, 15_000, -5_000)).toBe(25_000);
    expect(collectedSurplusMinorUnits(5_000, 15_000, -5_000)).toBe(0);
});

test('the July example of ADR-0066: bills in March and May', () => {
    const months = [0, 0, 40_000, 0, 40_000, 0].map((actual) => ({
        rate: 15_000,
        actual,
    }));
    const chain = collectedChain(0, months);

    expect(chain[4].surplus).toBe(5_000);
    expect(chain[4].end).toBe(0);
    expect(chain[5].end).toBe(15_000);
});

test('the floor applies in every month of the chain', () => {
    const chain = collectedChain(0, [
        { rate: 10_000, actual: 50_000 },
        { rate: 10_000, actual: 0 },
    ]);

    expect(chain).toEqual([
        { start: 0, end: 0, surplus: 40_000 },
        { start: 0, end: 10_000, surplus: 0 },
    ]);
});

test('an empty chain has no months', () => {
    expect(collectedChain(30_000, [])).toEqual([]);
});
