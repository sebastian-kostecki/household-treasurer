<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import AccountSelect from '@/components/AccountSelect.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import FormField from '@/components/FormField.vue';
import FormPreview from '@/components/FormPreview.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
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
import { useOperationAmountInput } from '@/composables/useOperationAmountInput';
import { minorUnitsToMoneyInput } from '@/lib/format';
import {
    handEntryToSpendChanges,
    previewToSpendReadings,
} from '@/lib/entry-to-spend-warning';
import type { EntryToSpendInputs } from '@/lib/entry-to-spend-warning';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import { withSelectedCategory } from '@/lib/operations-category-options';
import type { SelectedOperationCategory } from '@/lib/operations-category-options';
import {
    buildOperationPreview,
    monthNamesLocativeFrom,
} from '@/lib/operation-dialog-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

type FormBinding = InstanceType<typeof Form>['$props'];

export type IncomeFormAccount = AccountPickerOption & {
    opening_date: string;
    current_balance_minor_units: number;
};

export type IncomeFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
};

export type IncomeFormExpenseCategory = IncomeFormCategory & {
    planned_amount_minor_units: number;
    execution_minor_units: number;
};

export type IncomeFormOperation = {
    id: number;
    operation_date: string;
    amount_minor_units: number;
    description: string;
    counterparty?: string | null;
    raw_statement_description?: string | null;
    account_id: number;
    category_id: number;
    category_label?: string;
    category_icon?: string;
    category_color?: string;
    category_archived?: boolean;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    operation?: IncomeFormOperation | null;
    accounts: IncomeFormAccount[];
    incomeCategories: IncomeFormCategory[];
    expenseCategories?: IncomeFormExpenseCategory[];
    entryToSpend: EntryToSpendInputs;
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Pre-fills the amount input; used only by the dialogs kitchen sink. */
    initialAmountInput?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

type EntryType = 'income' | 'refund';

const operationDate = ref('');
const description = ref('');
const counterparty = ref('');
const accountId = ref<number | ''>('');
const categoryId = ref<number | ''>('');
const amountInput = ref('0,00');
const type = ref<EntryType>('income');
const originalAmountMinorUnits = ref(0);
const originalAccountId = ref<number | null>(null);
const originalCategoryId = ref<number | null>(null);
const busy = ref(false);

const isExpenseRefund = computed(() => type.value === 'refund');

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? IncomeController.store.form()
        : IncomeController.update.form(props.operation ?? { id: 0 });
});

const typeOptions = computed(() => [
    { value: 'income' as const, label: t.operations.type_income },
    { value: 'refund' as const, label: t.operations.type_refund },
]);

const { amountInvalid, amountMinorUnits, showPreview } =
    useOperationAmountInput(amountInput);

const operationYear = computed(() =>
    operationDate.value === ''
        ? undefined
        : Number(operationDate.value.slice(0, 4)),
);

const warningPreview = computed(() => {
    const proposalKind =
        props.mode === 'edit' && isExpenseRefund.value ? 'refund' : 'income';

    if (accountId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            categories: props.expenseCategories,
            proposal: {
                kind: proposalKind,
                account_id: 0,
                category_id: 0,
                operation_year: operationYear.value,
                amount_minor_units: 0,
            },
        });
    }

    return previewHandEntryWarnings({
        mode: props.mode,
        accounts: props.accounts,
        categories: props.expenseCategories,
        editedRecord:
            props.mode === 'edit' &&
            originalAccountId.value !== null &&
            originalCategoryId.value !== null
                ? {
                      account_id: originalAccountId.value,
                      category_id: originalCategoryId.value,
                      signed_amount_minor_units: isExpenseRefund.value
                          ? -Math.abs(originalAmountMinorUnits.value)
                          : originalAmountMinorUnits.value,
                  }
                : null,
        proposal: {
            kind: proposalKind,
            account_id: accountId.value,
            category_id: categoryId.value === '' ? undefined : categoryId.value,
            operation_year: operationYear.value,
            amount_minor_units: amountMinorUnits.value,
        },
    });
});

