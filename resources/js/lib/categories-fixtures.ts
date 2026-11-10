import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type {
    CategoriesPageProps,
    CategoryGroupItem,
    CategoryListItem,
} from '@/types/categories';

export type CategoriesScenario = {
    key: string;
    title: string;
    props: CategoriesPageProps;
};

function category(
    id: number,
    name: string,
    type: CategoryListItem['type'],
    icon: string,
    color: string,
    budgetYears: number[],
    archivedAt: string | null = null,
    groupId: number | null = null,
    sortOrder: number = id,
): CategoryListItem {
    return {
        id,
        name,
        icon,
        color,
        type,
        group_id: groupId,
        sort_order: sortOrder,
        budget_years: budgetYears,
        archived_at: archivedAt,
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
        [2026, 2027],
    ),
    category(
        12,
        'Rozrywka',
        'expense',
        'film',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
    ),
    category(
        13,
        'Rekolekcje',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2027],
    ),
    category(
        14,
        'Bez pozycji w budżecie',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [],
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
        '2026-09-09',
        32,
    ),
    category(
        22,
        'Stary kredyt, który miał bardzo długą nazwę i nie mieści się w jednym wierszu',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2025, 2026],
        '2026-01-15',
    ),
];

/**
 * Kategorie z grupami (ADR-0066). Jeden ciąg miejsc na typ: grupa „Zdrowie”
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
        null,
        34,
        1,
    ),
    category(
        2,
        'Odsetki',
        'income',
        'percent',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        null,
        2,
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
        null,
        null,
        0,
    ),
    category(
        15,
        'Apteka',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        31,
        2,
    ),
    category(
        16,
        'Badania',
        'expense',
        'heart',
        DEFAULT_CATEGORY_COLOR,
        [2026],
        null,
        31,
        3,
    ),
    category(
        17,
        'Czynsz',
        'expense',
        'home',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        32,
        5,
    ),
    category(
        18,
        'Prąd',
        'expense',
        'zap',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        32,
        6,
    ),
    category(
        19,
        'Internet',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        32,
        7,
    ),
    category(
        20,
        'Gaz',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2026],
        null,
        32,
        8,
    ),
    category(
        23,
        'Woda',
        'expense',
        'tag',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        32,
        9,
    ),
    category(
        12,
        'Rozrywka',
        'expense',
        'film',
        DEFAULT_CATEGORY_COLOR,
        [2026, 2027],
        null,
        null,
        11,
    ),
];

export const categoriesScenarios: CategoriesScenario[] = [
    {
        key: 'default',
        title: 'default: przychody i wydatki z latami pozycji, kategoria bez pozycji (kreska), bez grup',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories: [],
            incomeGroups: [],
            expenseGroups: [],
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
        },
    },
    {
        key: 'with-archive',
        title: 'with-archive: zwinięte archiwum „Archiwum (n)”',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories,
            incomeGroups: [],
            expenseGroups: [],
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
        },
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
        },
    },
];
