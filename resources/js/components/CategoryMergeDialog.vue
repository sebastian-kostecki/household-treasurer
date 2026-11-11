<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryMergeController from '@/actions/App/Http/Controllers/CategoryMergeController';
import CategoryMergePreview from '@/components/CategoryMergePreview.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import FormField from '@/components/FormField.vue';
import { useCategoryMergePreview } from '@/composables/useCategoryMergePreview';
import type { CategoryMergePreviewState } from '@/composables/useCategoryMergePreview';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryPickerOption } from '@/lib/category-appearance';
import type { CategoryListItem } from '@/types/categories';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = defineProps<{
    source: CategoryListItem | null;
    /** Active Categories of the source's type, in list order (`mergeTargetOptions`). */
    targets: CategoryPickerOption[];
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Replaces the preview read from the server; used only by the dialogs kitchen sink. */
    previewState?: CategoryMergePreviewState;
    /** Pre-selects a target on open; used only by the dialogs kitchen sink. */
    initialTargetId?: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const t = useTranslations();

const targetId = ref<number | ''>('');

// Closing the dialog or switching to another source clears the choice.
watch(
    [open, () => props.source?.id],
    () => {
        targetId.value = open.value ? (props.initialTargetId ?? '') : '';
    },
    { immediate: true },
);

const { state: fetchedState } = useCategoryMergePreview(
    open,
    computed(() => props.source?.id ?? null),
    targetId,
    computed(() => props.previewState === undefined),
);

const state = computed(() => props.previewState ?? fetchedState.value);

const formBinding = computed(
    () =>
        props.form ?? CategoryMergeController.store.form(props.source?.id ?? 0),
);

const title = computed(() =>
    t.categories.merge_title.replace(':name', props.source?.name ?? ''),
);
</script>

<template>
    <ConfirmActionDialog
        v-if="source !== null"
        v-model:open="open"
        destructive
        :form="formBinding"
        :title="title"
        :description="t.categories.merge_description"
        :confirm-label="t.categories.merge_confirm"
        :cancel-label="t.categories.merge_cancel"
        :confirm-disabled="state.status !== 'loaded'"
    >
        <FormField
            :label="t.categories.merge_target"
            v-slot="{ id, describedBy, invalid }"
        >
            <CategorySelect
                :id="id"
                v-model="targetId"
                name="target_id"
                required
                :categories="targets"
                :placeholder="t.categories.merge_target_placeholder"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>
        <CategoryMergePreview :state="state" />
    </ConfirmActionDialog>
</template>