const toSpendReadings = computed(() =>
    previewToSpendReadings(
        props.entryToSpend,
        handEntryToSpendChanges({
            kind: isExpenseRefund.value ? 'refund' : 'income',
            operationDate: operationDate.value,
            categoryId: categoryId.value === '' ? null : categoryId.value,
            amountMinorUnits: amountMinorUnits.value,
            original:
                props.mode === 'edit' &&
                props.operation !== null &&
                props.operation !== undefined &&
                originalCategoryId.value !== null
                    ? {
                          operationDate: props.operation.operation_date,
                          categoryId: originalCategoryId.value,
                          amountMinorUnits: originalAmountMinorUnits.value,
                      }
                    : null,
        }),
    ),
);

const yearCategories = computed(() => {
    if (!operationDate.value) {
        return [];
    }

    const year = Number(operationDate.value.slice(0, 4));

    if (props.mode === 'edit' && isExpenseRefund.value) {
        return (props.expenseCategories ?? []).filter(
            (category) => category.budget_year === year,
        );
    }

    return props.incomeCategories.filter(
        (category) => category.budget_year === year,
    );
});

const selectedOperationCategory = computed<SelectedOperationCategory | null>(
    () => {
        const operation = props.operation;

        if (
            props.mode !== 'edit' ||
            operation === null ||
            operation === undefined ||
            operation.category_label === undefined ||
            operation.category_icon === undefined ||
            operation.category_color === undefined ||
            categoryId.value !== operation.category_id
        ) {
            return null;
        }

        return {
            id: operation.category_id,
            name: operation.category_label,
            icon: operation.category_icon,
            color: operation.category_color,
            archived: operation.category_archived === true,
        };
    },
);

const availableCategories = computed(() =>
    withSelectedCategory(
        yearCategories.value,
        selectedOperationCategory.value,
        t.operations.category_archived_suffix,
    ),
);

const selectedExpenseCategory = computed(() => {
    if (!isExpenseRefund.value) {
        return undefined;
    }

    return (props.expenseCategories ?? []).find(
        (category) => category.id === categoryId.value,
    );
});

const preview = computed(() => {
    const result = warningPreview.value;

    return buildOperationPreview({
        toSpendReadings: toSpendReadings.value,
        limit:
            selectedExpenseCategory.value !== undefined
                ? {
                      label: t.operations.preview_category_limit,
                      beforeMinorUnits: result.availableLimitBeforeMinorUnits,
                      afterMinorUnits: result.availableLimitAfterMinorUnits,
                      hint: t.operations.available_limit_hint,
                      warning: result.showOverLimitWarning
                          ? t.operations.over_limit_warning
                          : null,
                  }
                : null,
        accountBalance: {
            label: t.operations.preview_account_balance,
            beforeMinorUnits: result.accountBalanceBeforeMinorUnits,
            afterMinorUnits: result.accountBalanceAfterMinorUnits,
            warning: result.showNegativeBalanceWarning
                ? t.operations.negative_balance_warning
                : null,
        },
        texts: {
            toSpendLabel: t.operations.preview_to_spend,
            toSpendWarning: t.operations.to_spend_negative_preview_warning,
            monthNamesLocative: monthNamesLocativeFrom(t.budget),
        },
    });
});

watch(
    () => [props.open, props.mode, props.operation] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.operation) {
            operationDate.value = props.operation.operation_date;
            description.value = props.operation.description;
            counterparty.value = props.operation.counterparty ?? '';
            accountId.value = props.operation.account_id;
            categoryId.value = props.operation.category_id;
            amountInput.value =
                props.initialAmountInput ??
                minorUnitsToMoneyInput(props.operation.amount_minor_units);
            originalAmountMinorUnits.value = props.operation.amount_minor_units;
            originalAccountId.value = props.operation.account_id;
            originalCategoryId.value = props.operation.category_id;
            type.value = 'income';

            return;
        }

        operationDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        counterparty.value = '';
        accountId.value = props.accounts[0]?.id ?? '';
        categoryId.value = '';
        amountInput.value = props.initialAmountInput ?? '0,00';
        type.value = 'income';
        originalAmountMinorUnits.value = 0;
        originalAccountId.value = null;
        originalCategoryId.value = null;
    },
    { immediate: true },
);

