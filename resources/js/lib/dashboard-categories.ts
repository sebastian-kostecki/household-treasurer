export type DashboardCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    initial_plan_minor_units: number;
    current_plan_minor_units: number;
    execution_minor_units: number;
    remaining_minor_units: number;
    percent_complete: number | null;
    is_over_limit: boolean;
    is_archived: boolean;
    is_paced?: boolean;
    is_drip?: boolean;
    month_remaining_minor_units?: number | null;
    month_execution_minor_units?: number;
};

export type DashboardTypeTotals = {
    initial_plan_minor_units: number;
    current_plan_minor_units: number;
    execution_minor_units: number;
    remaining_minor_units: number;
    percent_complete: number | null;
};

export type CategoryOrderMode = 'budget' | 'risk' | 'month';

export const VISIBLE_CATEGORY_LIMIT = 8;

export function defaultCategoryOrderMode(
    showOrderToggle: boolean,
    showMonthRemainingOrder: boolean,
): CategoryOrderMode {
    if (!showOrderToggle) {
        return 'budget';
    }

    return showMonthRemainingOrder ? 'month' : 'risk';
}

export function monthUsagePercent(
    monthExecutionMinorUnits: number,
    monthRemainingMinorUnits: number,
): number | null {
    const denominator = monthExecutionMinorUnits + monthRemainingMinorUnits;

    if (denominator <= 0) {
        return null;
    }

    return Math.round((monthExecutionMinorUnits / denominator) * 100);
}

export function isOverMonthRemaining(
    monthRemainingMinorUnits: number | null | undefined,
): boolean {
    return (
        monthRemainingMinorUnits !== null &&
        monthRemainingMinorUnits !== undefined &&
        monthRemainingMinorUnits < 0
    );
}

export function shouldShowMonthPace(
    category: DashboardCategory,
    monthElapsedPercent: number | null | undefined,
): boolean {
    if (
        category.is_drip !== true ||
        monthElapsedPercent === null ||
        monthElapsedPercent === undefined
    ) {
        return false;
    }

    if (
        category.month_remaining_minor_units === null ||
        category.month_remaining_minor_units === undefined ||
        category.month_remaining_minor_units < 0
    ) {
        return false;
    }

    return (
        monthUsagePercent(
            category.month_execution_minor_units ?? 0,
            category.month_remaining_minor_units,
        ) !== null
    );
}

export function hasMonthRemainingReading(
    category: DashboardCategory,
): boolean {
    return (
        category.is_paced === true &&
        category.month_remaining_minor_units !== undefined &&
        category.month_remaining_minor_units !== null
    );
}

export function sortCategoriesByRisk(
    categories: DashboardCategory[],
): DashboardCategory[] {
    return [...categories].sort((left, right) => {
        if (left.is_over_limit !== right.is_over_limit) {
            return left.is_over_limit ? -1 : 1;
        }

        const leftPercent = left.percent_complete ?? -1;
        const rightPercent = right.percent_complete ?? -1;

        return rightPercent - leftPercent;
    });
}

export function sortCategoriesByMonthRemaining(
    categories: DashboardCategory[],
): DashboardCategory[] {
    return [...categories]
        .map((category, index) => ({ category, index }))
        .sort((left, right) => {
            const leftPaced = hasMonthRemainingReading(left.category);
            const rightPaced = hasMonthRemainingReading(right.category);

            if (!leftPaced && !rightPaced) {
                return left.index - right.index;
            }

            if (!leftPaced) {
                return 1;
            }

            if (!rightPaced) {
                return -1;
            }

            const leftOver = isOverMonthRemaining(
                left.category.month_remaining_minor_units,
            );
            const rightOver = isOverMonthRemaining(
                right.category.month_remaining_minor_units,
            );

            if (leftOver !== rightOver) {
                return leftOver ? -1 : 1;
            }

            const leftPercent =
                monthUsagePercent(
                    left.category.month_execution_minor_units ?? 0,
                    left.category.month_remaining_minor_units ?? 0,
                ) ?? -1;
            const rightPercent =
                monthUsagePercent(
                    right.category.month_execution_minor_units ?? 0,
                    right.category.month_remaining_minor_units ?? 0,
                ) ?? -1;

            if (leftPercent !== rightPercent) {
                return rightPercent - leftPercent;
            }

            return left.index - right.index;
        })
        .map(({ category }) => category);
}

export function planChanged(category: {
    initial_plan_minor_units: number;
    current_plan_minor_units: number;
}): boolean {
    return (
        category.initial_plan_minor_units !== category.current_plan_minor_units
    );
}

export function progressWidth(percentComplete: number | null): string {
    if (percentComplete === null) {
        return '0%';
    }

    return `${Math.min(percentComplete, 100)}%`;
}

export function meterBarClass(
    categoryType: 'income' | 'expense',
    percentComplete: number | null,
    isOverLimit: boolean,
): string {
    if (percentComplete === null) {
        return 'bg-muted';
    }

    if (categoryType === 'income' && percentComplete > 100) {
        return 'bg-success';
    }

    if (categoryType === 'expense' && isOverLimit) {
        return 'bg-destructive';
    }

    return 'bg-primary';
}

export function meterPercentClass(
    categoryType: 'income' | 'expense',
    percentComplete: number | null,
    isOverLimit: boolean,
): string {
    if (percentComplete === null) {
        return 'text-muted-foreground';
    }

    if (categoryType === 'income' && percentComplete > 100) {
        return 'text-success';
    }

    if (categoryType === 'expense' && isOverLimit) {
        return 'text-destructive';
    }

    return '';
}

export function remainingClass(
    categoryType: 'income' | 'expense',
    isOverLimit: boolean,
): string {
    if (categoryType === 'expense' && isOverLimit) {
        return 'text-destructive';
    }

    return '';
}

export function sectionBarClass(
    categoryType: 'income' | 'expense',
    percentComplete: number | null,
): string {
    if (percentComplete === null) {
        return 'bg-muted';
    }

    if (categoryType === 'income' && percentComplete > 100) {
        return 'bg-success';
    }

    if (categoryType === 'expense' && percentComplete > 100) {
        return 'bg-destructive';
    }

    return 'bg-primary';
}

export function sectionPercentClass(
    categoryType: 'income' | 'expense',
    percentComplete: number | null,
): string {
    if (percentComplete === null) {
        return 'text-muted-foreground';
    }

    if (categoryType === 'income' && percentComplete > 100) {
        return 'text-success';
    }

    if (categoryType === 'expense' && percentComplete > 100) {
        return 'text-destructive';
    }

    return '';
}
