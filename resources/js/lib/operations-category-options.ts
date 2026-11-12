import type { CategoryPickerOption } from '@/lib/category-appearance';

/** The list Category an existing operation is saved on (FR-019). */
export type SelectedOperationCategory = CategoryPickerOption & {
    archived: boolean;
};

/**
 * Keeps the Category of an existing operation selectable while it is selected,
 * even when the year's options do not contain it (archived on the list or on
 * the year's Budget Line). An archived Category carries the suffix; picking
 * another option drops the appended one, because `selected` is then null.
 */
export function withSelectedCategory<T extends CategoryPickerOption>(
    options: T[],
    selected: SelectedOperationCategory | null,
    archivedSuffix: string,
): CategoryPickerOption[] {
    if (
        selected === null ||
        options.some((option) => option.id === selected.id)
    ) {
        return options;
    }

    return [
        ...options,
        {
            id: selected.id,
            name: selected.archived
                ? `${selected.name} ${archivedSuffix}`
                : selected.name,
            icon: selected.icon,
            color: selected.color,
        },
    ];
}
