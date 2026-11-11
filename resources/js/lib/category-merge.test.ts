import { describe, expect, it } from 'vite-plus/test';
import {
    canMergeCategory,
    mergeTargetOptions,
    monthPlanItemsLine,
    monthRangeLabel,
} from './category-merge';
import type { CategoryGroupItem, CategoryListItem } from '../types/categories';

function category(
    id: number,
    name: string,
    overrides: Partial<CategoryListItem> = {},
): CategoryListItem {
    return {
        id,
        name,
        icon: 'tag',
        color: 'blue',
        type: 'expense',
        group_id: null,
        sort_order: id,
        description: null,
        rhythm: 'running',
        opening_collected_minor_units: null,
        irregular_since: null,
        budget_years: [2026],
        can_change_type: true,
        can_delete: true,
        archived_at: null,
        ...overrides,
    };
}

const group: CategoryGroupItem = {
    id: 10,
    name: 'Opłaty',
    sort_order: 2,
    archived_count: 0,
};

describe('mergeTargetOptions', () => {
    const source = category(1, 'Jedzenie poza domem');

    it('lists active Categories of the source type without the source', () => {
        const options = mergeTargetOptions(
            source,
            [
                source,
                category(2, 'Restauracje'),
                category(3, 'Pensja', { type: 'income', rhythm: null }),
                category(4, 'Stara', { archived_at: '2026-01-01' }),
            ],
            [],
        );

        expect(options.map((option) => option.id)).toEqual([2]);
    });

    it('follows the tree order and carries the group of a member', () => {
        const options = mergeTargetOptions(
            source,
            [
                category(2, 'Czynsz', { group_id: 10, sort_order: 5 }),
                category(3, 'Prąd', { group_id: 10, sort_order: 6 }),
                category(4, 'Restauracje', { sort_order: 1 }),
                category(5, 'Kino', { sort_order: 9 }),
            ],
            [group],
        );

        expect(options.map((option) => option.id)).toEqual([4, 2, 3, 5]);
        expect(options.map((option) => option.group?.id ?? null)).toEqual([
            null,
            10,
            10,
            null,
        ]);
        expect(options[1].group).toEqual({ id: 10, name: 'Opłaty' });
    });
});

describe('canMergeCategory', () => {
    const source = category(1, 'A');

    it('is true when another active Category of the type exists', () => {
        expect(canMergeCategory(source, [source, category(2, 'B')])).toBe(true);
    });

    it.each([
        ['only the source', [source]],
        ['only another type', [source, category(2, 'B', { type: 'income' })]],
        [
            'only an archived one',
            [source, category(2, 'B', { archived_at: '2026-01-01' })],
        ],
    ])('is false with %s', (_label, categories) => {
        expect(canMergeCategory(source, categories)).toBe(false);
    });

    it('lets an archived source merge into an active Category', () => {
        const archived = category(1, 'A', { archived_at: '2026-01-01' });

        expect(canMergeCategory(archived, [archived, category(2, 'B')])).toBe(
            true,
        );
    });
});

describe('monthRangeLabel', () => {
    it.each([
        [{ year: 2026, month: 1 }, { year: 2026, month: 11 }, 'I–XI 2026'],
        [{ year: 2026, month: 11 }, { year: 2026, month: 11 }, 'XI 2026'],
        [
            { year: 2026, month: 11 },
            { year: 2027, month: 2 },
            'XI 2026–II 2027',
        ],
    ])('labels %j to %j', (first, last, expected) => {
        expect(monthRangeLabel(first, last)).toBe(expected);
    });
});

describe('monthPlanItemsLine', () => {
    const copy = {
        merge_plan_items_one: ':count pozycja planu miesiąca (:range)',
        merge_plan_items_few: ':count pozycje planów miesięcy (:range)',
        merge_plan_items_many: ':count pozycji planów miesięcy (:range)',
    };
    const first = { year: 2026, month: 1 };
    const last = { year: 2026, month: 11 };

    it.each([
        [1, '1 pozycja planu miesiąca (I–XI 2026)'],
        [2, '2 pozycje planów miesięcy (I–XI 2026)'],
        [4, '4 pozycje planów miesięcy (I–XI 2026)'],
        [5, '5 pozycji planów miesięcy (I–XI 2026)'],
        [12, '12 pozycji planów miesięcy (I–XI 2026)'],
        [22, '22 pozycje planów miesięcy (I–XI 2026)'],
    ])('uses the right form for %i', (count, expected) => {
        expect(monthPlanItemsLine({ count, first, last }, copy)).toBe(expected);
    });

    it('is null when no item moves', () => {
        expect(
            monthPlanItemsLine({ count: 0, first: null, last: null }, copy),
        ).toBeNull();
    });
});
