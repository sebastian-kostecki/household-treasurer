export type ShortcutInput = {
    key: string;
    ctrlKey: boolean;
    metaKey: boolean;
    altKey: boolean;
    shiftKey: boolean;
    isComposing: boolean;
    repeat: boolean;
    targetIsEditable: boolean;
    hasOpenLayer: boolean;
};

export type GlobalShortcut =
    | { kind: 'view'; index: number }
    | { kind: 'add-operation' }
    | { kind: 'focus-search' };

/**
 * Decides which global shortcut a key press triggers, or null when the page
 * must keep the key: with Ctrl, ⌘ or Alt held, during IME composition, on a
 * held key, in a text field and under an open layer (dialog, menu, listbox).
 * `/` keeps Shift allowed, because it is Shift+7 on the Polish keyboard; the
 * digits and `N` do not, so `Shift+2` (an `@`) never switches the view.
 */
export function resolveGlobalShortcut(
    input: ShortcutInput,
    viewCount: number,
): GlobalShortcut | null {
    if (
        input.ctrlKey ||
        input.metaKey ||
        input.altKey ||
        input.isComposing ||
        input.repeat ||
        input.targetIsEditable ||
        input.hasOpenLayer
    ) {
        return null;
    }

    if (input.key === '/') {
        return { kind: 'focus-search' };
    }

    if (input.shiftKey && input.key !== 'N') {
        return null;
    }

    if (input.key === 'n' || input.key === 'N') {
        return { kind: 'add-operation' };
    }

    if (/^[1-9]$/.test(input.key)) {
        const index = Number(input.key) - 1;

        return index < viewCount ? { kind: 'view', index } : null;
    }

    return null;
}
