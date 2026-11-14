import type {
    PeriodReportCategory,
    PeriodReportComparison,
    PeriodReportData,
    ReportPeriod,
    ReportsPageProps,
    ReportWindow,
    ReportTrendCategory,
    ReportTrendData,
    ReportTrendEntry,
    ReportTrendMonth,
    ReportYearsCategory,
    ReportYearsCells,
    ReportYearsData,
    ReportYearsEntry,
    ReportYearsFastest,
    ReportYearsMode,
    ReportYearsSection,
} from '@/types/reports';

/** Scenarios of the Reports kitchen sink (`/dev/ui/reports`). */
export type ReportScenario = {
    key: string;
    caption: string;
    /** Rendered in a phone-wide frame. */
    phone?: boolean;
    props: ReportsPageProps;
};

function category(
    id: number,
    name: string,
    icon: string,
    color: string,
    amount: number,
    share: number | null,
    isArchived = false,
): PeriodReportCategory {
    return {
        id,
        name,
        icon,
        color,
        amount_minor_units: amount,
        share_tenths_percent: share,
        is_archived: isArchived,
        has_operations: true,
        previous_minor_units: null,
        difference_minor_units: null,
    };
}

const food = category(1, 'Żywność', 'shopping-cart', 'green', 100000, 667);
const media = category(2, 'Media', 'zap', 'blue', 30000, 200);
const furniture = category(3, 'Meble', 'sofa', 'orange', 50000, 333);
const electronics = category(4, 'Elektronika', 'tv', 'purple', -20000, -133);
const cinema = category(5, 'Kino', 'film', 'pink', 20000, 133);
const oldSubscription = category(
    6,
    'Stara prenumerata',
    'newspaper',
    'gray',
    12000,
    80,
    true,
);
const salary = category(7, 'Wynagrodzenie', 'briefcase', 'green', 700000, null);
const bonus = category(8, 'Premia', 'gift', 'teal', 80000, null);

export const yearReport: PeriodReportData = {
    income_minor_units: 780000,
    expense_minor_units: 150000,
    result_minor_units: 630000,
    has_operations: true,
    comparison: null,
    expense: [
        { kind: 'category', ...food },
        {
            kind: 'group',
            id: 10,
            name: 'Dom',
            amount_minor_units: 60000,
            share_tenths_percent: 400,
            has_operations: true,
            previous_minor_units: null,
            difference_minor_units: null,
            categories: [media, furniture, electronics],
        },
        { kind: 'category', ...cinema },
    ],
    income: [
        { kind: 'category', ...salary },
        { kind: 'category', ...bonus },
    ],
};

const archivedInGroupReport: PeriodReportData = {
    income_minor_units: 700000,
    expense_minor_units: 162000,
    result_minor_units: 538000,
    has_operations: true,
    comparison: null,
    expense: [
        {
            kind: 'group',
            id: 11,
            name: 'Abonamenty',
            amount_minor_units: 62000,
            share_tenths_percent: 383,
            has_operations: true,
            previous_minor_units: null,
            difference_minor_units: null,
            categories: [
                category(9, 'Streaming', 'tv', 'red', 50000, 309),
                oldSubscription,
            ],
        },
        { kind: 'category', ...food },
    ],
    income: [{ kind: 'category', ...salary }],
};

const refundReport: PeriodReportData = {
    income_minor_units: 0,
    expense_minor_units: 150000,
    result_minor_units: -150000,
    has_operations: true,
    comparison: null,
    expense: yearReport.expense,
    income: [],
};

const nonPositiveExpenseReport: PeriodReportData = {
    income_minor_units: 500000,
    expense_minor_units: -20000,
    result_minor_units: 520000,
    has_operations: true,
    comparison: null,
    expense: [
        {
            kind: 'category',
            ...category(4, 'Elektronika', 'tv', 'purple', -20000, null),
        },
    ],
    income: [{ kind: 'category', ...salary, amount_minor_units: 500000 }],
};

const emptyReport: PeriodReportData = {
    income_minor_units: 0,
    expense_minor_units: 0,
    result_minor_units: 0,
    has_operations: false,
    comparison: null,
    expense: [],
    income: [],
};

/** A category with the previous-year amount; no current operations when `hasOperations` is false. */
function compared(
    base: PeriodReportCategory,
    previous: number | null,
    hasOperations = true,
): PeriodReportCategory {
    const current = hasOperations ? base.amount_minor_units : 0;

    return {
        ...base,
        amount_minor_units: current,
        share_tenths_percent: hasOperations ? base.share_tenths_percent : null,
        has_operations: hasOperations,
        previous_minor_units: previous,
        difference_minor_units: current - (previous ?? 0),
    };
}

