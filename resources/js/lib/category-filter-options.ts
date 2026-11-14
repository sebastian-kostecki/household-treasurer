import type { CategoryPickerOption } from './category-appearance';
import { groupSelectOptions } from './category-tree';
import type { SelectOptionBlock } from './category-tree';

export type CategorySectionLabels = { income: string; expense: string };

export type CategorySection<O extends CategoryPickerOption> = {
    type: 'income' | 'expense';
    label: string;
    blocks: SelectOptionBlock<O>[];
};

/**
 * The options of a filter-like category picker as sections (ADR-0093): income
 * first, then expense, each with the options in the order the server sent
 * them, grouped into labelled blocks by their Category Group. A section
 * without options is left out.
 */
export function categorySections<O extends CategoryPickerOption>(
    options: O[],
    labels: CategorySectionLabels,
): CategorySection<O>[] {
    const types = ['income', 'expense'] as const;

    return types
        .map((type) => ({
            type,
            label: labels[type],
            blocks: groupSelectOptions(
                options.filter((option) => option.type === type),
            ),
        }))
        .filter((section) => section.blocks.length > 0);
}

/** The name of an option, with the archived suffix when it applies. */
export function categoryOptionName(
    option: CategoryPickerOption,
    archivedSuffix?: string,
): string {
    if (!option.is_archived || archivedSuffix === undefined) {
        return option.name;
    }

    return `${option.name} ${archivedSuffix}`;
}
