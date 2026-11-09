import { describe, expect, it } from 'vite-plus/test';
import { MAX_PLAN_AMOUNT_MINOR_UNITS } from './month-plan-amount';
import {
    changeModeAmountInputs,
    matchActualState,
    raisesToActualOnChange,
} from './month-plan-match-actual';
import type { MonthPlanItem } from '@/types/month-plan';

function row(overrides: Partial<MonthPlanItem> = {}): MonthPlanItem {
    return {
        category_id: 1,
        list_category_id: 1,
        name: 'Jedzenie',
        icon: 'utensils',
        color: 'brand',
        type: 'expense',
        month_item_kind: 'running',
        is_one_off: false,
        is_archived: false,
        plan_minor_units: 10000,
        actual_minor_units: 15000,
        has_positive_amount: true,
        can_change_kind: true,
        in_plan: true,
        deviation_minor_units: 5000,
        is_over_plan: true,
        is_settled: false,
        available_limit_minor_units: null,
        is_idle_one_off: false,
        approved_minor_units: 10000,
        is_join_candidate: false,
        year_comparison: null,
        ...overrides,
    };
}

describe('raisesToActualOnChange', () => {
    it('raises an in-plan expense whose actual exceeds the plan', () => {
        expect(raisesToActualOnChange(row())).toBe(true);
    });

    it('does not raise when actual equals or is below the plan', () => {
        expect(raisesToActualOnChange(row({ actual_minor_units: 10000 }))).toBe(
            false,
        );
        expect(raisesToActualOnChange(row({ actual_minor_units: -500 }))).toBe(
            false,
        );
    });

    it('does not raise income, candidates or archived items', () => {
        expect(raisesToActualOnChange(row({ type: 'income' }))).toBe(false);
        expect(raisesToActualOnChange(row({ in_plan: false }))).toBe(false);
        expect(raisesToActualOnChange(row({ is_archived: true }))).toBe(false);
    });

    it('does not raise above the field limit', () => {
        expect(
            raisesToActualOnChange(
                row({ actual_minor_units: MAX_PLAN_AMOUNT_MINOR_UNITS + 1 }),
            ),
        ).toBe(false);
        expect(
            raisesToActualOnChange(
                row({ actual_minor_units: MAX_PLAN_AMOUNT_MINOR_UNITS }),
            ),
        ).toBe(true);
    });
});

describe('changeModeAmountInputs', () => {
    it('starts raised rows at the actual amount and the rest at the plan', () => {
        expect(
            changeModeAmountInputs([
                row({ category_id: 1 }),
                row({ category_id: 2, actual_minor_units: 5000 }),
                row({ category_id: 3, in_plan: false, plan_minor_units: 0 }),
            ]),
        ).toEqual({ 1: '150,00', 2: '100,00', 3: '0,00' });
    });
});

describe('matchActualState', () => {
    it('offers the button after the field is lowered below actual', () => {
        expect(matchActualState(row(), 12000, true)).toBe('offer');
    });

    it('offers the button to a candidate with positive actual', () => {
        const candidate = row({ in_plan: false, plan_minor_units: 0 });

        expect(matchActualState(candidate, 0, true)).toBe('offer');
    });

    it('offers the button for an archived item in the plan', () => {
        expect(matchActualState(row({ is_archived: true }), 0, true)).toBe(
            'offer',
        );
    });

    it('offers nothing for an unreadable amount, no field or income', () => {
        expect(matchActualState(row(), null, true)).toBeNull();
        expect(matchActualState(row(), 0, false)).toBeNull();
        expect(matchActualState(row({ type: 'income' }), 0, true)).toBeNull();
    });

    it('offers nothing when actual is at or below the field', () => {
        expect(matchActualState(row(), 15000, true)).not.toBe('offer');
        expect(
            matchActualState(row({ actual_minor_units: -300 }), 0, true),
        ).toBeNull();
    });

    it('offers nothing when actual exceeds the field limit', () => {
        const huge = row({
            actual_minor_units: MAX_PLAN_AMOUNT_MINOR_UNITS + 1,
        });

        expect(matchActualState(huge, 0, true)).toBeNull();
    });

    it('marks a raised row, also after typing the same amount by hand', () => {
        expect(matchActualState(row(), 15000, true)).toBe('raised');
        expect(matchActualState(row({ in_plan: false }), 15000, true)).toBe(
            'raised',
        );
    });

    it('does not mark when actual does not exceed the saved plan', () => {
        expect(
            matchActualState(row({ actual_minor_units: 10000 }), 10000, true),
        ).toBeNull();
    });
});
