import { parseMoneyInputToMinorUnits } from './format';

/** Matches the server limit in ApproveMonthPlanRequest and UpdateMonthPlanRequest. */
export const MAX_PLAN_AMOUNT_MINOR_UNITS = 1_000_000_000_000;

/** Minor units for a plan or set-aside amount, or null when the input is not a valid amount in 0…MAX. */
export function parsePlanAmountInput(input: string): number | null {
    const parsed = parseMoneyInputToMinorUnits(input);

    return parsed !== null &&
        parsed >= 0 &&
        parsed <= MAX_PLAN_AMOUNT_MINOR_UNITS
        ? parsed
        : null;
}
