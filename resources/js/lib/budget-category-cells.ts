export type BudgetCategoryType = 'income' | 'expense';

/**
 * Archived rows return '' so the cell inherits `text-muted-foreground` from
 * the row and never shows a status colour.
 */
export function executionClass(
    type: BudgetCategoryType,
    isArchived: boolean,
): string {
    if (isArchived) {
        return '';
    }

    return type === 'income' ? 'text-success' : '';
}

export function remainingClass(
    type: BudgetCategoryType,
    isOverLimit: boolean,
    isArchived: boolean,
): string {
    if (isArchived) {
        return '';
    }

    return type === 'expense' && isOverLimit
        ? 'text-destructive'
        : 'text-muted-foreground';
}

export function totalsRemainingClass(
    type: BudgetCategoryType,
    remainingMinorUnits: number,
): string {
    return type === 'expense' && remainingMinorUnits < 0
        ? 'text-destructive'
        : 'text-muted-foreground';
}
