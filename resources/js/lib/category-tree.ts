/**
 * The tree of a type's list: groups and Categories without a group on the top
 * level in the order of the sequence, the Categories of a group inside it
 * (ADR-0066). The same tie rule as `CategoryListOrder` in PHP: `sort_order`,
 * a group before a Category, then the position in the input list. The server
 * sends both lists ordered by (`sort_order`, `name_key`, id), so the position
 * stands for `name_key` and id, and the client needs no `name_key`.
 * A Category whose group is unknown counts as one without a group.
 */

export type TreeGroupSource = { id: number; sort_order: number };
export type TreeCategorySource = {
    id: number;
    group_id: number | null;
    sort_order: number;
};

export type TreeGroup<G, C> = {
    kind: 'group';
    group: G;
    categories: C[];
};

export type TreeCategory<C> = {
    kind: 'category';
    category: C;
};

export type CategoryTreeItem<G, C> = TreeGroup<G, C> | TreeCategory<C>;

export type ReorderItem =
    | { kind: 'group'; id: number; category_ids: number[] }
    | { kind: 'category'; id: number };

type Ranked<T> = { rank: number; sortOrder: number; index: number; value: T };

function byPlace(a: Ranked<unknown>, b: Ranked<unknown>): number {
    return a.sortOrder - b.sortOrder || a.rank - b.rank || a.index - b.index;
}

export function buildCategoryTree<
    G extends TreeGroupSource,
    C extends TreeCategorySource,
>(groups: G[], categories: C[]): CategoryTreeItem<G, C>[] {
    const knownGroupIds = new Set(groups.map((group) => group.id));
    const members = new Map<number, Ranked<C>[]>();
    const top: Ranked<
        { kind: 'group'; group: G } | { kind: 'category'; category: C }
    >[] = groups.map((group, index) => ({
        rank: 0,
        sortOrder: group.sort_order,
        index,
        value: { kind: 'group', group },
    }));

    categories.forEach((category, index) => {
        const entry = {
            rank: 1,
            sortOrder: category.sort_order,
            index,
            value: category,
        };

        if (
            category.group_id !== null &&
            knownGroupIds.has(category.group_id)
        ) {
            const list = members.get(category.group_id) ?? [];
            list.push(entry);
            members.set(category.group_id, list);

            return;
        }

        top.push({
            ...entry,
            value: { kind: 'category', category },
        });
    });

    return top.sort(byPlace).map((entry): CategoryTreeItem<G, C> => {
        if (entry.value.kind === 'category') {
            return { kind: 'category', category: entry.value.category };
        }

        const group = entry.value.group;

        return {
            kind: 'group',
            group,
            categories: (members.get(group.id) ?? [])
                .sort(byPlace)
                .map((member) => member.value),
        };
    });
}

/** The payload `items` of `categories.reorder-list` (and of the Budget's reorder). */
export function treeToReorderItems<
    G extends { id: number },
    C extends { id: number },
>(tree: CategoryTreeItem<G, C>[]): ReorderItem[] {
    return tree.map((item): ReorderItem => {
        if (item.kind === 'category') {
            return { kind: 'category', id: item.category.id };
        }

        return {
            kind: 'group',
            id: item.group.id,
            category_ids: item.categories.map((category) => category.id),
        };
    });
}

/**
 * „W górę / W dół”: moves a group one place on the type's top level, past the
 * neighbouring group or Category without a group. At the edges the tree is
 * returned unchanged.
 */
export function moveTopLevelItem<G extends { id: number }, C>(
    tree: CategoryTreeItem<G, C>[],
    groupId: number,
    direction: 'up' | 'down',
): CategoryTreeItem<G, C>[] {
    const index = tree.findIndex(
        (item) => item.kind === 'group' && item.group.id === groupId,
    );
    const target = direction === 'up' ? index - 1 : index + 1;

    if (index === -1 || target < 0 || target >= tree.length) {
        return tree;
    }

    const moved = [...tree];
    [moved[index], moved[target]] = [moved[target], moved[index]];

    return moved;
}

/**
 * Dragging moves the raw element between the top list (which holds tree items)
 * and a group list (which holds bare Categories). These two functions put an
 * element that arrived from the other list back into the shape its list expects.
 */
export function toTreeItem<G, C extends object>(
    element: CategoryTreeItem<G, C> | C,
): CategoryTreeItem<G, C> {
    return 'kind' in element
        ? (element as CategoryTreeItem<G, C>)
        : { kind: 'category', category: element };
}

export function toGroupMember<G, C extends object>(
    element: CategoryTreeItem<G, C> | C,
): C {
    return 'kind' in element && element.kind === 'category'
        ? (element as TreeCategory<C>).category
        : (element as C);
}
