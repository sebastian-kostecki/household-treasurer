import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { CategoryListFilter } from '@/lib/category-list-filter';
import type {
    CategoriesListTab,
    CategoriesPageProps,
    CategoryGroupItem,
    CategoryListItem,
    CategoryMergePreviewData,
} from '@/types/categories';

const categoryAppearanceOptions: CategoriesPageProps['categoryAppearanceOptions'] =
    {
        icons: ['tag', 'wallet', 'percent', 'shopping-cart', 'film', 'plane'],
        colors: [DEFAULT_CATEGORY_COLOR],
    };

export type CategoriesScenario = {
    key: string;
    title: string;
    props: CategoriesPageProps;
    /** The tab the page starts on; defaults to `props.tab`. */
    tab?: CategoriesListTab;
    /** The phrase and Rhythm the page starts with; defaults to no filter. */
    filter?: CategoryListFilter;
    /** The axis of the year strips; defaults to `fixtureYearAxis`. */
    yearAxis?: number[];
};

/** The axis of the year strips in the sink: the Budget years of the fixture user. */
export const fixtureYearAxis = [2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027];

type CategoryFixtureOptions = Partial<
    Pick<
        CategoryListItem,
        | 'description'
        | 'rhythm'
        | 'opening_collected_minor_units'
        | 'irregular_since'
        | 'can_change_type'
        | 'can_delete'
        | 'archived_at'
        | 'group_id'
        | 'sort_order'
    >
>;

export const rhythmChangeMonth: CategoriesPageProps['rhythmChangeMonth'] = {
    year: 2026,
    month: 11,
};

export const shortDescription = 'Wypłata z etatu, wpływa 10. dnia miesiąca';

export const longDescription =
    'Codzienne zakupy spożywcze, chemia domowa i drobiazgi z osiedlowego sklepu, bez restauracji i dostaw jedzenia, które mają własną kategorię, oraz zakupy robione online z dostawą do domu.';

function category(
    id: number,
    name: string,
    type: CategoryListItem['type'],
    icon: string,
    color: string,
    budgetYears: number[],
    options: CategoryFixtureOptions = {},
): CategoryListItem {
    return {
        id,
        name,
        icon,
        color,
        type,
        group_id: options.group_id ?? null,
        sort_order: options.sort_order ?? id,
        description: options.description ?? null,
        rhythm: options.rhythm ?? (type === 'expense' ? 'running' : null),
        opening_collected_minor_units:
            options.opening_collected_minor_units ?? null,
        irregular_since: options.irregular_since ?? null,
        budget_years: budgetYears,
        can_change_type: options.can_change_type ?? true,
        can_delete: options.can_delete ?? true,
        archived_at: options.archived_at ?? null,
    };
}

export const incomeCategories: CategoryListItem[] = [
    category(
        1,
        'Wynagrodzenie',
        'income',
        'wallet',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        {
            description: shortDescription,
            can_change_type: false,
            can_delete: false,
        },
    ),
    category(
        2,
        'Odsetki',
        'income',
        'percent',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
    ),
];

export const expenseCategories: CategoryListItem[] = [
    category(
        11,
        'Żywność',
        'expense',
        'shopping-cart',
        DEFAULT_CATEGORY_COLOR,
        [2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027],
        {
            description: longDescription,
            can_change_type: false,
            can_delete: false,
        },
    ),
    category(
        12,
        'Rozrywka',
        'expense',
        'film',
        DEFAULT_CATEGORY_COLOR,
        [2023, 2024, 2026, 2027],
        { can_delete: false },
    ),
    category(
        13,
        'Rekolekcje',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2027],
        { rhythm: 'even_annual' },
    ),
    category(
        14,
        'Bez pozycji w budżecie',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [],
        { rhythm: 'fixed_charge' },
    ),
    category(
        24,
        'Serwis auta',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        {
            rhythm: 'irregular',
            opening_collected_minor_units: 50000,
            irregular_since: { year: 2026, month: 11 },
        },
    ),
];

export const archivedCategories: CategoryListItem[] = [
    category(
        21,
        'Wakacje',
        'expense',
        'plane',
        DEFAULT_CATEGORY_COLOR,
        [2026],
        {
            archived_at: '2026-09-09',
            description: shortDescription,
            group_id: 32,
        },
    ),
    category(
        22,
        'Stary kredyt, który miał bardzo długą nazwę i nie mieści się w jednym wierszu',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2025, 2026],
        { archived_at: '2026-01-15', can_delete: false },
    ),
    category(
        25,
        'Premia roczna',
        'income',
        'wallet',
        DEFAULT_CATEGORY_COLOR,
        [2024, 2025],
        { archived_at: '2025-12-31' },
    ),
];

