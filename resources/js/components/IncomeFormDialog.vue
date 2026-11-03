<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import AccountSelect from '@/components/AccountSelect.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatMoney,
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import {
    handEntryToSpendChanges,
    previewToSpendWarnings,
} from '@/lib/entry-to-spend-warning';
import type { EntryToSpendInputs } from '@/lib/entry-to-spend-warning';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

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
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    operation?: IncomeFormOperation | null;
    accounts: IncomeFormAccount[];
    incomeCategories: IncomeFormCategory[];
    expenseCategories?: IncomeFormExpenseCategory[];
    entryToSpend: EntryToSpendInputs;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const operationDate = ref('');
const description = ref('');
const counterparty = ref('');
const accountId = ref<number | ''>('');
const categoryId = ref<number | ''>('');
const amountInput = ref('0,00');
const amountMinorUnits = ref(0);
const isExpenseRefund = ref(false);
const originalAmountMinorUnits = ref(0);
const originalAccountId = ref<number | null>(null);
const originalCategoryId = ref<number | null>(null);

const formBinding = computed(() =>
    props.mode === 'create'
        ? IncomeController.store.form()
        : IncomeController.update.form(props.operation ?? { id: 0 }),
);

const warningPreview = computed(() => {
    const proposalKind =
        props.mode === 'edit' && isExpenseRefund.value ? 'refund' : 'income';

    if (accountId.value === '' || categoryId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            categories: props.expenseCategories,
            proposal: {
                kind: proposalKind,
                account_id: 0,
                category_id: 0,
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
            category_id: categoryId.value,
            amount_minor_units: amountMinorUnits.value,
        },
    });
});

const showNegativeBalanceWarning = computed(
    () => warningPreview.value.showNegativeBalanceWarning,
);

