import {
    pacedMonthRemainingMinorUnits,
    pacedPoolMonthRemainingMinorUnits,
    type MonthRemainingCalculatorOptions,
} from '@/lib/month-remaining-calculator';

export type HandEntryMonthRemainingPreviewEditedRecord = {
    category_id?: number;
    signed_amount_minor_units: number;
    operation_month?: number;
};

export type HandEntryMonthRemainingPreviewProposal = {
    kind: 'income' | 'expense' | 'refund' | 'own_transfer' | 'capital_movement';
    category_id?: number;
};

export type HandEntryMonthRemainingCategory = {
    id: number;
    is_paced: boolean;
    planned_amount_minor_units: number;
    monthly_execution_minor_units: Record<number, number>;
};

export type HandEntryMonthRemainingBudget = {
    year: number;
    is_draft: boolean;
    categories: HandEntryMonthRemainingCategory[];
};

export type HandEntryMonthRemainingInputs = {
    today: string;
    budgets: HandEntryMonthRemainingBudget[];
};

function findBudget(
    inputs: HandEntryMonthRemainingInputs,
    year: number,
): HandEntryMonthRemainingBudget | undefined {
    return inputs.budgets.find((budget) => budget.year === year);
}

function executionDeltaForCategoryMonth(
    categoryId: number,
    month: number,
    mode: 'create' | 'edit',
    editedRecord: HandEntryMonthRemainingPreviewEditedRecord | null | undefined,
    proposal: HandEntryMonthRemainingPreviewProposal,
    signedAmountMinorUnits: number,
    operationMonth: number,
): number {
    if (proposal.kind !== 'expense') {
        return 0;
    }

    let delta = 0;

    if (
        mode === 'edit' &&
        editedRecord !== null &&
        editedRecord !== undefined &&
        editedRecord.category_id === categoryId &&
        editedRecord.operation_month === month
    ) {
        delta -= editedRecord.signed_amount_minor_units;
    }

    if (proposal.category_id === categoryId && month === operationMonth) {
        delta += signedAmountMinorUnits;
    }

    return delta;
}

function adjustedExecutionInMonth(
    category: HandEntryMonthRemainingCategory,
    month: number,
    mode: 'create' | 'edit',
    editedRecord: HandEntryMonthRemainingPreviewEditedRecord | null | undefined,
    proposal: HandEntryMonthRemainingPreviewProposal,
    signedAmountMinorUnits: number,
    operationMonth: number,
): number {
    const base = category.monthly_execution_minor_units[month] ?? 0;

    return (
        base +
        executionDeltaForCategoryMonth(
            category.id,
            month,
            mode,
            editedRecord,
            proposal,
            signedAmountMinorUnits,
            operationMonth,
        )
    );
}

function executionBeforeMonth(
    categories: HandEntryMonthRemainingCategory[],
    categoryId: number,
    month: number,
    mode: 'create' | 'edit',
    editedRecord: HandEntryMonthRemainingPreviewEditedRecord | null | undefined,
    proposal: HandEntryMonthRemainingPreviewProposal,
    signedAmountMinorUnits: number,
    operationMonth: number,
): number {
    const category = categories.find((entry) => entry.id === categoryId);

    if (category === undefined) {
        return 0;
    }

    let total = 0;

    for (let viewedMonth = 1; viewedMonth < month; viewedMonth++) {
        total += adjustedExecutionInMonth(
            category,
            viewedMonth,
            mode,
            editedRecord,
            proposal,
            signedAmountMinorUnits,
            operationMonth,
        );
    }

    return total;
}

function currentMonthForBudget(
    budgetYear: number,
    today: string,
): number {
    const todayYear = Number(today.slice(0, 4));

    if (todayYear === budgetYear) {
        return Number(today.slice(5, 7));
    }

    return 1;
}

export type HandEntryMonthRemainingPreviewInput = {
    mode: 'create' | 'edit';
    operationDate: string;
    handEntryMonthRemaining: HandEntryMonthRemainingInputs;
    editedRecord?: HandEntryMonthRemainingPreviewEditedRecord | null;
    proposal: HandEntryMonthRemainingPreviewProposal;
    signedAmountMinorUnits: number;
};

