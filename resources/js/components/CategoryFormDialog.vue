<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import CategoryAppearanceFields from '@/components/CategoryAppearanceFields.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import {
    DEFAULT_CATEGORY_COLOR,
    DEFAULT_CATEGORY_ICON,
    type CategoryAppearanceOptions,
} from '@/lib/category-appearance';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';

type FormBinding = InstanceType<typeof Form>['$props'];

export type CategoryFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    planned_amount_minor_units: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    budgetId: number;
    showArchived?: boolean;
    defaultType: 'income' | 'expense';
    category?: CategoryFormCategory | null;
    lockType?: boolean;
    categoryAppearanceOptions: CategoryAppearanceOptions;
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Pre-fills the amount input; used only by the dialogs kitchen sink. */
    initialPlannedAmountInput?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const icon = ref(DEFAULT_CATEGORY_ICON);
const color = ref<string | null>(DEFAULT_CATEGORY_COLOR);
const type = ref<'income' | 'expense'>('income');
const plannedAmountInput = ref('0,00');
const busy = ref(false);

const plannedAmountMinorUnits = computed(() =>
    parseMoneyInputToMinorUnits(plannedAmountInput.value),
);
const plannedAmountInvalid = computed(
    () => plannedAmountMinorUnits.value === null,
);

const writeOptions = computed(() =>
    props.showArchived ? { query: { show_archived: 1 } } : undefined,
);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? CategoryController.store.form(props.budgetId, writeOptions.value)
        : CategoryController.update.form(
              {
                  budget: props.budgetId,
                  category: props.category?.id ?? 0,
              },
              writeOptions.value,
          );
});

const previewFallbackName = computed(() =>
    props.mode === 'edit' && props.category
        ? props.category.name
        : t.budget.category_preview,
);

watch(
    () => [props.open, props.mode, props.category, props.defaultType] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.category) {
            name.value = props.category.name;
            icon.value = props.category.icon;
            color.value = props.category.color;
            type.value = props.category.type;
            plannedAmountInput.value =
                props.initialPlannedAmountInput ??
                minorUnitsToMoneyInput(
                    props.category.planned_amount_minor_units,
                );

            return;
        }

        name.value = '';
        icon.value = DEFAULT_CATEGORY_ICON;
        color.value = DEFAULT_CATEGORY_COLOR;
        type.value = props.defaultType;
        plannedAmountInput.value = props.initialPlannedAmountInput ?? '0,00';
    },
    { immediate: true },
);

function onOpenChange(next: boolean): void {
    if (!next && busy.value) {
        return;
    }

    emit('update:open', next);
}

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent :show-close-button="!busy">
            <Form
                v-bind="formBinding"
                class="grid gap-4"
                reset-on-success
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, reset, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            mode === 'create'
                                ? t.budget.create_category_title
                                : t.budget.edit_category_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.budget.create_category_description
                                : lockType
                                  ? t.budget.edit_category_description_execution
                                  : t.budget.edit_category_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <p v-if="mode === 'edit'" class="meta text-muted-foreground">
                    {{ t.budget.edit_category_shared_note }}
                </p>

                <CategoryAppearanceFields
                    v-model:name="name"
                    v-model:type="type"
                    v-model:color="color"
                    v-model:icon="icon"
                    :category-appearance-options="categoryAppearanceOptions"
                    :errors="errors"
                    :fallback-name="previewFallbackName"
                    :lock-type="lockType"
                />

                <FormField
                    id="category-planned-amount"
                    :label="
                        type === 'income'
                            ? t.budget.planned_amount
                            : t.budget.limit
                    "
                    money
                    :error="
                        plannedAmountInvalid
                            ? t.budget.planned_amount_invalid
                            : errors.planned_amount_minor_units
                    "
                    v-slot="{ id, describedBy, invalid, controlClass }"
                >
                    <Input
                        :id="id"
                        v-model="plannedAmountInput"
                        :class="controlClass"
                        inputmode="decimal"
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                    <input
                        type="hidden"
                        name="planned_amount_minor_units"
                        :value="plannedAmountMinorUnits ?? 0"
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="processing"
                            @click="
                                () => {
                                    clearErrors();
                                    reset();
                                    closeDialog();
                                }
                            "
                        >
                            {{ t.budget.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || plannedAmountInvalid"
                    >
                        <Spinner v-if="processing" />
                        {{ t.budget.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