const toSpendWarnings = computed(() =>
    previewToSpendWarnings(
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

function monthYearLabel(year: number, month: number): string {
    const monthName =
        t.budget[`month_name_${month}` as keyof typeof t.budget] ?? '';

    return `${monthName} ${year}`;
}

const availableCategories = computed(() => {
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

const selectedExpenseCategory = computed(() => {
    if (!isExpenseRefund.value) {
        return undefined;
    }

    return (props.expenseCategories ?? []).find(
        (category) => category.id === categoryId.value,
    );
});

const availableLimitMinorUnits = computed(
    () => warningPreview.value.availableLimitNowMinorUnits,
);

const predictedLimitMinorUnits = computed(
    () => warningPreview.value.availableLimitAfterMinorUnits,
);

const showOverLimitWarning = computed(
    () => warningPreview.value.showOverLimitWarning,
);

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
            amountMinorUnits.value = props.operation.amount_minor_units;
            amountInput.value = minorUnitsToMoneyInput(
                props.operation.amount_minor_units,
            );
            originalAmountMinorUnits.value = props.operation.amount_minor_units;
            originalAccountId.value = props.operation.account_id;
            originalCategoryId.value = props.operation.category_id;
            isExpenseRefund.value = false;

            return;
        }

        operationDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        counterparty.value = '';
        accountId.value = props.accounts[0]?.id ?? '';
        categoryId.value = '';
        amountMinorUnits.value = 0;
        amountInput.value = '0,00';
        isExpenseRefund.value = false;
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

watch(isExpenseRefund, () => {
    if (props.mode !== 'edit') {
        return;
    }

    categoryId.value = '';
});

function syncAmount(): void {
    const parsed = parseMoneyInputToMinorUnits(amountInput.value);

    if (parsed !== null) {
        amountMinorUnits.value = parsed;
    } else {
        amountMinorUnits.value = 0;
    }
}

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <Form
                v-bind="formBinding"
                class="space-y-4"
                reset-on-success
                @success="closeDialog"
                @before="syncAmount"
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

                <div class="grid gap-2">
                    <Label for="income-operation-date">
                        {{ t.operations.date }}
                    </Label>
                    <Input
                        id="income-operation-date"
                        name="operation_date"
                        v-model="operationDate"
                        type="date"
                        required
                    />
                    <InputError :message="errors.operation_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="income-description">
                        {{ t.operations.description }}
                    </Label>
                    <Input
                        id="income-description"
                        name="description"
                        v-model="description"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="income-counterparty">
                        {{ t.operations.counterparty }}
                    </Label>
                    <Input
                        id="income-counterparty"
                        name="counterparty"
                        v-model="counterparty"
                        autocomplete="off"
                    />
                    <InputError :message="errors.counterparty" />
                </div>

                <div
                    v-if="
                        mode === 'edit' && operation?.raw_statement_description
                    "
                    class="grid gap-2"
                >
                    <Label for="income-raw-statement-description">
                        {{ t.operations.raw_statement_description }}
                    </Label>
                    <textarea
                        id="income-raw-statement-description"
                        :value="operation.raw_statement_description"
                        readonly
                        rows="3"
                        class="border-input w-full rounded-xl border bg-transparent px-3 py-2 text-base md:text-sm"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="income-account">{{
                        t.operations.account
                    }}</Label>
                    <AccountSelect
                        id="income-account"
                        name="account_id"
                        v-model="accountId"
                        :accounts="accounts"
                        :placeholder="t.operations.select_account"
                        required
                    />
                    <InputError :message="errors.account_id" />
                </div>

                <div v-if="mode === 'edit'" class="flex items-center gap-2">
                    <input
                        id="income-is-expense-refund"
                        v-model="isExpenseRefund"
                        type="checkbox"
                        class="size-4 rounded border"
                    />
                    <Label for="income-is-expense-refund">
                        {{ t.operations.refund_label }}
                    </Label>
                </div>

                <input
                    v-if="mode === 'edit' && isExpenseRefund"
                    type="hidden"
                    name="convert_to_expense_refund"
                    value="1"
                />

                <div class="grid gap-2">
                    <Label for="income-category">
                        {{
                            mode === 'edit' && isExpenseRefund
                                ? t.operations.expense_category
                                : t.operations.income_category
                        }}
                    </Label>
                    <CategorySelect
                        id="income-category"
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
                    />
                    <InputError :message="errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="income-amount">{{ t.operations.amount }}</Label>
                    <Input
                        id="income-amount"
                        v-model="amountInput"
                        inputmode="decimal"
                        autocomplete="off"
                        @input="syncAmount"
                        @blur="syncAmount"
                    />
                    <input
                        type="hidden"
                        name="amount_minor_units"
                        :value="amountMinorUnits"
                    />
                    <InputError :message="errors.amount_minor_units" />
                </div>

                <div
                    v-if="
                        isExpenseRefund &&
                        selectedExpenseCategory &&
                        availableLimitMinorUnits !== null
                    "
                    class="bg-muted/40 rounded-md border p-3 text-sm"
                >
                    <p>
                        {{ t.operations.available_limit_now }}:
                        <span class="font-medium">
                            {{ formatMoney(availableLimitMinorUnits) }}
                        </span>
                    </p>
                    <p v-if="predictedLimitMinorUnits !== null">
                        {{ t.operations.available_limit_after }}:
                        <span class="font-medium">
                            {{ formatMoney(predictedLimitMinorUnits) }}
                        </span>
                    </p>
                    <p class="text-muted-foreground mt-2 text-xs">
                        {{ t.operations.available_limit_hint }}
                    </p>
                </div>

                <Alert
                    v-if="
                        showNegativeBalanceWarning ||
                        showOverLimitWarning ||
                        toSpendWarnings.length > 0
                    "
                    variant="warning"
                >
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{
                        t.operations.expense_warnings_title
                    }}</AlertTitle>
                    <AlertDescription>
                        <ul class="list-inside list-disc text-sm">
                            <li v-if="showOverLimitWarning">
                                {{ t.operations.over_limit_warning }}
                            </li>
                            <li v-if="showNegativeBalanceWarning">
                                {{ t.operations.negative_balance_warning }}
                            </li>
                            <li
                                v-for="warning in toSpendWarnings"
                                :key="`${warning.year}-${warning.month}`"
                            >
                                {{
                                    t.operations.to_spend_negative_warning
                                        .replace(
                                            ':amount',
                                            formatMoney(
                                                warning.toSpendAfterMinorUnits,
                                            ),
                                        )
                                        .replace(
                                            ':month_year',
                                            monthYearLabel(
                                                warning.year,
                                                warning.month,
                                            ),
                                        )
                                }}
                            </li>
                        </ul>
                    </AlertDescription>
                </Alert>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
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
                    <Button type="submit" :disabled="processing">
                        {{ t.operations.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