function comparison(
    values: Pick<
        PeriodReportComparison,
        | 'from'
        | 'through'
        | 'is_partial'
        | 'income_minor_units'
        | 'expense_minor_units'
        | 'result_minor_units'
    >,
    current: { income: number; expense: number },
): PeriodReportComparison {
    return {
        year: 2025,
        ...values,
        income_difference_minor_units:
            current.income - values.income_minor_units,
        expense_difference_minor_units:
            current.expense - values.expense_minor_units,
        result_difference_minor_units:
            current.income - current.expense - values.result_minor_units,
    };
}

export const comparedYearReport: PeriodReportData = {
    income_minor_units: 780000,
    expense_minor_units: 160000,
    result_minor_units: 620000,
    has_operations: true,
    comparison: comparison(
        {
            from: '2025-01-01',
            through: '2025-11-11',
            is_partial: true,
            income_minor_units: 690000,
            expense_minor_units: 151000,
            result_minor_units: 539000,
        },
        { income: 780000, expense: 160000 },
    ),
    expense: [
        { kind: 'category', ...compared(food, 91000) },
        {
            kind: 'group',
            id: 10,
            name: 'Dom',
            amount_minor_units: 60000,
            share_tenths_percent: 375,
            has_operations: true,
            previous_minor_units: 45000,
            difference_minor_units: 15000,
            categories: [
                compared(media, 35000),
                compared(furniture, null),
                compared(electronics, 10000),
            ],
        },
        { kind: 'category', ...compared(cinema, 15000, false) },
    ],
    income: [
        { kind: 'category', ...compared(salary, 690000) },
        { kind: 'category', ...compared(bonus, null) },
    ],
};

const comparedMonthReport: PeriodReportData = {
    income_minor_units: 700000,
    expense_minor_units: 162000,
    result_minor_units: 538000,
    has_operations: true,
    comparison: comparison(
        {
            from: '2025-03-01',
            through: '2025-03-31',
            is_partial: false,
            income_minor_units: 700000,
            expense_minor_units: 170000,
            result_minor_units: 530000,
        },
        { income: 700000, expense: 162000 },
    ),
    expense: [
        {
            kind: 'group',
            id: 11,
            name: 'Abonamenty',
            amount_minor_units: 62000,
            share_tenths_percent: 383,
            has_operations: true,
            previous_minor_units: 60000,
            difference_minor_units: 2000,
            categories: [
                compared(
                    category(9, 'Streaming', 'tv', 'red', 50000, 309),
                    48000,
                ),
                compared(oldSubscription, 12000),
            ],
        },
        { kind: 'category', ...compared(food, 110000) },
    ],
    income: [{ kind: 'category', ...compared(salary, 700000) }],
};

const comparedOnlyOneSideReport: PeriodReportData = {
    income_minor_units: 700000,
    expense_minor_units: 130000,
    result_minor_units: 570000,
    has_operations: true,
    comparison: comparison(
        {
            from: '2025-01-01',
            through: '2025-12-31',
            is_partial: false,
            income_minor_units: 700000,
            expense_minor_units: 171000,
            result_minor_units: 529000,
        },
        { income: 700000, expense: 130000 },
    ),
    expense: [
        { kind: 'category', ...compared(food, 91000) },
        {
            kind: 'group',
            id: 12,
            name: 'Auto',
            amount_minor_units: 0,
            share_tenths_percent: null,
            has_operations: false,
            previous_minor_units: 80000,
            difference_minor_units: -80000,
            categories: [
                compared(
                    category(10, 'Paliwo', 'fuel', 'orange', 0, null),
                    50000,
                    false,
                ),
                compared(
                    category(11, 'Ubezpieczenie', 'shield', 'blue', 0, null),
                    30000,
                    false,
                ),
            ],
        },
        {
            kind: 'category',
            ...compared(
                category(
                    12,
                    'Subskrypcje AI',
                    'sparkles',
                    'purple',
                    30000,
                    231,
                ),
                null,
            ),
        },
    ],
    income: [{ kind: 'category', ...compared(salary, 700000) }],
};

const comparedEmptyCurrentReport: PeriodReportData = {
    income_minor_units: 0,
    expense_minor_units: 0,
    result_minor_units: 0,
    has_operations: true,
    comparison: comparison(
        {
            from: '2025-01-01',
            through: '2025-01-31',
            is_partial: false,
            income_minor_units: 690000,
            expense_minor_units: 91000,
            result_minor_units: 599000,
        },
        { income: 0, expense: 0 },
    ),
    expense: [{ kind: 'category', ...compared(food, 91000, false) }],
    income: [{ kind: 'category', ...compared(salary, 690000, false) }],
};

