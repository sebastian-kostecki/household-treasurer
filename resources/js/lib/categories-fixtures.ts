import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { CategoriesPageProps, CategoryListItem } from '@/types/categories';

const categoryAppearanceOptions: CategoriesPageProps['categoryAppearanceOptions'] =
    {
        icons: ['tag', 'wallet', 'percent', 'shopping-cart', 'film', 'plane'],
        colors: [DEFAULT_CATEGORY_COLOR],
    };

export type CategoriesScenario = {
    key: string;
    title: string;
    props: CategoriesPageProps;
};

type CategoryFixtureOptions = Partial<
    Pick<
        CategoryListItem,
        'description' | 'can_change_type' | 'can_delete' | 'archived_at'
    >
>;

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
        description: options.description ?? null,
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
        [2026, 2027],
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
        [2026, 2027],
        { can_delete: false },
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
        { archived_at: '2026-09-09', description: shortDescription },
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
];

export const categoriesScenarios: CategoriesScenario[] = [
    {
        key: 'default',
        title: 'default: opis krótki (Wynagrodzenie) i długi, ucięty (Żywność); typ zablokowany (te dwie); „Usuń” tylko tam, gdzie can_delete; kategoria bez pozycji (kreska)',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories: [],
            categoryAppearanceOptions,
        },
    },
    {
        key: 'with-archive',
        title: 'with-archive: zwinięte archiwum „Archiwum (n)”',
        props: {
            incomeCategories,
            expenseCategories,
            archivedCategories,
            categoryAppearanceOptions,
        },
    },
    {
        key: 'one-type-empty',
        title: 'one-type-empty: brak aktywnych przychodów, sekcja z komunikatem',
        props: {
            incomeCategories: [],
            expenseCategories,
            archivedCategories: [],
            categoryAppearanceOptions,
        },
    },
    {
        key: 'only-archived',
        title: 'only-archived: wszystkie kategorie w archiwum, „0 aktywnych”',
        props: {
            incomeCategories: [],
            expenseCategories: [],
            archivedCategories,
            categoryAppearanceOptions,
        },
    },
    {
        key: 'empty',
        title: 'empty: użytkownik bez kategorii',
        props: {
            incomeCategories: [],
            expenseCategories: [],
            archivedCategories: [],
            categoryAppearanceOptions,
        },
    },
];