/**
 * Kategorie do filtra rytmu: grupa „Dom” (5 kategorii, tylko Meble są roczne),
 * grupa „Codzienne” bez rocznych (znika przy filtrze) i trzy roczne kategorie
 * bez grupy (Zdrowie, Ubrania, OC).
 */
export const rhythmFilterGroups: CategoryGroupItem[] = [
    { id: 41, name: 'Dom', sort_order: 1, archived_count: 0 },
    { id: 42, name: 'Codzienne', sort_order: 6, archived_count: 0 },
];

export const rhythmFilterExpenseCategories: CategoryListItem[] = [
    category(
        51,
        'Czynsz',
        'expense',
        'home',
        DEFAULT_CATEGORY_COLOR,
        [2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027],
        { group_id: 41, sort_order: 2, rhythm: 'fixed_charge' },
    ),
    category(
        52,
        'Prąd',
        'expense',
        'zap',
        DEFAULT_CATEGORY_COLOR,
        [2024, 2025, 2026, 2027],
        { group_id: 41, sort_order: 3, rhythm: 'fixed_charge' },
    ),
    category(
        53,
        'Internet',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2025, 2026, 2027],
        { group_id: 41, sort_order: 4, rhythm: 'fixed_charge' },
    ),
    category(54, 'Gaz', 'expense', 'tag', DEFAULT_CATEGORY_COLOR, [2026], {
        group_id: 41,
        sort_order: 5,
        rhythm: 'running',
    }),
    category(
        55,
        'Meble',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2022, 2023, 2024, 2025, 2026, 2027],
        { group_id: 41, sort_order: 6, rhythm: 'even_annual' },
    ),
    category(
        56,
        'Żywność',
        'expense',
        'shopping-cart',
        DEFAULT_CATEGORY_COLOR,
        [2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027],
        { group_id: 42, sort_order: 7 },
    ),
    category(
        57,
        'Transport',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2023, 2024, 2025, 2026, 2027],
        { group_id: 42, sort_order: 8 },
    ),
    category(
        58,
        'Zdrowie',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2025, 2026, 2027],
        { sort_order: 9, rhythm: 'even_annual' },
    ),
    category(
        59,
        'Ubrania',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2024, 2026, 2027],
        { sort_order: 10, rhythm: 'even_annual' },
    ),
    category(
        60,
        'OC',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2021, 2022, 2023, 2024, 2025, 2026, 2027],
        { sort_order: 11, rhythm: 'even_annual' },
    ),
];

/** Kategorie do szukajki „zwrot”: trafienia w nazwie i w opisie, w każdej zakładce. */
export const searchExpenseCategories: CategoryListItem[] = [
    category(
        61,
        'Żywność',
        'expense',
        'shopping-cart',
        DEFAULT_CATEGORY_COLOR,
        [2025, 2026, 2027],
        { sort_order: 0 },
    ),
    category(
        62,
        'Apteka',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        {
            group_id: 31,
            sort_order: 2,
            description: 'Leki, część kosztów podlega zwrotowi z ubezpieczenia',
        },
    ),
    category(
        63,
        'Badania',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2026],
        { group_id: 31, sort_order: 3 },
    ),
    category(
        64,
        'Zwroty od pracodawcy',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2024, 2025, 2026, 2027],
        { sort_order: 5, rhythm: 'irregular' },
    ),
    category(
        65,
        'Rozrywka',
        'expense',
        'film',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { sort_order: 6 },
    ),
];

export const searchIncomeCategories: CategoryListItem[] = [
    category(
        66,
        'Zwrot podatku',
        'income',
        'percent',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
    ),
    category(
        67,
        'Wynagrodzenie',
        'income',
        'wallet',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
    ),
];

export const searchArchivedCategories: CategoryListItem[] = [
    category(
        68,
        'Zwrot z biura podróży',
        'expense',
        'plane',
        DEFAULT_CATEGORY_COLOR,
        [2025],
        { archived_at: '2025-08-20' },
    ),
    category(
        69,
        'Stary kredyt',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2024, 2025],
        { archived_at: '2026-01-15' },
    ),
];

