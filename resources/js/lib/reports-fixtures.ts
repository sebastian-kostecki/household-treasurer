import type {
    PeriodReportCategory,
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
];
