import { useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import AccountReconciliationController from '@/actions/App/Http/Controllers/AccountReconciliationController';
import { useTranslations } from '@/composables/useTranslations';
import type { ReconciliationPreview } from '@/types/accounts';

export type ReconciliationPreviewState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'loaded'; preview: ReconciliationPreview }
    | { status: 'error'; message: string };

/**
 * Reads the Balance on a Date and the operations since the previous reconciliation
 * (ADR-0091) for `accountId` on `day`, again on every change of the day. A response
 * of a request that a newer one replaced is dropped; closing the dialog or an empty
 * day resets to `idle`. With `enabled` false (the kitchen sink supplies its own
 * state) nothing is read.
 */
export function useBalanceReconciliationPreview(
    open: Ref<boolean>,
    accountId: Ref<number | null>,
    day: Ref<string>,
    enabled: Ref<boolean>,
) {
    const t = useTranslations();
    const http = useHttp();
    const state = ref<ReconciliationPreviewState>({ status: 'idle' });
    let requestId = 0;

    async function load(account: number, reconciledOn: string): Promise<void> {
        const currentRequest = ++requestId;
        const rejected: { message: string | null } = { message: null };

        state.value = { status: 'loading' };

        try {
            // A 422 (a day the server refuses) does not throw: useHttp passes
            // it to onError and resolves.
            const response = (await http.submit(
                AccountReconciliationController.preview(account, {
                    query: { reconciled_on: reconciledOn },
                }),
                {
                    onError: (errors) => {
                        rejected.message =
                            Object.values(errors)[0] ??
                            t.accounts.reconcile_preview_failed;
                    },
                },
            )) as ReconciliationPreview;

            if (currentRequest !== requestId) {
                return;
            }

            state.value =
                rejected.message === null
                    ? { status: 'loaded', preview: response }
                    : { status: 'error', message: rejected.message };
        } catch (error) {
            // A request cancelled on purpose is not a failure; anything else
            // is logged with its cause.
            if (error instanceof Error && error.name === 'HttpCancelledError') {
                return;
            }

            console.error(
                'Balance reconciliation: loading the preview failed',
                error,
            );

            if (currentRequest === requestId) {
                state.value = {
                    status: 'error',
                    message: t.accounts.reconcile_preview_failed,
                };
            }
        }
    }

    watch(
        [open, accountId, day, enabled],
        () => {
            requestId++;

            if (
                !enabled.value ||
                !open.value ||
                accountId.value === null ||
                day.value === ''
            ) {
                state.value = { status: 'idle' };

                return;
            }

            void load(accountId.value, day.value);
        },
        { immediate: true },
    );

    return { state };
}
