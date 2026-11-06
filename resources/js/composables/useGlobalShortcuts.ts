import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { openCommandPalette } from '@/composables/useCommandPalette';
import { useAppViews } from '@/composables/useAppViews';
import { requestOperationsIntent } from '@/composables/useOperationsIntent';
import { resolveGlobalShortcut } from '@/lib/global-shortcuts';

const EDITABLE_TARGET =
    'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable=""], [contenteditable="true"]';

const OPEN_LAYER =
    '[role="dialog"][data-state="open"], [role="menu"][data-state="open"], [role="listbox"][data-state="open"]';

function isEditableTarget(target: EventTarget | null): boolean {
    if (!(target instanceof Element)) {
        return false;
    }

    return (
        (target instanceof HTMLElement && target.isContentEditable) ||
        target.closest(EDITABLE_TARGET) !== null
    );
}

/**
 * One `keydown` listener for the app layout: translates the event into the
 * input of `resolveGlobalShortcut` and runs the shortcut it returns.
 */
export function useGlobalShortcuts(): void {
    const views = useAppViews();

    function onKeydown(event: KeyboardEvent): void {
        const shortcut = resolveGlobalShortcut(
            {
                key: event.key,
                ctrlKey: event.ctrlKey,
                metaKey: event.metaKey,
                altKey: event.altKey,
                shiftKey: event.shiftKey,
                isComposing: event.isComposing,
                repeat: event.repeat,
                targetIsEditable: isEditableTarget(event.target),
                hasOpenLayer: document.querySelector(OPEN_LAYER) !== null,
            },
            views.length,
        );

        if (shortcut === null) {
            return;
        }

        event.preventDefault();

        if (shortcut.kind === 'view') {
            router.visit(views[shortcut.index].href);

            return;
        }

        if (shortcut.kind === 'palette') {
            openCommandPalette();

            return;
        }

        requestOperationsIntent(shortcut.kind);
    }

    onMounted(() => {
        document.addEventListener('keydown', onKeydown);
    });

    onUnmounted(() => {
        document.removeEventListener('keydown', onKeydown);
    });
}