export type HandEntryMonthRemainingPreviewResult = {
    showOverMonthCategoryWarning: boolean;
    showOverMonthPoolWarning: boolean;
};

export function previewHandEntryMonthRemainingWarnings(
    input: HandEntryMonthRemainingPreviewInput,
): HandEntryMonthRemainingPreviewResult {
    if (input.proposal.kind !== 'expense') {
        return {
            showOverMonthCategoryWarning: false,
            showOverMonthPoolWarning: false,
        };
    }

    if (
        input.proposal.category_id === undefined ||
        input.operationDate === ''
    ) {
        return {
            showOverMonthCategoryWarning: false,
            showOverMonthPoolWarning: false,
        };
    }

    const operationYear = Number(input.operationDate.slice(0, 4));
    const operationMonth = Number(input.operationDate.slice(5, 7));
    const budget = findBudget(input.handEntryMonthRemaining, operationYear);

    if (budget === undefined) {
        return {
            showOverMonthCategoryWarning: false,
            showOverMonthPoolWarning: false,
        };
    }

    const selectedCategory = budget.categories.find(
        (category) => category.id === input.proposal.category_id,
    );

    if (selectedCategory === undefined || !selectedCategory.is_paced) {
        return {
            showOverMonthCategoryWarning: false,
            showOverMonthPoolWarning: false,
        };
    }

    const calculatorOptions: MonthRemainingCalculatorOptions = {
        isDraft: budget.is_draft,
        budgetYear: budget.year,
        today: input.handEntryMonthRemaining.today,
    };

    const currentMonth = currentMonthForBudget(
        budget.year,
        input.handEntryMonthRemaining.today,
    );

    const pacedCategories = budget.categories.filter(
        (category) => category.is_paced,
    );

    const categoryAfterMonthRemaining = pacedMonthRemainingMinorUnits(
        calculatorOptions,
        selectedCategory.planned_amount_minor_units,
        executionBeforeMonth(
            budget.categories,
            selectedCategory.id,
            operationMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        ),
        adjustedExecutionInMonth(
            selectedCategory,
            operationMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        ),
        operationMonth,
        executionBeforeMonth(
            budget.categories,
            selectedCategory.id,
            currentMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        ),
        adjustedExecutionInMonth(
            selectedCategory,
            currentMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        ),
    );

    let totalCurrentPlan = 0;
    let totalExecutionBeforeViewedMonth = 0;
    let totalExecutionInViewedMonth = 0;
    let totalExecutionBeforeCurrentMonth = 0;
    let totalExecutionInCurrentMonth = 0;

    for (const category of pacedCategories) {
        totalCurrentPlan += category.planned_amount_minor_units;
        totalExecutionBeforeViewedMonth += executionBeforeMonth(
            budget.categories,
            category.id,
            operationMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        );
        totalExecutionInViewedMonth += adjustedExecutionInMonth(
            category,
            operationMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        );
        totalExecutionBeforeCurrentMonth += executionBeforeMonth(
            budget.categories,
            category.id,
            currentMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        );
        totalExecutionInCurrentMonth += adjustedExecutionInMonth(
            category,
            currentMonth,
            input.mode,
            input.editedRecord,
            input.proposal,
            input.signedAmountMinorUnits,
            operationMonth,
        );
    }

    const poolAfterMonthRemaining = pacedPoolMonthRemainingMinorUnits(
        calculatorOptions,
        totalCurrentPlan,
        totalExecutionBeforeViewedMonth,
        totalExecutionInViewedMonth,
        operationMonth,
        totalExecutionBeforeCurrentMonth,
        totalExecutionInCurrentMonth,
        pacedCategories.length > 0,
    );

    return {
        showOverMonthCategoryWarning: categoryAfterMonthRemaining < 0,
        showOverMonthPoolWarning: poolAfterMonthRemaining < 0,
    };
}