type TrendMonthsByNumber = Record<number, number>;

/** 12 cells from a month-number map; a missing month has no operation. */
function trendMonths(byMonth: TrendMonthsByNumber): (number | null)[] {
    return Array.from({ length: 12 }, (_, index) => byMonth[index + 1] ?? null);
}

function sumTrendMonths(rows: (number | null)[][]): (number | null)[] {
    return Array.from({ length: 12 }, (_, index) => {
        const cells = rows
            .map((row) => row[index])
            .filter((cell): cell is number => cell !== null);

        return cells.length === 0
            ? null
            : cells.reduce((sum, cell) => sum + cell, 0);
    });
}

function sumCells(months: (number | null)[]): number {
    return months.reduce<number>((sum, cell) => sum + (cell ?? 0), 0);
}

function trendCategory(
    id: number,
    name: string,
    icon: string,
    color: string,
    byMonth: TrendMonthsByNumber,
    isArchived = false,
): ReportTrendCategory {
    const months = trendMonths(byMonth);

    return {
        id,
        name,
        icon,
        color,
        is_archived: isArchived,
        amount_minor_units: sumCells(months),
        months,
    };
}

function trendGroup(
    id: number,
    name: string,
    categories: ReportTrendCategory[],
): ReportTrendEntry {
    const months = sumTrendMonths(categories.map((entry) => entry.months));

    return {
        kind: 'group',
        id,
        name,
        amount_minor_units: sumCells(months),
        months,
        categories,
    };
}

function trendLoose(category: ReportTrendCategory): ReportTrendEntry {
    return { kind: 'category', ...category };
}

/** The measures follow the rule of `ReportTrend`: a missing side counts as 0 in a month with operations. */
function buildTrend(
    income: ReportTrendEntry[],
    expense: ReportTrendEntry[],
    inProgressMonth: number | null,
): ReportTrendData {
    const incomeMonths = sumTrendMonths(income.map((entry) => entry.months));
    const expenseMonths = sumTrendMonths(expense.map((entry) => entry.months));
    const months: ReportTrendMonth[] = incomeMonths.map((incomeCell, index) => {
        const expenseCell = expenseMonths[index];

        return {
            month: index + 1,
            income_minor_units: incomeCell,
            expense_minor_units: expenseCell,
            result_minor_units:
                incomeCell === null && expenseCell === null
                    ? null
                    : (incomeCell ?? 0) - (expenseCell ?? 0),
        };
    });
    const incomeTotal = sumCells(incomeMonths);
    const expenseTotal = sumCells(expenseMonths);

    return {
        income_minor_units: incomeTotal,
        expense_minor_units: expenseTotal,
        result_minor_units: incomeTotal - expenseTotal,
        has_operations: income.length + expense.length > 0,
        in_progress_month: inProgressMonth,
        months,
        income,
        expense,
    };
}

const trendSalary = trendCategory(7, 'Wynagrodzenie', 'briefcase', 'green', {
    1: 700000,
    2: 700000,
    3: 700000,
    4: 700000,
    5: 700000,
    6: 700000,
    7: 700000,
    8: 700000,
    9: 700000,
    10: 700000,
    11: 700000,
});
const trendBonus = trendCategory(8, 'Premia', 'gift', 'teal', { 3: 80000 });
const trendFood = trendCategory(1, 'Żywność', 'shopping-cart', 'green', {
    1: 98000,
    2: 91050,
    3: 104990,
    4: 99900,
    5: 101250,
    6: 95000,
    7: 110000,
    8: 97500,
    9: 102000,
    10: 99000,
    11: 41234,
});
/** Irregular: bills only in three months, every other month has no operation. */
const trendBills = trendCategory(2, 'Rachunki', 'zap', 'blue', {
    3: 120000,
    7: 118000,
    11: 125000,
});
/** Net zero: a purchase refunded in the same month shows "0", not "—". */
const trendNetZero = trendCategory(3, 'Meble', 'sofa', 'orange', {
    4: 0,
    9: 150000,
});
/** A refund larger than the purchases of the month gives a negative cell. */
const trendRefund = trendCategory(4, 'Elektronika', 'tv', 'purple', {
    2: 90000,
    5: -20000,
});
const trendOldSubscription = trendCategory(
    6,
    'Stara prenumerata',
    'newspaper',
    'gray',
    { 1: 4000, 2: 4000, 3: 4000 },
    true,
);
const trendStreaming = trendCategory(9, 'Streaming', 'tv', 'red', {
    1: 5000,
    2: 5000,
    3: 5000,
    4: 5000,
    5: 5000,
    6: 5000,
    7: 5000,
    8: 5000,
    9: 5000,
    10: 5000,
    11: 5000,
});
/** The January expense exceeds the income (negative result), the other months are positive. */
const trendOverspendJanuary = trendCategory(10, 'Remont', 'hammer', 'orange', {
    1: 900000,
});