/**
 * Kategorie z grupami (ADR-0069). Jeden ciąg miejsc na typ: grupa „Zdrowie”
 * (2 kategorie), grupa „Opłaty stałe” (5 kategorii i zarchiwizowane Wakacje),
 * pusta grupa „Podróże” oraz kategorie bez grupy na poziomie typu.
 */
export const incomeGroups: CategoryGroupItem[] = [
    { id: 34, name: 'Pensje', sort_order: 0, archived_count: 0 },
];

export const groupedIncomeCategories: CategoryListItem[] = [
    category(
        1,
        'Wynagrodzenie',
        'income',
        'wallet',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { group_id: 34, sort_order: 1 },
    ),
    category(
        2,
        'Odsetki',
        'income',
        'percent',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { sort_order: 2 },
    ),
];

export const expenseGroups: CategoryGroupItem[] = [
    { id: 31, name: 'Zdrowie', sort_order: 1, archived_count: 0 },
    { id: 32, name: 'Opłaty stałe', sort_order: 4, archived_count: 1 },
    { id: 33, name: 'Podróże', sort_order: 10, archived_count: 0 },
];

export const groupedExpenseCategories: CategoryListItem[] = [
    category(
        11,
        'Żywność',
        'expense',
        'shopping-cart',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { sort_order: 0 },
    ),
    category(
        15,
        'Apteka',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { group_id: 31, sort_order: 2 },
    ),
    category(
        16,
        'Badania',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2026],
        { group_id: 31, sort_order: 3 },
    ),
    category(
        17,
        'Czynsz',
        'expense',
        'home',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { group_id: 32, sort_order: 5, rhythm: 'fixed_charge' },
    ),
    category(
        18,
        'Prąd',
        'expense',
        'zap',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { group_id: 32, sort_order: 6, rhythm: 'fixed_charge' },
    ),
    category(
        19,
        'Internet',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { group_id: 32, sort_order: 7 },
    ),
    category(20, 'Gaz', 'expense', 'tag', DEFAULT_CATEGORY_COLOR, [2026], {
        group_id: 32,
        sort_order: 8,
    }),
    category(
        23,
        'Woda',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { group_id: 32, sort_order: 9, rhythm: 'even_annual' },
    ),
    category(
        12,
        'Rozrywka',
        'expense',
        'film',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        { sort_order: 11 },
    ),
];

