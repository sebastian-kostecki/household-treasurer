export type CollectedMonthInput = {
    rate: number;
    actual: number;
};

export type CollectedMonth = {
    start: number;
    end: number;
    surplus: number;
};

/**
 * Copy of App\Support\CollectedCalculator::collectedAtEndMinorUnits: the
 * Collected at the end of a month, never below 0. Change both together.
 */
export function collectedAtEndMinorUnits(
    opening: number,
    rate: number,
    actual: number,
): number {
    return Math.max(0, opening + rate - actual);
}

/**
 * Copy of App\Support\CollectedCalculator::surplusMinorUnits: the part of the
 * bill above the opening Collected plus the rate. Change both together.
 */
export function collectedSurplusMinorUnits(
    opening: number,
    rate: number,
    actual: number,
): number {
    return Math.max(0, actual - (opening + rate));
}

/**
 * Copy of App\Support\CollectedCalculator::chain: the months of a period in
 * order, each starting with the Collected the previous one ended with. Change
 * both together.
 */
export function collectedChain(
    opening: number,
    months: CollectedMonthInput[],
): CollectedMonth[] {
    const result: CollectedMonth[] = [];
    let start = opening;

    for (const month of months) {
        const end = collectedAtEndMinorUnits(start, month.rate, month.actual);

        result.push({
            start,
            end,
            surplus: collectedSurplusMinorUnits(
                start,
                month.rate,
                month.actual,
            ),
        });
        start = end;
    }

    return result;
}
