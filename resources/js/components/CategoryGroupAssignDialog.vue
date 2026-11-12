<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryGroupController from '@/actions/App/Http/Controllers/CategoryGroupController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import FormField from '@/components/FormField.vue';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryGroupItem, CategoryListItem } from '@/types/categories';

type FormBinding = InstanceType<typeof Form>['$props'];

/** Reka's radio needs a non-empty value, so "Bez grupy" is this one and is sent as ''. */
const NO_GROUP = 'none';

const props = defineProps<{
    category: CategoryListItem | null;
    /** The groups of the Category's type, in list order. */
    groups: CategoryGroupItem[];
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Pre-selects a group other than the current one; used only by the dialogs kitchen sink. */
    initialGroupId?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const t = useTranslations();

function toChoice(groupId: number | null | undefined): string {
    return groupId === null || groupId === undefined
        ? NO_GROUP
        : String(groupId);
}

const currentChoice = computed(() => toChoice(props.category?.group_id));
const choice = ref<string>(currentChoice.value);

// Each opening, and a switch to another Category, starts from its current group.
watch(
    [open, () => props.category?.id],
    () => {
        choice.value =
            props.initialGroupId !== undefined
                ? toChoice(props.initialGroupId)
                : currentChoice.value;
    },
    { immediate: true },
);

const formBinding = computed(
    () =>
        props.form ??
        CategoryGroupController.assign.form({
            category: props.category?.id ?? 0,
        }),
);

const title = computed(() =>
    t.categories.group_assign_title.replace(
        ':name',
        props.category?.name ?? '',
    ),
);

const options = computed(() => [
    { value: NO_GROUP, label: t.categories.group_none },
    ...props.groups.map((group) => ({
        value: String(group.id),
        label: group.name,
    })),
]);
</script>

<template>
    <ConfirmActionDialog
        v-if="category !== null"
        v-model:open="open"
        :form="formBinding"
        :options="{ preserveScroll: true }"
        :title="title"
        :description="t.categories.group_assign_description"
        :confirm-label="t.categories.group_assign_confirm"
        :cancel-label="t.categories.cancel"
        :confirm-disabled="choice === currentChoice"
    >
        <FormField
            group
            :label="t.categories.group_assign_label"
            v-slot="{ id, labelId }"
        >
            <input
                type="hidden"
                name="category_group_id"
                :value="choice === NO_GROUP ? '' : choice"
            />
            <RadioGroup v-model="choice" :aria-labelledby="labelId">
                <div
                    v-for="option in options"
                    :key="option.value"
                    class="flex items-center gap-2"
                >
                    <RadioGroupItem
                        :id="`${id}-${option.value}`"
                        :value="option.value"
                    />
                    <label
                        :for="`${id}-${option.value}`"
                        class="text-sm leading-5"
                        >{{ option.label }}</label
                    >
                </div>
            </RadioGroup>
        </FormField>
    </ConfirmActionDialog>
</template>
