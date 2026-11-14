import type {
    CategoriesListTab,
    CategoriesPageProps,
    CategoryListItem,
} from '@/types/categories';
import type { Rhythm } from '@/types/rhythm';
import type { CategoryTreeItem, TreeGroup } from './category-tree';
import { matchesSearch } from './text-search';

export type CategoryListFilter = { query: string; rhythm: Rhythm | 'all' };

export const EMPTY_CATEGORY_LIST_FILTER: CategoryListFilter = {
    query: '',
    rhythm: 'all',
};

const RHYTHMS: Rhythm[] = [
    'fixed_charge',
    'running',
    'even_annual',
    'irregular',
];

/** A group of the filtered tree: `visibleCount` of the group's `count` Categories match. */
export type FilteredTreeGroup<G, C> = TreeGroup<G, C> & {
    visibleCount: number;
    count: number;
};

export type FilteredTreeItem<G, C> =
    | FilteredTreeGroup<G, C>
    | Extract<CategoryTreeItem<G, C>, { kind: 'category' }>;

/** The Rhythm counts only for the expense tab; the phrase always does. */
export function isFilterActive(
    filter: CategoryListFilter,
    type: CategoryListItem['type'],
): boolean {
    return (
        filter.query.trim() !== '' ||
        (type === 'expense' && filter.rhythm !== 'all')
    );
}

type Matchable = Pick<
    CategoryListItem,
    'name' | 'description' | 'type' | 'rhythm'
>;

export function categoryMatches(
    category: Matchable,
    filter: CategoryListFilter,
): boolean {
    if (!matchesSearch(filter.query, [category.name, category.description])) {
        return false;
    }

    return (
        category.type !== 'expense' ||
        filter.rhythm === 'all' ||
        category.rhythm === filter.rhythm
    );
}

/**
 * The tree the view shows: groups without a match vanish, a group with
 * matches carries `visibleCount` and its full `count`. Without an active
 * filter every item stays, empty groups included. A tree holds one type.
 */
export function filterCategoryTree<G, C extends Matchable>(
    tree: CategoryTreeItem<G, C>[],
    filter: CategoryListFilter,
): FilteredTreeItem<G, C>[] {
    const first = tree.find(
        (item) => item.kind === 'category' || item.categories.length > 0,
    );
    const type =
        first === undefined
            ? 'expense'
            : first.kind === 'category'
              ? first.category.type
              : first.categories[0].type;
    const active = isFilterActive(filter, type);
    const result: FilteredTreeItem<G, C>[] = [];

    for (const item of tree) {
        if (item.kind === 'category') {
            if (!active || categoryMatches(item.category, filter)) {
                result.push(item);
            }

            continue;
        }

        const count = item.categories.length;

        if (!active) {
            result.push({ ...item, visibleCount: count, count });

            continue;
        }

        const categories = item.categories.filter((category) =>
            categoryMatches(category, filter),
        );

        if (categories.length > 0) {
            result.push({
                ...item,
                categories,
                visibleCount: categories.length,
                count,
            });
        }
    }

    return result;
}

type CountableProps = Pick<
    CategoriesPageProps,
    'expenseCategories' | 'incomeCategories' | 'archivedCategories'
>;

export function tabCounts(
    props: CountableProps,
    filter: CategoryListFilter,
): Record<CategoriesListTab, number> {
    const matching = (items: CategoryListItem[]) =>
        items.filter((category) => categoryMatches(category, filter)).length;

    return {
        expense: matching(props.expenseCategories),
        income: matching(props.incomeCategories),
        archive: matching(props.archivedCategories),
    };
}

export function rhythmCounts(
    categories: CategoryListItem[],
    query: string,
): Record<Rhythm, number> {
    const counts = Object.fromEntries(
        RHYTHMS.map((rhythm) => [rhythm, 0]),
    ) as Record<Rhythm, number>;

    for (const category of categories) {
        if (
            category.type === 'expense' &&
            category.rhythm !== null &&
            matchesSearch(query, [category.name, category.description])
        ) {
            counts[category.rhythm] += 1;
        }
    }

    return counts;
}

export function archiveSections(
    archived: CategoryListItem[],
    filter: CategoryListFilter,
): { expense: CategoryListItem[]; income: CategoryListItem[] } {
    const matching = archived.filter((category) =>
        categoryMatches(category, filter),
    );

    return {
        expense: matching.filter((category) => category.type === 'expense'),
        income: matching.filter((category) => category.type === 'income'),
    };
}
