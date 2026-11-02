export type ToSpendItem = {
    type: 'income' | 'expense';
    kind: 'bill' | 'running' | null;
    plan: number;
    actual: number;
    hasPositiveAmount: boolean;
};

/**
 * Copy of App\Support\ToSpendCalculator::toSpendMinorUnits, used for the live
 * preview while a month plan proposal is edited. Change both together.
 */
export function toSpendMinorUnits(items: ToSpendItem[]): number {
    let total = 0;

    for (const item of items) {
        if (item.type === 'income') {
            total += item.hasPositiveAmount ? item.actual : item.plan;

            continue;
        }

        if (item.kind === 'bill') {
            total -= item.hasPositiveAmount ? item.actual : item.plan;

            continue;
        }

        total -= Math.max(item.plan, item.actual);
    }

    return total;
}