export const trendInProgress = buildTrend(
    [trendLoose(trendSalary), trendLoose(trendBonus)],
    [
        trendLoose(trendFood),
        trendGroup(10, 'Dom', [trendBills, trendNetZero, trendRefund]),
        trendGroup(11, 'Abonamenty', [trendStreaming, trendOldSubscription]),
        trendLoose(trendOverspendJanuary),
    ],
    11,
);

const trendClosed = buildTrend(
    [
        trendLoose(
            trendCategory(7, 'Wynagrodzenie', 'briefcase', 'green', {
                1: 700000,
                2: 700000,
                3: 700000,
                4: 700000,
                5: 700000,
                6: 700000,
                7: 700000,
                8: 700000,
                9: 700000,
                10: 700000,
                11: 700000,
                12: 700000,
            }),
        ),
    ],
    [
        trendLoose(
            trendCategory(1, 'Żywność', 'shopping-cart', 'green', {
                1: 98000,
                2: 91050,
                3: 104990,
                4: 99900,
                5: 101250,
                6: 95000,
                7: 110000,
                8: 97500,
                9: 102000,
                10: 99000,
                11: 94500,
                12: 140250,
            }),
        ),
    ],
    null,
);

const trendIrregularAndZero = buildTrend(
    [trendLoose(trendSalary)],
    [trendLoose(trendBills), trendLoose(trendNetZero), trendLoose(trendRefund)],
    11,
);

const trendArchivedInGroup = buildTrend(
    [trendLoose(trendSalary)],
    [trendGroup(11, 'Abonamenty', [trendStreaming, trendOldSubscription])],
    null,
);

const trendNegativeResult = buildTrend(
    [trendLoose(trendSalary)],
    [trendLoose(trendOverspendJanuary), trendLoose(trendFood)],
    11,
);

const windowFull: ReportWindow = {
    from: '2025-11-01',
    through: '2026-10-31',
    covered_from: '2025-11-01',
    covered_through: '2026-10-31',
    covered_month_count: 12,
    missing_from: null,
    missing_through: null,
};

const windowPartial: ReportWindow = {
    ...windowFull,
    covered_from: '2026-01-01',
    covered_month_count: 10,
    missing_from: '2025-11-01',
    missing_through: '2025-12-31',
};

const windowPartialEnd: ReportWindow = {
    ...windowFull,
    covered_through: '2025-12-31',
    covered_month_count: 2,
    missing_from: '2026-01-01',
    missing_through: '2026-10-31',
};

const windowNoCoverage: ReportWindow = {
    ...windowFull,
    covered_from: null,
    covered_through: null,
    covered_month_count: 0,
    missing_from: '2025-11-01',
    missing_through: '2026-10-31',
};

function lastTwelvePeriod(window: ReportWindow): ReportPeriod {
    return {
        kind: 'last-12',
        month: null,
        default_month: 11,
        is_in_progress: false,
        window,
    };
}

const trendEmpty = buildTrend([], [], 11);

const trendPeriod: ReportPeriod = {
    kind: 'trend',
    month: null,
    default_month: 11,
    is_in_progress: true,
    window: null,
};

const trendClosedPeriod: ReportPeriod = {
    ...trendPeriod,
    is_in_progress: false,
    window: null,
};

/**
 * "Lata" fixtures (ADR-0097). Each row is a series of whole zloty for 2020 to
 * 2028 (the "Do D" amounts); the builder derives the "Pełne lata" amounts, the
 * change, the highest years and the statistics the way the server does, so
 * every scenario carries a complete `years` prop.
 */
type YearsSpec = {
    id: number;
    name: string;
    icon: string;
    color: string;
    archived?: boolean;
    /** 2020 to 2028; null is a year without a Budget Line. */
    zloty: (number | null)[];
};

type YearsGroupSpec = { id: number; name: string; categories: YearsSpec[] };

type YearsRowSpec = YearsSpec | YearsGroupSpec;

type YearsRows = { expense: YearsRowSpec[]; income: YearsRowSpec[] };

const YEARS_FIRST = 2020;

/** A completed year runs longer than the same day of it; the current year is the same. */
const YEARS_FULL_FACTOR = 1.12;

