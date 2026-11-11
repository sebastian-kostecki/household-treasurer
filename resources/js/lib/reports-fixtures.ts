import type {
    PeriodReportCategory,
    PeriodReportComparison,
    PeriodReportData,
    ReportPeriod,
} from '@/types/reports';

/** Scenarios of the Reports kitchen sink (`/dev/ui/reports`). */
export type ReportScenario = {
    key: string;
    caption: string;
    /** Rendered in a phone-wide frame. */
    phone?: boolean;
    kind: 'report' | 'draft' | 'no-budget';
    period: ReportPeriod;
    report: PeriodReportData | null;
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

const yearReport: PeriodReportData = {
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

const comparedYearReport: PeriodReportData = {
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

export const reportScenarios: ReportScenario[] = [
    {
        key: 'year-in-progress',
        caption: 'Rok w toku: grupa rozwinięta, kategorie bez grupy',
        kind: 'report',
        period: {
            kind: 'year',
            month: null,
            default_month: 11,
            is_in_progress: true,
        },
        report: yearReport,
    },
    {
        key: 'month-closed',
        caption: 'Miesiąc zamknięty',
        kind: 'report',
        period: {
            kind: 'month',
            month: 3,
            default_month: 11,
            is_in_progress: false,
        },
        report: archivedInGroupReport,
    },
    {
        key: 'archived-in-group',
        caption: 'Grupa z zarchiwizowaną kategorią (wyszarzona, z plakietką)',
        kind: 'report',
        period: {
            kind: 'year',
            month: null,
            default_month: 11,
            is_in_progress: false,
        },
        report: archivedInGroupReport,
    },
    {
        key: 'refund-negative-share',
        caption: 'Zwrot netto: ujemna kwota i ujemny udział, ujemny wynik',
        kind: 'report',
        period: {
            kind: 'month',
            month: 5,
            default_month: 11,
            is_in_progress: false,
        },
        report: refundReport,
    },
    {
        key: 'non-positive-expenses',
        caption: 'Wydatki okresu ≤ 0: udziały jako „—”, bez „100,0%”',
        kind: 'report',
        period: {
            kind: 'month',
            month: 6,
            default_month: 11,
            is_in_progress: false,
        },
        report: nonPositiveExpenseReport,
    },
    {
        key: 'no-operations',
        caption: 'Okres bez operacji',
        kind: 'report',
        period: {
            kind: 'month',
            month: 1,
            default_month: 11,
            is_in_progress: false,
        },
        report: emptyReport,
    },
    {
        key: 'draft',
        caption: 'Budżet w przygotowaniu',
        kind: 'draft',
        period: {
            kind: 'year',
            month: null,
            default_month: 1,
            is_in_progress: false,
        },
        report: null,
    },
    {
        key: 'no-budget',
        caption: 'Brak budżetu',
        kind: 'no-budget',
        period: {
            kind: 'year',
            month: null,
            default_month: 1,
            is_in_progress: false,
        },
        report: null,
    },
    {
        key: 'phone',
        caption: 'Telefon (ramka 22rem): bez poziomego przewijania',
        phone: true,
        kind: 'report',
        period: {
            kind: 'year',
            month: null,
            default_month: 11,
            is_in_progress: true,
        },
        report: archivedInGroupReport,
    },
    {
        key: 'compared-year-in-progress',
        caption:
            'Rok w toku z porównaniem: notka o zakresie, kategoria tylko rok wcześniej („—” w bieżącej), tylko w bieżącym („—” w roku wcześniej)',
        kind: 'report',
        period: {
            kind: 'year',
            month: null,
            default_month: 11,
            is_in_progress: true,
        },
        report: comparedYearReport,
    },
    {
        key: 'compared-month-closed',
        caption:
            'Miesiąc zamknięty z porównaniem, zarchiwizowana kategoria wyciszona razem z różnicą',
        kind: 'report',
        period: {
            kind: 'month',
            month: 3,
            default_month: 11,
            is_in_progress: false,
        },
        report: comparedMonthReport,
    },
    {
        key: 'compared-one-side-only',
        caption:
            'Grupa z samymi kategoriami z roku wcześniej oraz kategoria tylko w bieżącym okresie',
        kind: 'report',
        period: {
            kind: 'year',
            month: null,
            default_month: 11,
            is_in_progress: false,
        },
        report: comparedOnlyOneSideReport,
    },
    {
        key: 'compared-empty-current',
        caption:
            'Bieżący okres bez operacji przy operacjach rok wcześniej: tabele z „—” w kolumnie bieżącej',
        kind: 'report',
        period: {
            kind: 'month',
            month: 1,
            default_month: 11,
            is_in_progress: false,
        },
        report: comparedEmptyCurrentReport,
    },
    {
        key: 'compared-phone',
        caption: 'Telefon (ramka 22rem) z porównaniem: druga linia wiersza',
        phone: true,
        kind: 'report',
        period: {
            kind: 'year',
            month: null,
            default_month: 11,
            is_in_progress: true,
        },
        report: comparedYearReport,
    },
];
