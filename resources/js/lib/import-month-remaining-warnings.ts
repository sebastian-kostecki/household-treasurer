import type { ImportRow, RowSelection } from './import-preview-rows';
import {
    isPoolRhythm,
    poolCategoryMonthRemainingMinorUnits,
    poolMonthRemainingMinorUnits,
} from './month-remaining-calculator';
import type { Rhythm } from '../types/rhythm';

export type MonthRemainingBudgetContext = {
    id: number;
    year: number;
    is_draft: boolean;
    categories: Array<{
        id: number;
        rhythms: Record<number, Rhythm> | null;
        planned_amount_minor_units: number;
    }>;
    execution_by_category_and_month: Record<number, Record<number, number>>;
};

export type MonthRemainingContext = {
    today: string;
    budgets: MonthRemainingBudgetContext[];
};

export type MonthRemainingWarningState = {
    showCategoryWarning: boolean;
    showPoolWarning: boolean;
    periodLabel: string | null;
};

function executionInMonth(
    budget: MonthRemainingBudgetContext,
    pendingImportExecution: Record<number, Record<number, number>>,
    categoryId: number,
    month: number,
): number {
    return (
        (budget.execution_by_category_and_month[categoryId]?.[month] ?? 0) +
        (pendingImportExecution[categoryId]?.[month] ?? 0)
    );
}

function executionBeforeMonth(
    budget: MonthRemainingBudgetContext,
    pendingImportExecution: Record<number, Record<number, number>>,
    categoryId: number,
    month: number,
): number {
    let total = 0;

    for (let viewedMonth = 1; viewedMonth < month; viewedMonth++) {
        total += executionInMonth(
            budget,
            pendingImportExecution,
            categoryId,
            viewedMonth,
        );
    }

    return total;
}

function budgetForYear(
    context: MonthRemainingContext,
    year: number,
): MonthRemainingBudgetContext | undefined {
    return context.budgets.find((budget) => budget.year === year);
}

function poolCategories(
    budget: MonthRemainingBudgetContext,
    month: number,
): MonthRemainingBudgetContext['categories'] {
    return budget.categories.filter((category) =>
        isPoolRhythm(category.rhythms?.[month]),
    );
}

function isPoolExpenseSelection(
    selection: RowSelection,
    year: number,
    month: number,
    expenseCategories: Array<{
        id: number;
        budget_year: number;
        rhythms?: Record<number, Rhythm> | null;
    }>,
): boolean {
    if (!selection.selected || selection.type !== 'expense') {
        return false;
    }

    if (selection.category_id === '') {
        return false;
    }

    const category = expenseCategories.find(
        (candidate) =>
            candidate.id === selection.category_id &&
            candidate.budget_year === year,
    );

    return isPoolRhythm(category?.rhythms?.[month]);
}

export function computeImportMonthRemainingWarnings(
    rows: ImportRow[],
    selections: Record<number, RowSelection>,
    expenseCategories: Array<{
        id: number;
        budget_year: number;
        rhythms?: Record<number, Rhythm> | null;
    }>,
    context: MonthRemainingContext,
    translations: Record<string, string>,
): Record<number, MonthRemainingWarningState> {
    const warnings: Record<number, MonthRemainingWarningState> = {};
    const pendingImportExecution: Record<
        number,
        Record<number, Record<number, number>>
    > = {};

    for (const row of rows) {
        warnings[row.index] = {
            showCategoryWarning: false,
            showPoolWarning: false,
            periodLabel: null,
        };

        if (!row.valid || !row.operation_date) {
            continue;
        }

        const selection = selections[row.index];

        if (selection === undefined) {
            continue;
        }

        const year = Number(row.operation_date.slice(0, 4));
        const month = Number(row.operation_date.slice(5, 7));
        const budget = budgetForYear(context, year);

        if (budget === undefined) {
            continue;
        }

        if (
            !isPoolExpenseSelection(selection, year, month, expenseCategories)
        ) {
            continue;
        }

        const categoryId = selection.category_id as number;
        const category = budget.categories.find(
            (candidate) => candidate.id === categoryId,
        );

        if (
            category === undefined ||
            !isPoolRhythm(category.rhythms?.[month])
        ) {
            continue;
        }

        const importAmount = Math.abs(row.amount_minor_units ?? 0);
        const paced = poolCategories(budget, month);
        const budgetPending = pendingImportExecution[budget.id] ?? {};
        const calculatorOptions = {
            isDraft: budget.is_draft,
            budgetYear: budget.year,
            today: context.today,
        };
        const todayYear = Number(context.today.slice(0, 4));
        const currentMonth =
            todayYear === budget.year ? Number(context.today.slice(5, 7)) : 1;

        const categoryMonthRemaining = poolCategoryMonthRemainingMinorUnits(
            calculatorOptions,
            category.planned_amount_minor_units,
            executionBeforeMonth(budget, budgetPending, categoryId, month),
            executionInMonth(budget, budgetPending, categoryId, month) +
                importAmount,
            month,
            executionBeforeMonth(
                budget,
                budgetPending,
                categoryId,
                currentMonth,
            ),
            executionInMonth(budget, budgetPending, categoryId, currentMonth),
        );

        const poolMonthRemaining = poolMonthRemainingMinorUnits(
            calculatorOptions,
            paced.reduce(
                (sum, poolCategory) =>
                    sum + poolCategory.planned_amount_minor_units,
                0,
            ),
            paced.reduce(
                (sum, poolCategory) =>
                    sum +
                    executionBeforeMonth(
                        budget,
                        budgetPending,
                        poolCategory.id,
                        month,
                    ),
                0,
            ),
            paced.reduce(
                (sum, poolCategory) =>
                    sum +
                    executionInMonth(
                        budget,
                        budgetPending,
                        poolCategory.id,
                        month,
                    ),
                0,
            ) + importAmount,
            month,
            paced.reduce(
                (sum, poolCategory) =>
                    sum +
                    executionBeforeMonth(
                        budget,
                        budgetPending,
                        poolCategory.id,
                        currentMonth,
                    ),
                0,
            ),
            paced.reduce(
                (sum, poolCategory) =>
                    sum +
                    executionInMonth(
                        budget,
                        budgetPending,
                        poolCategory.id,
                        currentMonth,
                    ),
                0,
            ),
            paced.length > 0,
        );

        warnings[row.index] = {
            showCategoryWarning: categoryMonthRemaining < 0,
            showPoolWarning: poolMonthRemaining < 0,
            periodLabel: `${translations[`month_name_${month}`] ?? ''} ${year}`,
        };

        pendingImportExecution[budget.id] ??= {};
        pendingImportExecution[budget.id][categoryId] ??= {};
        pendingImportExecution[budget.id][categoryId][month] =
            (pendingImportExecution[budget.id][categoryId][month] ?? 0) +
            importAmount;
    }

    return warnings;
}