watch(availableCategories, (categories) => {
    if (
        categoryId.value !== '' &&
        !categories.some((category) => category.id === categoryId.value)
    ) {
        categoryId.value =
            props.mode === 'edit' ? '' : (categories[0]?.id ?? '');
    }
});

function setType(next: EntryType): void {
    if (props.mode === 'edit' && next !== type.value) {
        categoryId.value = '';
    }

    type.value = next;
}

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
                                ? t.operations.add_income_title
                                : isExpenseRefund
                                  ? t.operations.edit_refund_title
                                  : t.operations.edit_income_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.operations.add_income_description
                                : t.operations.edit_income_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    v-if="mode === 'edit'"
                    id="income-type"
                    :label="t.operations.type"
                    group
                    :error="errors.convert_to_expense_refund"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <SegmentedControl
                        :model-value="type"
                        :options="typeOptions"
                        :ariaLabelledby="labelId"
                        :ariaDescribedby="describedBy"
                        :ariaInvalid="invalid"
                        block
                        @update:model-value="setType"
                    />
                    <input
                        v-if="isExpenseRefund"
                        type="hidden"
                        name="convert_to_expense_refund"
                        value="1"
                    />
                </FormField>

                <div class="form-field-row">
                    <FormField
                        id="income-amount"
                        :label="t.operations.amount"
                        money
                        :error="
                            amountInvalid
                                ? t.operations.amount_invalid
                                : errors.amount_minor_units
                        "
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            v-model="amountInput"
                            :class="controlClass"
                            inputmode="decimal"
                            autocomplete="off"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                        <input
                            type="hidden"
                            name="amount_minor_units"
                            :value="amountMinorUnits"
                        />
                    </FormField>

                    <FormField
                        id="income-operation-date"
                        :label="t.operations.date"
                        :error="errors.operation_date"
                        v-slot="{ id, describedBy, invalid }"
                    >
                        <Input
                            :id="id"
                            name="operation_date"
                            v-model="operationDate"
                            type="date"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>

                <FormField
                    id="income-category"
                    :label="
                        mode === 'edit' && isExpenseRefund
                            ? t.operations.expense_category
                            : t.operations.income_category
                    "
                    :error="errors.category_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <CategorySelect
                        :id="id"
                        name="category_id"
                        v-model="categoryId"
                        :categories="availableCategories"
                        :placeholder="
                            availableCategories.length === 0
                                ? mode === 'edit' && isExpenseRefund
                                    ? t.operations.no_expense_category_for_year
                                    : t.operations.no_income_category_for_year
                                : t.operations.select_category
                        "
                        required
                        :disabled="availableCategories.length === 0"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="income-account"
                    :label="t.operations.account"
                    :error="errors.account_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <AccountSelect
                        :id="id"
                        name="account_id"
                        v-model="accountId"
                        :accounts="accounts"
                        :placeholder="t.operations.select_account"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="income-description"
                    :label="t.operations.description"
                    :error="errors.description"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="description"
                        v-model="description"
                        required
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="income-counterparty"
                    :label="t.operations.counterparty"
                    :error="errors.counterparty"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="counterparty"
                        v-model="counterparty"
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    v-if="
                        mode === 'edit' && operation?.raw_statement_description
                    "
                    id="income-raw-statement-description"
                    :label="t.operations.raw_statement_description"
                    group
                >
                    <p class="form-readonly-text">
                        {{ operation.raw_statement_description }}
                    </p>
                </FormField>

                <FormPreview
                    v-if="showPreview"
                    :rows="preview.rows"
                    :warnings="preview.warnings"
                />

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
                            {{ t.operations.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || amountInvalid"
                    >
                        <Spinner v-if="processing" />
                        {{ t.operations.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
