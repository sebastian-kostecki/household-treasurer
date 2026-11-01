import { expect, test } from 'vite-plus/test';
import {
    pacedMonthRemainingMinorUnits,
    pacedPoolMonthRemainingMinorUnits,
} from './month-remaining-calculator';
import type { MonthRemainingCalculatorOptions } from './month-remaining-calculator';

const executionOptions: MonthRemainingCalculatorOptions = {
    isDraft: false,
    budgetYear: 2026,
    today: '2026-06-15',
};

const draftOptions: MonthRemainingCalculatorOptions = {
    ...executionOptions,
    isDraft: true,
};

test('draft splits paced current plan into twelfths', () => {
    expect(
        pacedMonthRemainingMinorUnits(draftOptions, 120_000, 0, 0, 3, 0, 0),
    ).toBe(10_000);
    expect(
        pacedMonthRemainingMinorUnits(draftOptions, 120_000, 0, 0, 12, 0, 0),
    ).toBe(10_000);
});

test('draft puts the remainder of the division in december', () => {
    expect(
        pacedMonthRemainingMinorUnits(draftOptions, 120_005, 0, 0, 11, 0, 0),
    ).toBe(10_000);
    expect(
        pacedMonthRemainingMinorUnits(draftOptions, 120_005, 0, 0, 12, 0, 0),
    ).toBe(10_005);
});

test('execution catch up subtracts paced execution in the viewed month', () => {
    expect(
        pacedMonthRemainingMinorUnits(
            executionOptions,
            120_000,
            25_000,
            10_000,
            3,
            0,
            0,
        ),
    ).toBe(-500);
});

test('execution floors leftover toward negative infinity', () => {
    expect(
        pacedMonthRemainingMinorUnits(executionOptions, 0, 7, 0, 1, 0, 0),
    ).toBe(-1);
});

test('execution december uses months left of one', () => {
    expect(
        pacedMonthRemainingMinorUnits(
            { ...executionOptions, today: '2026-12-20' },
            120_000,
            110_000,
            5_000,
            12,
            110_000,
            5_000,
        ),
    ).toBe(5_000);
});

test('execution past budget year uses the viewed month', () => {
    expect(
        pacedMonthRemainingMinorUnits(
            { ...executionOptions, budgetYear: 2025 },
            120_000,
            20_000,
            2_000,
            3,
            90_000,
            1_000,
        ),
    ).toBe(8_000);
});

test('execution future month shows current month plaster', () => {
    expect(
        pacedMonthRemainingMinorUnits(
            { ...executionOptions, today: '2026-03-15' },
            120_000,
            0,
            0,
            8,
            20_000,
            2_000,
        ),
    ).toBe(8_000);
});

test('execution future budget year uses even plaster without catch up', () => {
    expect(
        pacedMonthRemainingMinorUnits(
            { ...executionOptions, budgetYear: 2027 },
            120_000,
            0,
            0,
            5,
            0,
            0,
        ),
    ).toBe(10_000);
});

test('paced pool floors summed leftover once', () => {
    const categoryRemaining = pacedMonthRemainingMinorUnits(
        executionOptions,
        50_001,
        0,
        0,
        1,
        0,
        0,
    );
    const poolRemaining = pacedPoolMonthRemainingMinorUnits(
        executionOptions,
        100_002,
        0,
        0,
        1,
        0,
        0,
        true,
    );

    expect(categoryRemaining + categoryRemaining).not.toBe(poolRemaining);
    expect(poolRemaining).toBe(8_333);
});

test('paced pool follows draft and future month rules', () => {
    expect(
        pacedPoolMonthRemainingMinorUnits(
            draftOptions,
            120_005,
            0,
            0,
            12,
            0,
            0,
            true,
        ),
    ).toBe(10_005);
    expect(
        pacedPoolMonthRemainingMinorUnits(
            executionOptions,
            120_000,
            0,
            0,
            9,
            35_000,
            1_000,
            true,
        ),
    ).toBe(11_142);
});

test('empty paced pool month remaining is zero', () => {
    expect(
        pacedPoolMonthRemainingMinorUnits(
            executionOptions,
            0,
            0,
            0,
            6,
            0,
            0,
            false,
        ),
    ).toBe(0);
});
