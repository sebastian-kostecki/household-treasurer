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

export const VISIBLE_CATEGORY_LIMIT = 8;

export const DASHBOARD_SECTION_VISIBLE_CATEGORY_LIMIT = 5;

export function dashboardVisibleCategoryLimit(
    _categoryType: 'income' | 'expense',
): number {
    return DASHBOARD_SECTION_VISIBLE_CATEGORY_LIMIT;
}

export function sliceVisibleCategories(
    categories: DashboardCategory[],
    visibleLimit: number,
    isExpanded: boolean,
): DashboardCategory[] {
    if (isExpanded || categories.length <= visibleLimit) {
        return categories;
    }

    return categories.slice(0, visibleLimit);
}

export function hiddenDashboardCategoryCount(
    categoryCount: number,
    visibleLimit: number,
): number {
    return Math.max(categoryCount - visibleLimit, 0);
}

export function showsYearElapsedMark(
    categoryType: 'income' | 'expense',
    category: DashboardCategory,
): boolean {
    if (categoryType === 'income') {
        return true;
    }

    return category.is_paced === true;
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

    return 'bg-foreground';
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