const yearsCzynsz: YearsSpec = {
    id: 101,
    name: 'Czynsz',
    icon: 'house',
    color: 'blue',
    zloty: [7800, 8400, 8700, 9300, 9600, 9900, 9900, 10200, 10500],
};
const yearsInternet: YearsSpec = {
    id: 102,
    name: 'Internet',
    icon: 'zap',
    color: 'teal',
    zloty: [1320, 1320, 1200, 1100, 1100, 1080, 1100, 1100, 1150],
};
const yearsMeble: YearsSpec = {
    id: 103,
    name: 'Meble',
    icon: 'sofa',
    color: 'orange',
    zloty: [0, null, 1800, 2600, 3300, 4200, 5040, 5200, 5600],
};
const yearsGroupHome: YearsGroupSpec = {
    id: 10,
    name: 'Dom',
    categories: [yearsCzynsz, yearsInternet, yearsMeble],
};
const yearsFood: YearsSpec = {
    id: 104,
    name: 'Żywność',
    icon: 'shopping-cart',
    color: 'green',
    zloty: [9800, 10400, 11800, 13200, 14400, 15100, 16200, 16900, 17600],
};
const yearsHolidays: YearsSpec = {
    id: 105,
    name: 'Wakacje',
    icon: 'plane',
    color: 'purple',
    zloty: [2200, 2800, 3500, 4900, 6400, 7400, 8600, 9000, 9800],
};
const yearsCinema: YearsSpec = {
    id: 106,
    name: 'Kino',
    icon: 'film',
    color: 'pink',
    archived: true,
    zloty: [1500, 1400, 1300, 900, 700, null, null, null, null],
};
const yearsTransport: YearsSpec = {
    id: 107,
    name: 'Transport',
    icon: 'car',
    color: 'red',
    zloty: [13360, 15200, 17800, 20400, 22300, 25000, 27400, 28000, 29000],
};
const yearsSalary: YearsSpec = {
    id: 201,
    name: 'Wynagrodzenie',
    icon: 'briefcase',
    color: 'green',
    zloty: [64000, 66000, 70000, 74000, 79000, 84000, 88000, 91000, 94000],
};
const yearsBonus: YearsSpec = {
    id: 202,
    name: 'Premia',
    icon: 'gift',
    color: 'teal',
    zloty: [5900, 6100, 0, 7200, 8000, 8400, 9000, 9400, 9800],
};

const yearsRows: YearsRows = {
    expense: [
        yearsGroupHome,
        yearsFood,
        yearsHolidays,
        yearsCinema,
        yearsTransport,
    ],
    income: [yearsSalary, yearsBonus],
};

/** (numerator / denominator) in tenths of a percent, half away from zero, on integers. */
function tenthsOfRatio(numerator: number, denominator: number): number {
    const scaled = numerator * 1000;

    return (
        Math.sign(scaled) *
        Math.floor((2 * Math.abs(scaled) + denominator) / (2 * denominator))
    );
}

/** Annual growth in tenths of a percent between two amounts `years` apart. */
function growthTenths(start: number, end: number, years: number): number {
    return Math.round((Math.pow(end / start, 1 / years) - 1) * 1000);
}

function yearsCells(
    cells: (number | null)[],
    counted: number,
): ReportYearsCells {
    const countedCells = cells.slice(0, counted);
    const finalIndex = counted - 1;
    const final = countedCells[finalIndex] ?? null;
    const firstIndex = countedCells.findIndex(
        (cell) => cell !== null && cell > 0,
    );
    const first = firstIndex < 0 ? null : countedCells[firstIndex];
    const hasChange =
        final !== null &&
        first !== null &&
        first > 0 &&
        firstIndex !== finalIndex;
    const maximum = Math.max(0, ...countedCells.map((cell) => cell ?? 0));

    return {
        cells,
        change_tenths: hasChange ? tenthsOfRatio(final - first, first) : null,
        change_first_year: hasChange ? YEARS_FIRST + firstIndex : null,
        max_years:
            maximum <= 0
                ? []
                : countedCells.flatMap((cell, index) =>
                      cell === maximum ? [YEARS_FIRST + index] : [],
                  ),
    };
}

function sumYearsCells(
    rows: (number | null)[][],
    width: number,
): (number | null)[] {
    return Array.from({ length: width }, (_, index) =>
        rows.every((row) => row[index] === null)
            ? null
            : rows.reduce((sum, row) => sum + (row[index] ?? 0), 0),
    );
}

type YearsBuild = {
    columns: number[];
    currentYear: number | null;
    /** "Pełne lata": completed years in full, the current year out of the change. */
    full: boolean;
};

function yearsSeries(spec: YearsSpec, build: YearsBuild): (number | null)[] {
    return build.columns.map((year) => {
        const value = spec.zloty[year - YEARS_FIRST] ?? null;

        if (value === null) {
            return null;
        }

        const zloty =
            build.full && year !== build.currentYear
                ? Math.round(value * YEARS_FULL_FACTOR)
                : value;

        return zloty * 100;
    });
}

