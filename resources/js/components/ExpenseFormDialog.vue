<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
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
    monthHasPlan,
    previewToSpendReadings,
} from '@/lib/entry-to-spend-warning';
import type { EntryToSpendInputs } from '@/lib/entry-to-spend-warning';
import { previewHandEntryMonthRemainingWarnings } from '@/lib/hand-entry-month-remaining-preview';
import type { HandEntryMonthRemainingInputs } from '@/lib/hand-entry-month-remaining-preview';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import {
    buildOperationPreview,
    monthNamesLocativeFrom,
} from '@/lib/operation-dialog-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

type FormBinding = InstanceType<typeof Form>['$props'];

export type ExpenseFormAccount = AccountPickerOption & {
    opening_date: string;
    current_balance_minor_units: number;
};

export type ExpenseFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
    planned_amount_minor_units: number;
    execution_minor_units: number;
};

export type ExpenseFormIncomeCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
};

export type ExpenseFormOperation = {
    id: number;
    operation_date: string;
    amount_minor_units: number;
    description: string;
    counterparty?: string | null;
    raw_statement_description?: string | null;
    account_id: number;
    category_id: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    operation?: ExpenseFormOperation | null;
    accounts: ExpenseFormAccount[];
    expenseCategories: ExpenseFormCategory[];
    incomeCategories?: ExpenseFormIncomeCategory[];
    handEntryMonthRemaining: HandEntryMonthRemainingInputs;
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

type EntryType = 'expense' | 'refund' | 'income';

const operationDate = ref('');
const description = ref('');
const counterparty = ref('');
const accountId = ref<number | ''>('');
const categoryId = ref<number | ''>('');
const amountInput = ref('0,00');
const type = ref<EntryType>('expense');
const originalAmountMinorUnits = ref(0);
const originalAccountId = ref<number | null>(null);
const originalCategoryId = ref<number | null>(null);
const busy = ref(false);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? ExpenseController.store.form()
        : ExpenseController.update.form(props.operation ?? { id: 0 });
});

const editingRefund = computed(
    () =>
        props.mode === 'edit' &&
        props.operation !== null &&
        props.operation !== undefined &&
        props.operation.amount_minor_units < 0,
);

const typeOptions = computed(() => {
    const options: { value: EntryType; label: string }[] = [
        { value: 'expense', label: t.operations.type_expense },
        { value: 'refund', label: t.operations.type_refund },
    ];

    if (editingRefund.value) {
        options.push({ value: 'income', label: t.operations.type_income });
    }

    return options;
});

const { amountInvalid, amountMinorUnits, showPreview } =
    useOperationAmountInput(amountInput);

const availableCategories = computed(() => {
    if (!operationDate.value) {
        return [];
    }

    const year = Number(operationDate.value.slice(0, 4));

    if (type.value === 'income') {
        return (props.incomeCategories ?? []).filter(
            (category) => category.budget_year === year,
        );
    }

    return props.expenseCategories.filter(
        (category) => category.budget_year === year,
    );
});

const selectedCategory = computed(() =>
    availableCategories.value.find(
        (category) => category.id === categoryId.value,
    ),
);

const signedAmountMinorUnits = computed(() =>
    type.value === 'refund' ? -amountMinorUnits.value : amountMinorUnits.value,
);

const warningPreview = computed(() => {
    if (accountId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            categories: props.expenseCategories,
            proposal: {
                kind: type.value,
                account_id: 0,
                category_id: 0,
                amount_minor_units: 0,
            },
        });
    }

    const editedRecord =
        props.mode === 'edit' &&
        props.operation !== null &&
        props.operation !== undefined &&
        originalAccountId.value !== null &&
        originalCategoryId.value !== null
            ? {
                  account_id: originalAccountId.value,
                  category_id: originalCategoryId.value,
                  signed_amount_minor_units: originalAmountMinorUnits.value,
              }
            : null;

    return previewHandEntryWarnings({
        mode: props.mode,
        accounts: props.accounts,
        categories: props.expenseCategories,
        editedRecord,
        proposal: {
            kind: type.value,
            account_id: accountId.value,
            category_id:
                type.value === 'income' || categoryId.value === ''
                    ? undefined
                    : categoryId.value,
            amount_minor_units: amountMinorUnits.value,
        },
    });
});

const monthRemainingEditedRecord = computed(() => {
    if (
        props.mode !== 'edit' ||
        props.operation === null ||
        props.operation === undefined ||
        originalCategoryId.value === null
    ) {
        return null;
    }

    return {
        category_id: originalCategoryId.value,
        signed_amount_minor_units: originalAmountMinorUnits.value,
        operation_month: Number(props.operation.operation_date.slice(5, 7)),
    };
});

