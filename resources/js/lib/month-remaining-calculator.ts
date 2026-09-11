export type MonthRemainingCalculatorOptions = {
    isDraft: boolean;
    budgetYear: number;
    today: string;
};

function parseToday(today: string): { year: number; month: number } {
    const [year, month] = today.split('-').map(Number);

    return { year, month };
}

function floorDivide(numerator: number, denominator: number): number {
    if (denominator <= 0) {
        throw new Error('Denominator must be positive.');
    }

    if (numerator >= 0) {
        return Math.floor(numerator / denominator);
    }

    return -Math.floor((-numerator + denominator - 1) / denominator);
}

function monthsLeft(month: number): number {
    return 13 - month;
}

function monthTiming(
    budgetYear: number,
    viewedMonth: number,
    todayYear: number,
    todayMonth: number,
): 'past' | 'current' | 'future' {
    if (budgetYear < todayYear) {
        return 'past';
    }

    if (budgetYear > todayYear) {
        return 'future';
    }

    if (viewedMonth < todayMonth) {
        return 'past';
    }

    if (viewedMonth === todayMonth) {
        return 'current';
    }

    return 'future';
}

function draftMonthRemainingMinorUnits(
    currentPlanMinorUnits: number,
    month: number,
): number {
    const monthlyFloor = floorDivide(currentPlanMinorUnits, 12);

    if (month <= 11) {
        return monthlyFloor;
    }

    return currentPlanMinorUnits - 11 * monthlyFloor;
}

function catchUpMonthRemainingMinorUnits(
    currentPlanMinorUnits: number,
    executionBeforeViewedMonth: number,
    executionInViewedMonth: number,
    viewedMonth: number,
): number {
    const leftover = currentPlanMinorUnits - executionBeforeViewedMonth;
    const allowance = floorDivide(leftover, monthsLeft(viewedMonth));

    return allowance - executionInViewedMonth;
}

function futurePlasterMonthRemainingMinorUnits(
    currentPlanMinorUnits: number,
    executionBeforeCurrentMonth: number,
    executionInCurrentMonth: number,
    budgetYear: number,
    todayYear: number,
    todayMonth: number,
): number {
    if (budgetYear > todayYear) {
        return floorDivide(currentPlanMinorUnits, 12);
    }

    return catchUpMonthRemainingMinorUnits(
        currentPlanMinorUnits,
        executionBeforeCurrentMonth,
        executionInCurrentMonth,
        todayMonth,
    );
}

export function pacedMonthRemainingMinorUnits(
    options: MonthRemainingCalculatorOptions,
    currentPlanMinorUnits: number,
    executionBeforeViewedMonth: number,
    executionInViewedMonth: number,
    viewedMonth: number,
    executionBeforeCurrentMonth: number,
    executionInCurrentMonth: number,
): number {
    const { year: todayYear, month: todayMonth } = parseToday(options.today);

    if (options.isDraft) {
        return draftMonthRemainingMinorUnits(currentPlanMinorUnits, viewedMonth);
    }

    if (
        monthTiming(
            options.budgetYear,
            viewedMonth,
            todayYear,
            todayMonth,
        ) === 'future'
    ) {
        return futurePlasterMonthRemainingMinorUnits(
            currentPlanMinorUnits,
            executionBeforeCurrentMonth,
            executionInCurrentMonth,
            options.budgetYear,
            todayYear,
            todayMonth,
        );
    }

    return catchUpMonthRemainingMinorUnits(
        currentPlanMinorUnits,
        executionBeforeViewedMonth,
        executionInViewedMonth,
        viewedMonth,
    );
}

export function pacedPoolMonthRemainingMinorUnits(
    options: MonthRemainingCalculatorOptions,
    totalCurrentPlanMinorUnits: number,
    totalExecutionBeforeViewedMonth: number,
    totalExecutionInViewedMonth: number,
    viewedMonth: number,
    totalExecutionBeforeCurrentMonth: number,
    totalExecutionInCurrentMonth: number,
    hasPacedCategories: boolean,
): number {
    if (!hasPacedCategories) {
        return 0;
    }

    const { year: todayYear, month: todayMonth } = parseToday(options.today);

    if (options.isDraft) {
        return draftMonthRemainingMinorUnits(
            totalCurrentPlanMinorUnits,
            viewedMonth,
        );
    }

    if (
        monthTiming(
            options.budgetYear,
            viewedMonth,
            todayYear,
            todayMonth,
        ) === 'future'
    ) {
        return futurePlasterMonthRemainingMinorUnits(
            totalCurrentPlanMinorUnits,
            totalExecutionBeforeCurrentMonth,
            totalExecutionInCurrentMonth,
            options.budgetYear,
            todayYear,
            todayMonth,
        );
    }

    return catchUpMonthRemainingMinorUnits(
        totalCurrentPlanMinorUnits,
        totalExecutionBeforeViewedMonth,
        totalExecutionInViewedMonth,
        viewedMonth,
    );
}
