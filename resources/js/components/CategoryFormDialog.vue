<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import CategoryChip from '@/components/CategoryChip.vue';
import CategoryColorPicker from '@/components/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/CategoryIconPicker.vue';
import FormField from '@/components/FormField.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    is_paced: boolean;
    month_item_kind: 'bill' | 'running';
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    budgetId: number;
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
const isPaced = ref(true);
const monthItemKind = ref<'bill' | 'running'>('running');
const busy = ref(false);

const plannedAmountMinorUnits = computed(() =>
    parseMoneyInputToMinorUnits(plannedAmountInput.value),
);
const plannedAmountInvalid = computed(
    () => plannedAmountMinorUnits.value === null,
);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? CategoryController.store.form(props.budgetId)
        : CategoryController.update.form(props.category ?? { id: 0 });
});

const typeOptions = computed(() => [
    { value: 'income' as const, label: t.budget.type_income },
    { value: 'expense' as const, label: t.budget.type_expense },
]);

const monthItemKindOptions = computed(() => [
    { value: 'running' as const, label: t.budget.month_item_kind_running },
    { value: 'bill' as const, label: t.budget.month_item_kind_bill },
]);

const previewName = computed(() => {
    const trimmed = name.value.trim();

    if (trimmed !== '') {
        return trimmed;
    }

    if (props.mode === 'edit' && props.category) {
        return props.category.name;
    }

    return t.budget.category_preview;
});

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
            isPaced.value = props.category.is_paced;
            monthItemKind.value = props.category.month_item_kind;

            return;
        }

        name.value = '';
        icon.value = DEFAULT_CATEGORY_ICON;
        color.value = DEFAULT_CATEGORY_COLOR;
        type.value = props.defaultType;
        plannedAmountInput.value = props.initialPlannedAmountInput ?? '0,00';
        isPaced.value = true;
        monthItemKind.value = 'running';
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

                <div
                    class="bg-background flex items-center gap-4 rounded-md p-4"
                >
                    <CategoryChip
                        v-if="color"
                        :name="previewName"
                        :icon="icon"
                        :color="color"
                    />
                    <p v-else class="text-muted-foreground text-sm">
                        {{ t.budget.category_preview_hint }}
                    </p>
                </div>

                <FormField
                    id="category-name"
                    :label="t.budget.name"
                    :error="errors.name"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="name"
                        v-model="name"
                        required
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="category-type"
                    :label="t.budget.type"
                    group
                    :error="errors.type"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <SegmentedControl
                        v-model="type"
                        :options="typeOptions"
                        :ariaLabelledby="labelId"
                        :ariaDescribedby="describedBy"
                        :ariaInvalid="invalid"
                        name="type"
                        block
                        :disabled="lockType"
                    />
                </FormField>

                <FormField
                    id="category-color"
                    :label="t.budget.category_color"
                    group
                    :error="errors.color"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <CategoryColorPicker
                        :colors="categoryAppearanceOptions.colors"
                        v-model="color"
                        :aria-labelledby="labelId"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                    <input type="hidden" name="color" :value="color ?? ''" />
                </FormField>

                <FormField
                    id="category-icon"
                    :label="t.budget.category_icon"
                    group
                    :error="errors.icon"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <CategoryIconPicker
                        :icons="categoryAppearanceOptions.icons"
                        v-model="icon"
                        :color="color ?? DEFAULT_CATEGORY_COLOR"
                        :aria-labelledby="labelId"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                    <input type="hidden" name="icon" :value="icon" />
                </FormField>

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

                <FormField
                    v-if="type === 'expense'"
                    id="category-is-paced"
                    :label="t.budget.paced_category"
                    :hint="t.budget.paced_category_hint"
                    :error="errors.is_paced"
                    v-slot="{ id, describedBy }"
                >
                    <Checkbox
                        :id="id"
                        v-model="isPaced"
                        :aria-describedby="describedBy"
                    />
                    <input
                        type="hidden"
                        name="is_paced"
                        :value="isPaced ? '1' : '0'"
                    />
                </FormField>

                <FormField
                    v-if="type === 'expense'"
                    id="category-month-item-kind"
                    :label="t.budget.month_item_kind"
                    group
                    :hint="t.budget.month_item_kind_hint"
                    :error="errors.month_item_kind"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <SegmentedControl
                        v-model="monthItemKind"
                        :options="monthItemKindOptions"
                        :ariaLabelledby="labelId"
                        :ariaDescribedby="describedBy"
                        :ariaInvalid="invalid"
                        name="month_item_kind"
                        block
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