function yearsSection(
    rows: YearsRowSpec[],
    build: YearsBuild,
    counted: number,
): ReportYearsSection {
    const width = build.columns.length;

    const toCategory = (spec: YearsSpec): ReportYearsCategory => ({
        ...yearsCells(yearsSeries(spec, build), counted),
        kind: 'category',
        id: spec.id,
        name: spec.name,
        icon: spec.icon,
        color: spec.color,
        is_archived: spec.archived ?? false,
    });

    const entries: ReportYearsEntry[] = rows.map((row) => {
        if (!('categories' in row)) {
            return toCategory(row);
        }

        const categories = row.categories.map(toCategory);

        return {
            ...yearsCells(
                sumYearsCells(
                    categories.map((category) => category.cells),
                    width,
                ),
                counted,
            ),
            kind: 'group' as const,
            id: row.id,
            name: row.name,
            categories,
        };
    });

    const categories = entries.flatMap((entry) =>
        entry.kind === 'group' ? entry.categories : [entry],
    );
    const totalCells = sumYearsCells(
        categories.map((category) => category.cells),
        width,
    ).map((cell) => cell ?? 0);
    const startIndex = totalCells
        .slice(0, counted)
        .findIndex((cell) => cell > 0);
    const endTotal = counted > 0 ? totalCells[counted - 1] : 0;
    const average =
        startIndex >= 0 && startIndex < counted - 1
            ? growthTenths(
                  totalCells[startIndex],
                  endTotal,
                  counted - 1 - startIndex,
              )
            : null;

    let fastest: ReportYearsFastest | null = null;

    for (const category of categories) {
        const positive = category.cells
            .slice(0, counted)
            .filter((cell) => cell !== null && cell > 0);
        const end = category.cells[counted - 1] ?? null;
        const startAt = category.cells
            .slice(0, counted)
            .findIndex((cell) => cell !== null && cell > 0);

        if (
            positive.length < 4 ||
            end === null ||
            end <= 0 ||
            startAt >= counted - 1
        ) {
            continue;
        }

        const growth = growthTenths(
            category.cells[startAt] as number,
            end,
            counted - 1 - startAt,
        );

        if (fastest === null || growth > fastest.growth_tenths) {
            fastest = {
                id: category.id,
                name: category.name,
                icon: category.icon,
                color: category.color,
                growth_tenths: growth,
                first_year: YEARS_FIRST + startAt,
            };
        }
    }

    return {
        entries,
        total: yearsCells(totalCells, counted),
        first_year: startIndex < 0 ? null : build.columns[startIndex],
        average_yearly_growth_tenths: average,
        fastest_growing: fastest,
    };
}

function yearsMode(rows: YearsRows, build: YearsBuild): ReportYearsMode {
    const counted =
        build.full && build.currentYear !== null
            ? build.columns.indexOf(build.currentYear)
            : build.columns.length;

    return {
        final_year: counted > 0 ? build.columns[counted - 1] : null,
        expense: yearsSection(rows.expense, build, counted),
        income: yearsSection(rows.income, build, counted),
    };
}

function buildYears(options: {
    firstYear?: number;
    lastYear: number;
    currentYear: number | null;
    cut: { month: number; day: number } | null;
    rows?: YearsRows;
}): ReportYearsData {
    const first = options.firstYear ?? YEARS_FIRST;
    const columns = Array.from(
        { length: options.lastYear - first + 1 },
        (_, index) => first + index,
    );
    const rows = options.rows ?? yearsRows;

    return {
        columns,
        current_year: options.currentYear,
        cut: options.cut,
        same_day:
            options.currentYear === null
                ? null
                : yearsMode(rows, {
                      columns,
                      currentYear: options.currentYear,
                      full: false,
                  }),
        full: yearsMode(rows, {
            columns,
            currentYear: options.currentYear,
            full: true,
        }),
    };
}

const yearsPeriod: ReportPeriod = {
    kind: 'years',
    month: null,
    default_month: 11,
    is_in_progress: false,
    window: null,
};

const yearsNovemberCut = { month: 11, day: 14 };

const yearsSameDay = buildYears({
    lastYear: 2026,
    currentYear: 2026,
    cut: yearsNovemberCut,
});

const yearsNoCurrentYear = buildYears({
    lastYear: 2026,
    currentYear: null,
    cut: null,
});

const yearsOnlyCurrent = buildYears({
    firstYear: 2026,
    lastYear: 2026,
    currentYear: 2026,
    cut: yearsNovemberCut,
});

