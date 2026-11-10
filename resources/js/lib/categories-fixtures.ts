import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type {
    CategoriesPageProps,
    CategoryGroupItem,
    CategoryListItem,
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
};

type CategoryFixtureOptions = Partial<
    Pick<
        CategoryListItem,
        | 'description'
        | 'rhythm'
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
            rhythmChangeMonth,
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
            categoryAppearanceOptions,
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
            rhythmChangeMonth,
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
            categoryAppearanceOptions,
            rhythmChangeMonth,
        },
    },
];
