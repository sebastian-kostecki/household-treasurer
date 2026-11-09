import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { CategoriesPageProps, CategoryListItem } from '@/types/categories';

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
): CategoryListItem {
    return {
        id,
        name,
        icon,
        color,
        type,
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

export const categoriesScenarios: CategoriesScenario[] = [
    {
        key: 'default',
        title: 'default: przychody i wydatki z latami pozycji, kategoria bez pozycji (kreska)',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories: [],
        },
    },
    {
        key: 'with-archive',
        title: 'with-archive: zwinięte archiwum „Archiwum (n)”',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories,
        },
    },
    {
        key: 'one-type-empty',
        title: 'one-type-empty: brak aktywnych przychodów, sekcja z komunikatem',
        props: {
            incomeCategories: [],
            expenseCategories,
            archivedCategories: [],
        },
    },
    {
        key: 'only-archived',
        title: 'only-archived: wszystkie kategorie w archiwum, „0 aktywnych”',
        props: {
            incomeCategories: [],
            expenseCategories: [],
            archivedCategories,
        },
    },
    {
        key: 'empty',
        title: 'empty: użytkownik bez kategorii',
        props: {
            incomeCategories: [],
            expenseCategories: [],
            archivedCategories: [],
        },
    },
];
