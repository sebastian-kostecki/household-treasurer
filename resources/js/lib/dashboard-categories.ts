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
};

export type DashboardTypeTotals = {
    initial_plan_minor_units: number;
    current_plan_minor_units: number;
    execution_minor_units: number;
    remaining_minor_units: number;
    percent_complete: number | null;
};

export type CategoryOrderMode = 'budget' | 'risk';

export const VISIBLE_CATEGORY_LIMIT = 8;

export function showsYearElapsedMark(
    categoryType: 'income' | 'expense',
    category: DashboardCategory,
): boolean {
    if (categoryType === 'income') {
        return true;
    }

    return category.is_paced === true;
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
