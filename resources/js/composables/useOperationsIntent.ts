import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { operations } from '@/routes';

export type OperationsIntent = 'add-operation' | 'focus-search';

const operationsIntent = ref<OperationsIntent | null>(null);

function clearOperationsIntent(): void {
    operationsIntent.value = null;
}

/**
 * The intent the Operations page acts on once it is shown ("open the add
 * menu", "focus the search"); the page clears it after using it.
 */
export function useOperationsIntent() {
    return { operationsIntent, clearOperationsIntent };
}

/**
 * Asks for an intent on the Operations page: on that page it only sets the
 * intent, anywhere else it also visits Operations, which consumes it. The
 * intent is dropped when the visit finishes, used or not.
 */
export function requestOperationsIntent(intent: OperationsIntent): void {
    operationsIntent.value = intent;

    if (usePage().component === 'Operations') {
        return;
    }

    router.visit(operations(), { onFinish: clearOperationsIntent });
}
