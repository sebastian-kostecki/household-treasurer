import {
    isMonthPaceAhead,
    monthPacePercent,
    monthPacePoolSums,
} from './month-pace-calculator';

import type { MonthRhythm } from '../types/month-plan';

export type MonthPacePreviewRow = {
    type: 'income' | 'expense';
    rhythm: MonthRhythm | null;
    actual_minor_units: number;
};

/**
 * Pace shown while a month plan is edited: the plan of each row comes from the
 * amount typed in its field, the actual amount from the server row.
 */
export function monthPacePreview<Row extends MonthPacePreviewRow>(
    rows: Row[],
    planOf: (row: Row) => number,
    elapsedDay: number,
    daysInMonth: number,
): { percent: number | null; isAhead: boolean } {
    const sums = monthPacePoolSums(
        rows.map((row) => ({
            type: row.type,
            rhythm: row.rhythm,
            plan: planOf(row),
            actual: row.actual_minor_units,
        })),
    );

    return {
        percent: monthPacePercent(sums.planMinorUnits, sums.actualMinorUnits),
        isAhead: isMonthPaceAhead(
            sums.planMinorUnits,
            sums.actualMinorUnits,
            elapsedDay,
            daysInMonth,
        ),
    };
}
