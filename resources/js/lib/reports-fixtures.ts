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
        },
    },
    {
        key: 'last-12-phone',
        caption:
            'Telefon (ramka 22rem): cztery segmenty przełącznika mieszczą się bez poziomego przewijania, notka pokrycia',
        phone: true,
        props: {
            budgetYear: 2026,
            budgetPhase: 'execution',
            period: lastTwelvePeriod(windowPartial),
            report: yearReport,
            trend: null,
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
        },
    },
];
