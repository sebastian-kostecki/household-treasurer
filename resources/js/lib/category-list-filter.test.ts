import { describe, expect, it } from 'vite-plus/test';
import type { CategoryGroupItem, CategoryListItem } from '@/types/categories';
import type { Rhythm } from '@/types/rhythm';
import {
    archiveSections,
    categoryMatches,
    filterCategoryTree,
    isFilterActive,
    rhythmCounts,
    tabCounts,
    type CategoryListFilter,
} from './category-list-filter';
import { buildCategoryTree } from './category-tree';

const none: CategoryListFilter = { query: '', rhythm: 'all' };

function item(
    id: number,
    name: string,
    type: CategoryListItem['type'],
    rhythm: Rhythm | null,
    options: Partial<CategoryListItem> = {},
): CategoryListItem {
    return {
        id,
        name,
        icon: 'tag',
        color: 'x',
        type,
        group_id: null,
        sort_order: id,
        description: null,
        rhythm,
        opening_collected_minor_units: null,
        irregular_since: null,
        budget_years: [],
        can_change_type: true,
        can_delete: true,
        archived_at: null,
        ...options,
    };
}

function group(id: number, name: string, sortOrder: number): CategoryGroupItem {
    return { id, name, sort_order: sortOrder, archived_count: 0 };
}

const groups = [
    group(1, 'Dom', 1),
    group(2, 'Codzienne', 2),
    group(3, 'Pusta', 3),
];

const expenses = [
    item(10, 'Meble', 'expense', 'even_annual', { group_id: 1 }),
    item(11, 'Czynsz', 'expense', 'fixed_charge', { group_id: 1 }),
    item(12, 'Prąd', 'expense', 'running', { group_id: 1 }),
    item(13, 'Gaz', 'expense', 'running', { group_id: 1 }),
    item(14, 'Woda', 'expense', 'running', { group_id: 1 }),
    item(20, 'Jedzenie', 'expense', 'running', { group_id: 2 }),
    item(21, 'Transport', 'expense', 'running', { group_id: 2 }),
    item(30, 'Zdrowie', 'expense', 'even_annual'),
    item(31, 'Ubrania', 'expense', 'even_annual'),
    item(32, 'Kino', 'expense', 'running'),
];

describe('isFilterActive', () => {
    it('counts the phrase for both types and the Rhythm for expenses only', () => {
        expect(isFilterActive(none, 'expense')).toBe(false);
        expect(isFilterActive({ query: ' ', rhythm: 'all' }, 'expense')).toBe(
            false,
        );
        expect(isFilterActive({ query: 'a', rhythm: 'all' }, 'income')).toBe(
            true,
        );
        expect(
            isFilterActive({ query: '', rhythm: 'running' }, 'expense'),
        ).toBe(true);
        expect(isFilterActive({ query: '', rhythm: 'running' }, 'income')).toBe(
            false,
        );
    });
});

describe('categoryMatches', () => {
    it('matches the phrase in name or description, ignoring diacritics', () => {
        const category = item(1, 'Prąd', 'expense', 'running', {
            description: 'Rachunek za energię',
        });

        expect(categoryMatches(category, { ...none, query: 'prad' })).toBe(
            true,
        );
        expect(categoryMatches(category, { ...none, query: 'energie' })).toBe(
            true,
        );
        expect(categoryMatches(category, { ...none, query: 'woda' })).toBe(
            false,
        );
    });

    it('applies the Rhythm only to expenses', () => {
        const filter: CategoryListFilter = { query: '', rhythm: 'irregular' };

        expect(
            categoryMatches(item(1, 'A', 'expense', 'running'), filter),
        ).toBe(false);
        expect(categoryMatches(item(2, 'B', 'income', null), filter)).toBe(
            true,
        );
    });
});

describe('filterCategoryTree', () => {
    const tree = buildCategoryTree(groups, expenses);

    it('returns every item, empty groups included, without a filter', () => {
        const result = filterCategoryTree(tree, none);

        expect(result).toHaveLength(tree.length);
        expect(result.map((entry) => entry.kind)).toEqual(
            tree.map((entry) => entry.kind),
        );

        const empty = result.find(
            (entry) => entry.kind === 'group' && entry.group.id === 3,
        );

        expect(empty).toMatchObject({ visibleCount: 0, count: 0 });
    });

    it('keeps the matching part of a group with "1 of 5" and drops groups without a match', () => {
        const result = filterCategoryTree(tree, {
            query: '',
            rhythm: 'even_annual',
        });

        expect(result).toHaveLength(3);

        const dom = result[0];

        expect(dom.kind).toBe('group');

        if (dom.kind === 'group') {
            expect(dom.group.name).toBe('Dom');
            expect(dom.categories.map((c) => c.name)).toEqual(['Meble']);
            expect(dom.visibleCount).toBe(1);
            expect(dom.count).toBe(5);
        }

        expect(
            result
                .slice(1)
                .map((entry) =>
                    entry.kind === 'category' ? entry.category.name : 'group',
                ),
        ).toEqual(['Zdrowie', 'Ubrania']);
    });

    it('drops ungrouped Categories that do not match the phrase', () => {
        const result = filterCategoryTree(tree, { ...none, query: 'kino' });

        expect(result).toHaveLength(1);
        expect(result[0]).toMatchObject({
            kind: 'category',
            category: { name: 'Kino' },
        });
    });
});

describe('tabCounts and archiveSections', () => {
    const props = {
        expenseCategories: [item(1, 'Prąd', 'expense', 'running')],
        incomeCategories: [item(2, 'Zwrot podatku', 'income', null)],
        archivedCategories: [
            item(3, 'Zwrot za bilet', 'expense', 'running', {
                archived_at: '2026-01-01',
            }),
            item(4, 'Stary dochód', 'income', null, {
                archived_at: '2026-01-02',
            }),
            item(5, 'Stary wydatek', 'expense', 'irregular', {
                archived_at: '2026-01-03',
            }),
        ],
    };

    it('counts all tabs without a filter', () => {
        expect(tabCounts(props, none)).toEqual({
            expense: 1,
            income: 1,
            archive: 3,
        });
    });

    it('gives 0/1/1 for the phrase "zwrot"', () => {
        expect(tabCounts(props, { ...none, query: 'zwrot' })).toEqual({
            expense: 0,
            income: 1,
            archive: 1,
        });
    });

    it('applies the Rhythm to expenses on every tab but not to income', () => {
        expect(tabCounts(props, { query: '', rhythm: 'irregular' })).toEqual({
            expense: 0,
            income: 1,
            archive: 2,
        });
    });

    it('splits the archive by type in the server order', () => {
        const sections = archiveSections(props.archivedCategories, none);

        expect(sections.expense.map((c) => c.id)).toEqual([3, 5]);
        expect(sections.income.map((c) => c.id)).toEqual([4]);
        expect(
            archiveSections(props.archivedCategories, {
                ...none,
                query: 'zwrot',
            }).income,
        ).toEqual([]);
    });
});

describe('rhythmCounts', () => {
    it('counts expenses by Rhythm after the phrase only', () => {
        expect(rhythmCounts(expenses, '')).toEqual({
            fixed_charge: 1,
            running: 6,
            even_annual: 3,
            irregular: 0,
        });
        expect(rhythmCounts(expenses, 'o')).toEqual({
            fixed_charge: 0,
            running: 3,
            even_annual: 1,
            irregular: 0,
        });
    });
});