export const categoriesScenarios: CategoriesScenario[] = [
    {
        key: 'default',
        title: 'default: opis krótki (Wynagrodzenie) i długi, ucięty (Żywność); typ zablokowany (te dwie); „Usuń” tylko tam, gdzie can_delete; kategoria bez pozycji (kreska)',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'with-groups',
        title: 'with-groups: grupy z kategoriami wciętymi pod wierszem grupy (Zdrowie: 2, Opłaty stałe: 5), pusta grupa „Podróże” z podpowiedzią, kategorie bez grupy na poziomie typu',
        props: {
            incomeCategories: groupedIncomeCategories,
            expenseCategories: groupedExpenseCategories,
            archivedCategories: [],
            incomeGroups,
            expenseGroups,
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'income-tab',
        title: 'income-tab: zakładka Przychody z grupą „Pensje”, bez kolumny Rytm',
        props: {
            incomeCategories: groupedIncomeCategories,
            expenseCategories,
            archivedCategories: [],
            incomeGroups,
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'income',
            rhythmChangeMonth,
        },
    },
    {
        key: 'archive-tab',
        title: 'archive-tab: zakładka Archiwum z podsekcjami Wydatki i Przychody',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories,
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'archive',
            rhythmChangeMonth,
        },
    },
    {
        key: 'search',
        title: 'search: fraza „zwrot” w Wydatkach (nazwa i opis), liczniki zakładek pokazują trafienia, grupa „1 z 2 kategorii”, bez uchwytów',
        filter: { query: 'zwrot', rhythm: 'all' },
        props: {
            incomeCategories: searchIncomeCategories,
            expenseCategories: searchExpenseCategories,
            archivedCategories: searchArchivedCategories,
            incomeGroups: [],
            expenseGroups: [expenseGroups[0]],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'rhythm-filter',
        title: 'rhythm-filter: rytm „Roczna”, grupa „Dom” z „1 z 5 kategorii” (Meble), Zdrowie, Ubrania i OC bez grupy, grupa „Codzienne” znika',
        filter: { query: '', rhythm: 'even_annual' },
        props: {
            incomeCategories,
            expenseCategories: rhythmFilterExpenseCategories,
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: rhythmFilterGroups,
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'no-results',
        title: 'no-results: fraza bez trafień, komunikat i „Wyczyść filtry”',
        filter: { query: 'zzz', rhythm: 'all' },
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories,
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'archive-empty',
        title: 'archive-empty: zakładka Archiwum bez żadnej archiwalnej kategorii, komunikat „archive_empty”',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'archive',
            rhythmChangeMonth,
        },
    },
    {
        key: 'one-type-empty',
        title: 'one-type-empty: brak aktywnych przychodów, sekcja z komunikatem',
        props: {
            incomeCategories: [],
            expenseCategories,
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'only-empty-group',
        title: 'only-empty-group: sekcja przychodów ma tylko pustą grupę, bez komunikatu „Brak aktywnych kategorii”',
        props: {
            incomeCategories: [],
            expenseCategories,
            archivedCategories: [],
            incomeGroups: [
                {
                    id: 35,
                    name: 'Pusta grupa',
                    sort_order: 0,
                    archived_count: 0,
                },
            ],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
    {
        key: 'only-archived',
        title: 'only-archived: wszystkie kategorie w archiwum, „0 aktywnych”',
        props: {
            incomeCategories: [],
            expenseCategories: [],
            archivedCategories,
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'archive',
            rhythmChangeMonth,
        },
    },
    {
        key: 'long-axis',
        title: 'long-axis: oś lat 2012–2027 (16 komórek), czy dwucyfrowe skróty mieszczą się w pasku',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
        yearAxis: Array.from({ length: 16 }, (_, index) => 2012 + index),
    },
    {
        key: 'empty',
        title: 'empty: użytkownik bez kategorii',
        props: {
            incomeCategories: [],
            expenseCategories: [],
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: [],
            categoryAppearanceOptions,
            tab: 'expense',
            rhythmChangeMonth,
        },
    },
];

const noMonthPlanItems: CategoryMergePreviewData['month_plan_items'] = {
    count: 0,
    first: null,
    last: null,
};

/** The production pair: the source lives only in 2026, the target has no line there yet. */
export const mergeMovePreview: CategoryMergePreviewData = {
    source: { id: 1, name: 'Jedzenie poza domem', archived: false },
    target: { id: 2, name: 'Restauracje' },
    budgets: [
        {
            year: 2026,
            phase: 'execution',
            operations_count: 48,
            target_plan_before_minor_units: 0,
            target_plan_after_minor_units: 300000,
        },
    ],
    operations_count: 48,
    month_plan_items: noMonthPlanItems,
    collected: null,
    restored_budget_year: null,
};

/** Both Categories have a line in 2026 (their plans add up) and the 2027 draft only has the source. */
export const mergeFoldPreview: CategoryMergePreviewData = {
    source: { id: 1, name: 'Jedzenie poza domem', archived: false },
    target: { id: 2, name: 'Restauracje' },
    budgets: [
        {
            year: 2026,
            phase: 'execution',
            operations_count: 48,
            target_plan_before_minor_units: 150000,
            target_plan_after_minor_units: 450000,
        },
        {
            year: 2027,
            phase: 'draft',
            operations_count: 0,
            target_plan_before_minor_units: 0,
            target_plan_after_minor_units: 300000,
        },
    ],
    operations_count: 48,
    month_plan_items: {
        count: 11,
        first: { year: 2026, month: 1 },
        last: { year: 2026, month: 11 },
    },
    collected: null,
    restored_budget_year: null,
};

/** Both Categories are Irregular: the target starts a new period with the sum of their Collected. */
export const mergeCollectedPreview: CategoryMergePreviewData = {
    ...mergeMovePreview,
    source: { id: 1, name: 'Energia', archived: false },
    target: { id: 2, name: 'Prąd' },
    collected: {
        source_minor_units: 40000,
        target_minor_units: 45000,
        after_minor_units: 85000,
        since: { year: 2026, month: 11 },
    },
};

/** The target is not Irregular, so the source's Collected is lost. */
export const mergeCollectedLostPreview: CategoryMergePreviewData = {
    ...mergeCollectedPreview,
    collected: {
        source_minor_units: 40000,
        target_minor_units: null,
        after_minor_units: null,
        since: { year: 2026, month: 11 },
    },
};

/** An archived source whose 2026 line comes back to the Budget totals. */
export const mergeArchivedSourcePreview: CategoryMergePreviewData = {
    ...mergeMovePreview,
    source: { id: 1, name: 'Wakacje', archived: true },
    target: { id: 2, name: 'Urlop' },
    restored_budget_year: 2026,
};
