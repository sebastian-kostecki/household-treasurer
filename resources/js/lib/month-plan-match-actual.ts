import { minorUnitsToMoneyInput } from './format';
import { MAX_PLAN_AMOUNT_MINOR_UNITS } from './month-plan-amount';
import type { MonthPlanItem } from '@/types/month-plan';

export type MatchActualState = 'offer' | 'raised' | null;

/**
 * An in-plan, non-archived expense row whose actual amount exceeds the saved
 * plan and fits the plan field (ADR-0062). Change mode starts it raised. An
 * irregular row is the exception: its rate is not raised to the bill, the
 * Collected covers it (ADR-0066).
 */
export function raisesToActualOnChange(row: MonthPlanItem): boolean {
    return (
        row.type === 'expense' &&
        row.rhythm !== 'irregular' &&
        row.in_plan &&
        !row.is_archived &&
        row.actual_minor_units > row.plan_minor_units &&
        row.actual_minor_units <= MAX_PLAN_AMOUNT_MINOR_UNITS
    );
}

/** Starting amounts of change mode: the saved plan, except raised rows, which start at the actual amount. */
export function changeModeAmountInputs(
    rows: MonthPlanItem[],
): Record<number, string> {
    return Object.fromEntries(
        rows.map((row) => [
            row.category_id,
            minorUnitsToMoneyInput(
                raisesToActualOnChange(row)
                    ? row.actual_minor_units
                    : row.plan_minor_units,
            ),
        ]),
    );
}

/**
 * Always null for an irregular row (ADR-0066).
 *
 * 'offer': the button "plan = actual" (expense with a field, readable amount
 * below the actual amount, which the field accepts); 'raised': the marker
 * (field equals the actual amount, which exceeds the saved plan).
 */
export function matchActualState(
    row: MonthPlanItem,
    fieldAmount: number | null,
    hasAmountField: boolean,
): MatchActualState {
    if (
        row.type !== 'expense' ||
        row.rhythm === 'irregular' ||
        !hasAmountField ||
        fieldAmount === null
    ) {
        return null;
    }

    const actual = row.actual_minor_units;

    if (actual > fieldAmount && actual <= MAX_PLAN_AMOUNT_MINOR_UNITS) {
        return 'offer';
    }

    if (actual === fieldAmount && actual > row.plan_minor_units) {
        return 'raised';
    }

    return null;
}
