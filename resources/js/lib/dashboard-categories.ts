import {
    DEFAULT_OPERATIONS_SECTION,
    EMPTY_OPERATIONS_FILTERS,
    operationsListQuery,
} from './operations-list-query';

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

/**
 * Cel wiersza kategorii: operacje tej kategorii w całym roku budżetu, żeby
 * kwota w tabeli miała widoczne źródło.
 */
export function categoryOperationsQuery(
    category: { name: string },
    categoryType: 'income' | 'expense',
    year: number,
): Record<string, string | number | undefined> {
    return operationsListQuery(
        {
            ...EMPTY_OPERATIONS_FILTERS,
            category_name: category.name,
            category_type: categoryType,
            date_from: `${year}-01-01`,
            date_to: `${year}-12-31`,
        },
        DEFAULT_OPERATIONS_SECTION,
    );
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
