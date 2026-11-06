export type PaletteCategory = {
    id: number;
    name: string;
    type: 'expense' | 'income';
    icon: string;
    color: string;
    execution_minor_units: number;
};

export type PaletteItem =
    | { kind: 'view'; index: number }
    | { kind: 'add-operation' }
    | { kind: 'import' }
    | { kind: 'add-budget-year' }
    | { kind: 'settings' }
    | { kind: 'category'; category: PaletteCategory };

export type PaletteAction = {
    key: string;
    item: PaletteItem;
    label: string;
    shortcut?: string;
};

export type PaletteActionLabels = {
    addOperation: string;
    importCsv: string;
    addBudgetYear: string;
    settings: string;
};

/**
 * The "Widoki i akcje" group in its fixed order: the views with their
 * shortcuts, then "Dodaj operację" (N), "Importuj CSV", "Dodaj rok budżetu"
 * and "Ustawienia".
 */
export function buildPaletteActions(
    views: { title: string; shortcut?: string }[],
    labels: PaletteActionLabels,
): PaletteAction[] {
    return [
        ...views.map((view, index): PaletteAction => ({
            key: `view:${index}`,
            item: { kind: 'view', index },
            label: view.title,
            shortcut: view.shortcut,
        })),
        {
            key: 'add-operation',
            item: { kind: 'add-operation' },
            label: labels.addOperation,
            shortcut: 'N',
        },
        {
            key: 'import',
            item: { kind: 'import' },
            label: labels.importCsv,
        },
        {
            key: 'add-budget-year',
            item: { kind: 'add-budget-year' },
            label: labels.addBudgetYear,
        },
        {
            key: 'settings',
            item: { kind: 'settings' },
            label: labels.settings,
        },
    ];
}

/** The text the search matches for an action: its label. */
export function paletteActionSearchValue(action: PaletteAction): string {
    return action.label;
}

/** The text the search matches for a category: its name. */
export function paletteCategorySearchValue(category: PaletteCategory): string {
    return category.name;
}

/**
 * The same text without Polish diacritics ("Żywność" -> "Zywnosc"), so a query
 * typed on a keyboard without them still matches. `ł` has no decomposition,
 * so it is mapped by hand.
 */
export function foldDiacritics(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .replace(/ł/g, 'l')
        .replace(/Ł/g, 'L');
}

/** The hint on the sidebar button: ⌘K on macOS and iOS, Ctrl+K elsewhere. */
export function paletteShortcutLabel(platform: string): '⌘K' | 'Ctrl+K' {
    return /^(mac|iphone|ipad|ipod)/i.test(platform) ? '⌘K' : 'Ctrl+K';
}
