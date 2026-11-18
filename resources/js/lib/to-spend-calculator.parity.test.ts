/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';
import type { Rhythm } from '../types/rhythm';
import { collectedChain } from './collected-calculator';
import type {
    CollectedMonth,
    CollectedMonthInput,
} from './collected-calculator';
import { toSpendBreakdown } from './to-spend-calculator';
import type { ToSpendItem } from './to-spend-calculator';

type CaseItem = {
    type: 'income' | 'expense';
    rhythm: Rhythm | null;
    plan: number;
    actual: number;
    has_positive_amount: boolean;
    collected_at_start: number;
};

type ParityCases = {
    format: number;
    items: Array<CaseItem & { name: string; expected: number }>;
    breakdowns: Array<{
        name: string;
        items: CaseItem[];
        set_aside: number;
        expected: { income: number; expense: number; to_spend: number };
    }>;
    chains: Array<{
        name: string;
        opening: number;
        months: CollectedMonthInput[];
        expected: CollectedMonth[];
    }>;
};

// Same file as tests/Unit/ToSpendCalculatorParityTest.php; values are derived by hand from the glossary.
const FIXTURE = join(
    dirname(fileURLToPath(import.meta.url)),
    '../../../tests/Fixtures/amount-invariants/to-spend-calculator-cases.json',
);

const cases = JSON.parse(readFileSync(FIXTURE, 'utf8')) as ParityCases;

function toItem(item: CaseItem): ToSpendItem {
    return {
        type: item.type,
        rhythm: item.rhythm,
        plan: item.plan,
        actual: item.actual,
        hasPositiveAmount: item.has_positive_amount,
        collectedAtStart: item.collected_at_start,
    };
}

describe('To Spend calculator parity table', () => {
    it('reads a fixture in format 1 with cases in every section', () => {
        expect(cases.format).toBe(1);
        expect(cases.items.length).toBeGreaterThan(0);
        expect(cases.breakdowns.length).toBeGreaterThan(0);
        expect(cases.chains.length).toBeGreaterThan(0);
    });

    describe('items', () => {
        for (const item of cases.items) {
            it(item.name, () => {
                const toSpend = toSpendBreakdown(
                    [toItem(item)],
                    0,
                ).toSpendMinorUnits;

                // `0 - x` instead of `-x`: negating 0 gives -0, which toBe rejects.
                const counted = item.type === 'income' ? toSpend : 0 - toSpend;

                expect(counted).toBe(item.expected);
            });
        }
    });

    describe('breakdowns', () => {
        for (const breakdown of cases.breakdowns) {
            it(breakdown.name, () => {
                expect(
                    toSpendBreakdown(
                        breakdown.items.map(toItem),
                        breakdown.set_aside,
                    ),
                ).toEqual({
                    incomeMinorUnits: breakdown.expected.income,
                    expenseMinorUnits: breakdown.expected.expense,
                    toSpendMinorUnits: breakdown.expected.to_spend,
                });
            });
        }
    });

    describe('chains', () => {
        for (const chain of cases.chains) {
            it(chain.name, () => {
                expect(collectedChain(chain.opening, chain.months)).toEqual(
                    chain.expected,
                );
            });
        }
    });
});
