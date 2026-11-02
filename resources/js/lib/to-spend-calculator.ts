export type ToSpendItem = {
    type: 'income' | 'expense';
    kind: 'bill' | 'running' | null;
    plan: number;
    actual: number;
    hasPositiveAmount: boolean;
};

/**
 * Copy of App\Support\ToSpendCalculator::itemMinorUnits: the unsigned amount
 * one item contributes. Change both together.
 */
export function toSpendItemMinorUnits(item: ToSpendItem): number {
    if (item.type === 'income' || item.kind === 'bill') {
        return item.hasPositiveAmount ? item.actual : item.plan;
    }

    return Math.max(item.plan, item.actual);
}

/**
 * Copy of App\Support\ToSpendCalculator::toSpendMinorUnits, used for the live
 * preview while a month plan proposal is edited. Change both together.
 */
export function toSpendMinorUnits(
    items: ToSpendItem[],
    setAsideMinorUnits: number,
): number {
    let total = 0;

    for (const item of items) {
        const amount = toSpendItemMinorUnits(item);

        total += item.type === 'income' ? amount : -amount;
    }

    return total - setAsideMinorUnits;
}