const yearsLeap = buildYears({
    firstYear: 2024,
    lastYear: 2028,
    currentYear: 2028,
    cut: { month: 2, day: 29 },
});

const yearsNoCoverage = buildYears({
    firstYear: 2027,
    lastYear: 2026,
    currentYear: null,
    cut: null,
});

const yearsNoOperations = buildYears({
    lastYear: 2026,
    currentYear: 2026,
    cut: yearsNovemberCut,
    rows: { expense: [], income: [] },
});

export const reportScenarios: ReportScenario[] = [
    {
        key: 'year-in-progress',
        caption:
            'Rok w toku: grupa rozwinięta, kategorie bez grupy; nazwa kategorii to link do operacji roku',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'year',
                month: null,
                default_month: 11,
                is_in_progress: true,
                window: null,
            },
            report: yearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'month-closed',
        caption:
            'Miesiąc zamknięty: nazwa kategorii to link do operacji miesiąca',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'month',
                month: 3,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: archivedInGroupReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'archived-in-group',
        caption: 'Grupa z zarchiwizowaną kategorią (wyszarzona, z plakietką)',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'year',
                month: null,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: archivedInGroupReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'refund-negative-share',
        caption: 'Zwrot netto: ujemna kwota i ujemny udział, ujemny wynik',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'month',
                month: 5,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: refundReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'non-positive-expenses',
        caption: 'Wydatki okresu ≤ 0: udziały jako „—”, bez „100,0%”',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'month',
                month: 6,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: nonPositiveExpenseReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'no-operations',
        caption: 'Okres bez operacji',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'month',
                month: 1,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: emptyReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'draft',
        caption: 'Budżet w przygotowaniu',
        props: {
            budgetYear: 2027,
            budgetPhase: 'draft',
            period: {
                kind: 'year',
                month: null,
                default_month: 1,
                is_in_progress: false,
                window: null,
            },
            report: null,
            trend: null,
            years: null,
        },
    },
    {
        key: 'no-budget',
        caption: 'Brak budżetu',
        props: {
            budgetYear: null,
            budgetPhase: null,
            period: {
                kind: 'year',
                month: null,
                default_month: 1,
                is_in_progress: false,
                window: null,
            },
            report: null,
            trend: null,
            years: null,
        },
    },
    {
        key: 'phone',
        caption:
            'Telefon (ramka 22rem): bez poziomego przewijania, pełna nazwa, Udział pod kwotą, Badge pod nazwą',
        phone: true,
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'year',
                month: null,
                default_month: 11,
                is_in_progress: true,
                window: null,
            },
            report: archivedInGroupReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'compared-year-in-progress',
        caption:
            'Rok w toku z porównaniem: notka o zakresie, kategoria tylko rok wcześniej („—” w bieżącej), tylko w bieżącym („—” w roku wcześniej)',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'year',
                month: null,
                default_month: 11,
                is_in_progress: true,
                window: null,
            },
            report: comparedYearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'compared-month-closed',
        caption:
            'Miesiąc zamknięty z porównaniem, zarchiwizowana kategoria wyciszona razem z różnicą',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'month',
                month: 3,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: comparedMonthReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'compared-one-side-only',
        caption:
            'Grupa z samymi kategoriami z roku wcześniej oraz kategoria tylko w bieżącym okresie',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'year',
                month: null,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: comparedOnlyOneSideReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'compared-empty-current',
        caption:
            'Bieżący okres bez operacji przy operacjach rok wcześniej: tabele z „—” w kolumnie bieżącej',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'month',
                month: 1,
                default_month: 11,
                is_in_progress: false,
                window: null,
            },
            report: comparedEmptyCurrentReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'compared-phone',
        caption:
            'Telefon (ramka 22rem) z porównaniem: pełna nazwa, druga linia (rok wcześniej i różnica), trzecia linia (Udział)',
        phone: true,
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: {
                kind: 'year',
                month: null,
                default_month: 11,
                is_in_progress: true,
                window: null,
            },
            report: comparedYearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'trend-in-progress',
        caption:
            'Trend roku w toku: miesiąc „w toku”, miesiące przyszłe z „—”, grupy zwinięte, kolor tylko w wierszu Wynik',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendPeriod,
            report: null,
            trend: trendInProgress,
            years: null,
        },
    },
    {
        key: 'trend-closed',
        caption:
            'Trend roku zakończonego: wszystkie miesiące z kwotami; nazwa kategorii to link do operacji roku',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendClosedPeriod,
            report: null,
            trend: trendClosed,
            years: null,
        },
    },
    {
        key: 'trend-irregular-and-zero',
        caption:
            'Kategoria nieregularna (rachunki w 3 miesiącach, reszta „—”), kategoria z netto 0 („0”), zwrot dający ujemną komórkę',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendPeriod,
            report: null,
            trend: trendIrregularAndZero,
            years: null,
        },
    },
    {
        key: 'trend-negative-result',
        caption: 'Miesiąc z ujemnym wynikiem (styczeń): wynik w kolorze błędu',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendPeriod,
            report: null,
            trend: trendNegativeResult,
            years: null,
        },
    },
    {
        key: 'trend-archived-in-group',
        caption:
            'Grupa z zarchiwizowaną kategorią: wyciszony wiersz z plakietką, bez koloru',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendClosedPeriod,
            report: null,
            trend: trendArchivedInGroup,
            years: null,
        },
    },
    {
        key: 'trend-no-operations',
        caption: 'Rok bez operacji',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendPeriod,
            report: null,
            trend: trendEmpty,
            years: null,
        },
    },
    {
        key: 'trend-phone',
        caption:
            'Telefon (ramka 22rem): tabele przewijają się w poziomie, nazwa stoi w miejscu',
        phone: true,
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: trendPeriod,
            report: null,
            trend: trendInProgress,
            years: null,
        },
    },
    {
        key: 'last-12-full',
        caption:
            '12 miesięcy w pełni pokryte, bez paska miesięcy i bez porównania; kwoty to suma miesięcy z obu Budżetów, policzona na serwerze, nazwa kategorii to link do operacji całego okna',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowFull),
            report: yearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'last-12-partial',
        caption:
            'Pokryte 10 z 12 miesięcy (brak na początku okna): notka nad panelem miar',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowPartial),
            report: yearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'last-12-partial-end',
        caption:
            'Pokryte 2 miesiące na początku okna, brak na końcu: notka nazywa brakujący koniec',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowPartialEnd),
            report: archivedInGroupReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'last-12-no-coverage',
        caption:
            'Żaden miesiąc okna nie ma budżetu w realizacji: stan pusty zamiast panelu i tabel',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowNoCoverage),
            report: emptyReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'last-12-no-operations',
        caption: 'Okno w pełni pokryte, ale bez operacji',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowFull),
            report: emptyReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'last-12-phone',
        caption:
            'Telefon (ramka 22rem): pięć segmentów przełącznika mieści się lub przewija się w swoim pasku, notka pokrycia',
        phone: true,
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowPartial),
            report: yearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'draft-last-12',
        caption:
            'Szkic 2027 przy oknie z poprzedniego roku w realizacji: „12 miesięcy” pokazuje raport okna, a z przełącznika wraca się do kartki „Budżet w przygotowaniu”',
        props: {
            budgetYear: 2027,
            budgetPhase: 'draft',
            period: lastTwelvePeriod(windowPartial),
            report: yearReport,
            trend: null,
            years: null,
        },
    },
    {
        key: 'years-same-day',
        caption:
            'Lata 2020–2026 „Do 14 listopada” (domyślny): kafelki, zakładki, grupa Dom z sumami, Meble „od 2022”, Kino zarchiwizowana, sparkline z rokiem bieżącym w foreground; „Pełne lata” i Przychody ogląda się kliknięciem (stan lokalny)',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsSameDay,
        },
    },
    {
        key: 'years-no-current-year',
        caption:
            'Styczeń 2027 ze szkicem: brak roku bieżącego, brak przełącznika zakresu, wszystkie lata w całości',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsNoCurrentYear,
        },
    },
    {
        key: 'years-only-current',
        caption: 'Jeden rok w realizacji: zmiana i statystyki „—”',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsOnlyCurrent,
        },
    },
    {
        key: 'years-leap',
        caption: 'Cięcie 29 lutego: przełącznik „Do 29 lutego”',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsLeap,
        },
    },
    {
        key: 'years-no-coverage',
        caption:
            'Brak budżetu w realizacji: stan pusty zamiast kafelków i tabeli',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsNoCoverage,
        },
    },
    {
        key: 'years-no-operations',
        caption:
            'Lata w realizacji bez operacji: kafelki „—” i „Brak pozycji w tym okresie.”',
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsNoOperations,
        },
    },
    {
        key: 'years-draft-selected',
        caption:
            'Wybrany szkic 2027 w sidebarze: widok „Lata” zostaje i pokazuje te same lata',
        props: {
            budgetYear: 2027,
            budgetPhase: 'draft',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsSameDay,
        },
    },
    {
        key: 'years-phone',
        caption:
            'Telefon (ramka 22rem): pięć segmentów w przewijanym pasku, tabela przewija się z przypiętą kolumną Kategoria',
        phone: true,
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: yearsPeriod,
            report: null,
            trend: null,
            years: yearsSameDay,
        },
    },
];
