import { useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import CategoryMergeController from '@/actions/App/Http/Controllers/CategoryMergeController';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryMergePreviewData } from '@/types/categories';

export type CategoryMergePreviewState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'loaded'; preview: CategoryMergePreviewData }
    | { status: 'error'; message: string };

/**
 * Reads the preview of merging `sourceId` into `targetId` (ADR-0078) on every
 * change of the target. A response of a request that a newer one replaced is
 * dropped; closing the dialog or clearing the target resets to `idle`.
 * With `enabled` false (the kitchen sink supplies its own state) nothing is read.
 */
export function useCategoryMergePreview(
    open: Ref<boolean>,
    sourceId: Ref<number | null>,
    targetId: Ref<number | ''>,
    enabled: Ref<boolean>,
) {
    const t = useTranslations();
    const http = useHttp();
    const state = ref<CategoryMergePreviewState>({ status: 'idle' });
    let requestId = 0;

    async function load(source: number, target: number): Promise<void> {
        const currentRequest = ++requestId;
        const rejected: { message: string | null } = { message: null };

        state.value = { status: 'loading' };

        try {
            // A 422 (a target the server refuses) does not throw: useHttp
            // passes it to onError and resolves.
            const response = (await http.submit(
                CategoryMergeController.show(source, {
                    query: { target_id: target },
                }),
                {
                    onError: (errors) => {
                        rejected.message =
                            Object.values(errors)[0] ??
                            t.categories.merge_preview_failed;
                    },
                },
            )) as CategoryMergePreviewData;

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

            console.error('Category merge: loading the preview failed', error);

            if (currentRequest === requestId) {
                state.value = {
                    status: 'error',
                    message: t.categories.merge_preview_failed,
                };
            }
        }
    }

    watch(
        [open, sourceId, targetId, enabled],
        () => {
            requestId++;

            if (
                !enabled.value ||
                !open.value ||
                sourceId.value === null ||
                targetId.value === ''
            ) {
                state.value = { status: 'idle' };

                return;
            }

            void load(sourceId.value, targetId.value);
        },
        { immediate: true },
    );

    return { state };
}
