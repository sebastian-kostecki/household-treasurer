import { describe, expect, it } from 'vite-plus/test';
import {
    buildCategoryTree,
    groupSelectOptions,
    moveTopLevelItem,
    toGroupMember,
    toTreeItem,
    treeToReorderItems,
} from './category-tree';

const group = (id: number, sort_order: number) => ({
    id,
    name: `G${id}`,
    sort_order,
});
const category = (id: number, group_id: number | null, sort_order: number) => ({
    id,
    name: `C${id}`,
    group_id,
    sort_order,
});

describe('buildCategoryTree', () => {
    it('puts groups and Categories without a group in sequence order, members inside the group', () => {
        const tree = buildCategoryTree(
            [group(1, 1), group(2, 4)],
            [
                category(10, null, 0),
                category(11, 1, 2),
                category(12, 1, 3),
                category(13, 2, 5),
                category(14, null, 6),
            ],
        );

        expect(treeToReorderItems(tree)).toEqual([
            { kind: 'category', id: 10 },
            { kind: 'group', id: 1, category_ids: [11, 12] },
            { kind: 'group', id: 2, category_ids: [13] },
            { kind: 'category', id: 14 },
        ]);
    });

    it('orders members by their own place, not by the order of the input', () => {
        const tree = buildCategoryTree(
            [group(1, 0)],
            [category(11, 1, 5), category(12, 1, 2)],
        );

        expect(treeToReorderItems(tree)).toEqual([
            { kind: 'group', id: 1, category_ids: [12, 11] },
        ]);
    });

    it('puts a group before a Category on the same place', () => {
        const tree = buildCategoryTree([group(1, 3)], [category(10, null, 3)]);

        expect(tree.map((item) => item.kind)).toEqual(['group', 'category']);
    });

    it('breaks a tie between Categories by their position in the input list', () => {
        const tree = buildCategoryTree(
            [],
            [category(20, null, 1), category(10, null, 1)],
        );

        expect(treeToReorderItems(tree)).toEqual([
            { kind: 'category', id: 20 },
            { kind: 'category', id: 10 },
        ]);
    });

    it('treats a Category of an unknown group as one without a group', () => {
        const tree = buildCategoryTree([group(1, 0)], [category(10, 99, 1)]);

        expect(treeToReorderItems(tree)).toEqual([
            { kind: 'group', id: 1, category_ids: [] },
            { kind: 'category', id: 10 },
        ]);
    });

    it('keeps an empty group', () => {
        const tree = buildCategoryTree([group(1, 0)], []);

        expect(tree).toEqual([
            { kind: 'group', group: group(1, 0), categories: [] },
        ]);
    });
});

describe('moveTopLevelItem', () => {
    const tree = buildCategoryTree(
        [group(1, 1), group(2, 2)],
        [category(10, null, 0), category(11, 1, 3)],
    );

    it('moves a group down past the next top-level item', () => {
        expect(
            treeToReorderItems(moveTopLevelItem(tree, 1, 'down')).map(
                (item) => `${item.kind}:${item.id}`,
            ),
        ).toEqual(['category:10', 'group:2', 'group:1']);
    });

    it('moves a group up past a Category without a group', () => {
        expect(
            treeToReorderItems(moveTopLevelItem(tree, 1, 'up')).map(
                (item) => `${item.kind}:${item.id}`,
            ),
        ).toEqual(['group:1', 'category:10', 'group:2']);
    });

    it('leaves the tree at the edges', () => {
        expect(moveTopLevelItem(tree, 2, 'down')).toBe(tree);
        expect(
            moveTopLevelItem(
                buildCategoryTree([group(1, 0)], [category(10, null, 1)]),
                1,
                'up',
            ),
        ).toHaveLength(2);
    });

    it('leaves the tree for an unknown group', () => {
        expect(moveTopLevelItem(tree, 99, 'up')).toBe(tree);
    });
});

describe('toTreeItem and toGroupMember', () => {
    const bare = category(10, null, 0);
    const wrapped = { kind: 'category' as const, category: bare };

    it('wraps a bare Category that came from a group list into a tree item', () => {
        expect(toTreeItem(bare)).toEqual(wrapped);
    });

    it('keeps a tree item as it is', () => {
        expect(toTreeItem(wrapped)).toBe(wrapped);
    });

    it('unwraps a tree item that was dropped into a group list', () => {
        expect(toGroupMember(wrapped)).toBe(bare);
    });

    it('keeps a bare Category as it is', () => {
        expect(toGroupMember(bare)).toBe(bare);
    });

    it('gives a payload with the dropped Category id, never null', () => {
        const tree = [
            {
                kind: 'group' as const,
                group: group(1, 0),
                categories: [toGroupMember(wrapped)],
            },
        ];

        expect(treeToReorderItems(tree)).toEqual([
            { kind: 'group', id: 1, category_ids: [10] },
        ]);
    });
});

describe('groupSelectOptions', () => {
    const health = { id: 1, name: 'Zdrowie' };
    const option = (
        id: number,
        group?: { id: number; name: string } | null,
    ) => ({ id, group });

    it('keeps options without a group as single options', () => {
        expect(groupSelectOptions([option(1), option(2, null)])).toEqual([
            { kind: 'option', option: option(1) },
            { kind: 'option', option: option(2, null) },
        ]);
    });

    it('joins consecutive options of one group under its label', () => {
        expect(
            groupSelectOptions([
                option(1),
                option(2, health),
                option(3, health),
                option(4),
            ]),
        ).toEqual([
            { kind: 'option', option: option(1) },
            {
                kind: 'group',
                group: health,
                options: [option(2, health), option(3, health)],
            },
            { kind: 'option', option: option(4) },
        ]);
    });

    it('starts a new block when a group returns after another option', () => {
        const blocks = groupSelectOptions([
            option(1, health),
            option(2),
            option(3, health),
        ]);

        expect(blocks.map((block) => block.kind)).toEqual([
            'group',
            'option',
            'group',
        ]);
    });

    it('returns an empty list for no options', () => {
        expect(groupSelectOptions([])).toEqual([]);
    });
});