const monthRemainingWarningPreview = computed(() =>
    previewHandEntryMonthRemainingWarnings({
        mode: props.mode,
        operationDate: operationDate.value,
        handEntryMonthRemaining: props.handEntryMonthRemaining,
        editedRecord: monthRemainingEditedRecord.value,
        proposal: {
            kind: type.value,
            category_id:
                type.value === 'income' || categoryId.value === ''
                    ? undefined
                    : categoryId.value,
        },
        signedAmountMinorUnits: signedAmountMinorUnits.value,
    }),
);

const operationMonthHasPlan = computed(() =>
    monthHasPlan(props.entryToSpend, operationDate.value),
);

const operationMonthYearLabel = computed(() => {
    if (operationDate.value === '') {
        return '';
    }

    const month = Number(operationDate.value.slice(5, 7));
    const year = Number(operationDate.value.slice(0, 4));
    const monthName = t.budget[`month_name_${month}`] ?? '';

    return `${monthName} ${year}`;
});

const monthRemainingWarnings = computed(() => {
    if (operationMonthHasPlan.value) {
        return [];
    }

    const warnings: string[] = [];

    if (monthRemainingWarningPreview.value.showOverMonthCategoryWarning) {
        warnings.push(
            t.operations.over_month_category_warning.replace(
                ':month_year',
                operationMonthYearLabel.value,
            ),
        );
    }

    if (monthRemainingWarningPreview.value.showOverMonthPoolWarning) {
        warnings.push(
            t.operations.over_month_pool_warning.replace(
                ':month_year',
                operationMonthYearLabel.value,
            ),
        );
    }

    return warnings;
});

const toSpendReadings = computed(() =>
    previewToSpendReadings(
        props.entryToSpend,
        handEntryToSpendChanges({
            kind: type.value,
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

const preview = computed(() => {
    const result = warningPreview.value;
    const showLimit =
        type.value !== 'income' && selectedCategory.value !== undefined;

    return buildOperationPreview({
        toSpendReadings: toSpendReadings.value,
        limit: showLimit
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
        monthRemainingWarnings: monthRemainingWarnings.value,
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
            type.value =
                props.operation.amount_minor_units < 0 ? 'refund' : 'expense';
            amountInput.value =
                props.initialAmountInput ??
                minorUnitsToMoneyInput(
                    Math.abs(props.operation.amount_minor_units),
                );
            originalAmountMinorUnits.value = props.operation.amount_minor_units;
            originalAccountId.value = props.operation.account_id;
            originalCategoryId.value = props.operation.category_id;

            return;
        }

        operationDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        counterparty.value = '';
        accountId.value = props.accounts[0]?.id ?? '';
        categoryId.value = '';
        amountInput.value = props.initialAmountInput ?? '0,00';
        type.value = 'expense';
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
    if ((next === 'income') !== (type.value === 'income')) {
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
                                ? type === 'refund'
                                    ? t.operations.add_refund_title
                                    : t.operations.add_expense_title
                                : type === 'income'
                                  ? t.operations.edit_income_title
                                  : type === 'refund'
                                    ? t.operations.edit_refund_title
                                    : t.operations.edit_expense_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.operations.add_expense_description
                                : t.operations.edit_expense_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="expense-type"
                    :label="t.operations.type"
                    group
                    :error="errors.convert_to_income"
                    v-slot="{ labelId }"
                >
                    <SegmentedControl
                        :model-value="type"
                        :options="typeOptions"
                        :aria-labelledby="labelId"
                        block
                        @update:model-value="setType"
                    />
                    <input
                        v-if="mode === 'edit' && type === 'income'"
                        type="hidden"
                        name="convert_to_income"
                        value="1"
                    />
                </FormField>

                <div class="form-field-row">
                    <FormField
                        id="expense-amount"
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
                            :value="signedAmountMinorUnits"
                        />
                    </FormField>

                    <FormField
                        id="expense-operation-date"
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
                    id="expense-category"
                    :label="
                        type === 'income'
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
                        :placeholder="
                            availableCategories.length === 0
                                ? type === 'income'
                                    ? t.operations.no_income_category_for_year
                                    : t.operations.no_expense_category_for_year
                                : t.operations.select_category
                        "
                        required
                        :disabled="availableCategories.length === 0"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="expense-account"
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
                    id="expense-description"
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
                    id="expense-counterparty"
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
                    id="expense-raw-statement-description"
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
                            variant="outline"
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
