import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export function notifyBudgetYearSelectionFailed(message: string): void {
    toast.error(message);
}

/**
 * Inertia's `<Form>` and `<Link>` expose `onError` only for validation errors.
 * HTTP 4xx/5xx and network failures surface as router events, so we listen to
 * them while a year-selection request is in flight. Returns an unsubscribe fn.
 */
export function listenForBudgetYearSelectionFailure(
    isPending: () => boolean,
    message: string,
): () => void {
    const notify = (): void => {
        if (isPending()) {
            notifyBudgetYearSelectionFailed(message);
        }
    };

    const offHttpException = router.on('httpException', notify);
    const offNetworkError = router.on('networkError', notify);

    return () => {
        offHttpException();
        offNetworkError();
    };
}
