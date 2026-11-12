import type { CategoryPickerOption } from './category-appearance';
import { activeCountForm } from './category-list';
import { buildCategoryTree } from './category-tree';
import type { CategoryGroupItem, CategoryListItem } from '../types/categories';

type YearMonth = { year: number; month: number };

const ROMAN_MONTHS = [
    'I',
    'II',
    'III',
    'IV',
    'V',
    'VI',
    'VII',
    'VIII',
    'IX',
    'X',
    'XI',
    'XII',
];

/**
 * The Categories a Category can be merged into (ADR-0078): active, of the
 * source's type, not the source itself, in the order of the list tree. A
 * Category in a group carries the group so `CategorySelect` can label the block.
 * `groups` are the groups of the source's type.
 */
export function mergeTargetOptions(
    source: CategoryListItem,
    categories: CategoryListItem[],
    groups: CategoryGroupItem[],
): CategoryPickerOption[] {
    const candidates = categories.filter(
        (category) =>
            category.id !== source.id &&
            category.type === source.type &&
            category.archived_at === null,
    );

    return buildCategoryTree(groups, candidates).flatMap((item) => {
        if (item.kind === 'category') {
            return [toOption(item.category, null)];
        }

        const group = { id: item.group.id, name: item.group.name };

        return item.categories.map((category) => toOption(category, group));
    });
}

function toOption(
    category: CategoryListItem,
    group: { id: number; name: string } | null,
): CategoryPickerOption {
    return {
        id: category.id,
        name: category.name,
        icon: category.icon,
        color: category.color,
        group,
    };
}

/** Whether another active Category of the source's type exists to merge into. */
export function canMergeCategory(
    source: CategoryListItem,
    categories: CategoryListItem[],
): boolean {
    return categories.some(
        (category) =>
            category.id !== source.id &&
            category.type === source.type &&
            category.archived_at === null,
    );
}

/** „XI 2026”, „I–XI 2026” or, across a year boundary, „XI 2026–II 2027”. */
export function monthRangeLabel(first: YearMonth, last: YearMonth): string {
    const from = ROMAN_MONTHS[first.month - 1];
    const to = ROMAN_MONTHS[last.month - 1];

    if (first.year === last.year) {
        return first.month === last.month
            ? `${from} ${first.year}`
            : `${from}–${to} ${first.year}`;
    }

    return `${from} ${first.year}–${to} ${last.year}`;
}

type PlanItemsCopy = Record<
    'merge_plan_items_one' | 'merge_plan_items_few' | 'merge_plan_items_many',
    string
>;

/** The line about Month Plan items that move to the target, null when none do. */
export function monthPlanItemsLine(
    items: {
        count: number;
        first: YearMonth | null;
        last: YearMonth | null;
    },
    copy: PlanItemsCopy,
): string | null {
    if (items.count === 0 || items.first === null || items.last === null) {
        return null;
    }

    return copy[`merge_plan_items_${activeCountForm(items.count)}`]
        .replace(':count', String(items.count))
        .replace(':range', monthRangeLabel(items.first, items.last));
}
