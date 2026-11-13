<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import SheetOperationCategoryController from '@/actions/App/Http/Controllers/SheetOperationCategoryController';
import CategorySelect from '@/components/CategorySelect.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryPickerGroup } from '@/lib/category-appearance';
import { formatDate, formatMoney } from '@/lib/format';
import { OPERATIONS_RETURN_QUERY } from '@/lib/operations-list-query';
import { withSelectedCategory } from '@/lib/operations-category-options';

type FormBinding = InstanceType<typeof Form>['$props'];

export type OperationCategoryDialogCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    group?: CategoryPickerGroup | null;
    budget_year: number;
};

export type OperationCategoryDialogOperation = {
    id: number;
    type: 'income' | 'expense';
    operation_date: string;
    amount_minor_units: number;
    description: string;
    account_name: string;
    category_id: number;
    category_label: string;
    category_icon: string;
    category_color: string;
    category_archived: boolean;
};

/**
 * Changes only the Category of an operation of a Sheet Account (ADR-0089):
 * amount, date, Account and description are read-only text, the Category is
 * chosen from the operation's year and type.
 */
const props = defineProps<{
    open: boolean;
    operation: OperationCategoryDialogOperation | null;
    incomeCategories: OperationCategoryDialogCategory[];
    expenseCategories: OperationCategoryDialogCategory[];
    form?: Pick<FormBinding, 'action' | 'method'>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();
const categoryId = ref<number | ''>('');
const busy = ref(false);

const operationsReturnQuery = inject(OPERATIONS_RETURN_QUERY, undefined);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return SheetOperationCategoryController.update.form(
        props.operation ?? { id: 0 },
        operationsReturnQuery
            ? { query: operationsReturnQuery.value }
            : undefined,
    );
});

const isIncome = computed(() => props.operation?.type === 'income');

const availableCategories = computed(() => {
    const operation = props.operation;

    if (operation === null) {
        return [];
    }

    const year = Number(operation.operation_date.slice(0, 4));
    const options = (
        isIncome.value ? props.incomeCategories : props.expenseCategories
    ).filter((category) => category.budget_year === year);

    return withSelectedCategory(
        options,
        categoryId.value === operation.category_id
            ? {
                  id: operation.category_id,
                  name: operation.category_label,
                  icon: operation.category_icon,
                  color: operation.category_color,
                  archived: operation.category_archived,
              }
            : null,
        t.operations.category_archived_suffix,
    );
});

const unchanged = computed(
    () =>
        categoryId.value === '' ||
        categoryId.value === props.operation?.category_id,
);

watch(
    () => [props.open, props.operation] as const,
    ([open, operation]) => {
        if (open && operation) {
            categoryId.value = operation.category_id;
        }
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
                v-if="operation"
                v-bind="formBinding"
                class="grid gap-4"
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ t.operations.change_category }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t.operations.change_category_description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="form-field-row">
                    <FormField
                        id="operation-category-amount"
                        :label="t.operations.amount"
                        group
                    >
                        <p class="form-readonly-text tabular-nums">
                            {{ formatMoney(operation.amount_minor_units) }}
                        </p>
                    </FormField>

                    <FormField
                        id="operation-category-date"
                        :label="t.operations.date"
                        group
                    >
                        <p class="form-readonly-text">
                            {{ formatDate(operation.operation_date) }}
                        </p>
                    </FormField>
                </div>

                <FormField
                    id="operation-category-description"
                    :label="t.operations.description"
                    group
                >
                    <p class="form-readonly-text">
                        {{ operation.description }} ·
                        {{ operation.account_name }}
                    </p>
                </FormField>

                <FormField
                    id="operation-category-category"
                    :label="
                        isIncome
                            ? t.operations.income_category
                            : t.operations.expense_category
                    "
                    :error="errors.category_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <CategorySelect
                        :id="id"
                        name="category_id"
                        v-model="categoryId"
                        :categories="availableCategories"
                        :placeholder="t.operations.select_category"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
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
                                    closeDialog();
                                }
                            "
                        >
                            {{ t.operations.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing || unchanged">
                        <Spinner v-if="processing" />
                        {{ t.operations.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
