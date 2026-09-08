<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
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
    groszeToMoneyInput,
    parseMoneyInputToGrosze,
} from '@/lib/format';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

export type ExpenseFormAccount = AccountPickerOption & {
    opening_date: string;
    current_balance_grosze: number;
};

export type ExpenseFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
    planned_amount_grosze: number;
    execution_grosze: number;
};

export type ExpenseFormOperation = {
    id: number;
    operation_date: string;
    amount_grosze: number;
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
const amountGrosze = ref(0);
const isRefund = ref(false);
const originalAmountGrosze = ref(0);
const originalAccountId = ref<number | null>(null);
const originalCategoryId = ref<number | null>(null);

const formBinding = computed(() =>
    props.mode === 'create'
        ? ExpenseController.store.form()
        : ExpenseController.update.form(props.operation ?? { id: 0 }),
);

const availableCategories = computed(() => {
    if (!operationDate.value) {
        return [];
    }

    const year = Number(operationDate.value.slice(0, 4));

    return props.expenseCategories.filter(
        (category) => category.budget_year === year,
    );
});

const selectedCategory = computed(() =>
    availableCategories.value.find(
        (category) => category.id === categoryId.value,
    ),
);

const signedAmountGrosze = computed(() => {
    const absoluteAmount = Math.abs(amountGrosze.value);

    return isRefund.value ? -absoluteAmount : absoluteAmount;
});

const warningPreview = computed(() => {
    if (accountId.value === '' || categoryId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            categories: props.expenseCategories,
            proposal: {
                kind: isRefund.value ? 'refund' : 'expense',
                account_id: 0,
                category_id: 0,
                amount_grosze: 0,
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
                      signed_amount_grosze: originalAmountGrosze.value,
                  }
                : null,
        proposal: {
            kind: isRefund.value ? 'refund' : 'expense',
            account_id: accountId.value,
            category_id: categoryId.value,
            amount_grosze: amountGrosze.value,
        },
    });
});

const availableLimitGrosze = computed(
    () => warningPreview.value.availableLimitNowGrosze,
);

const predictedLimitGrosze = computed(
    () => warningPreview.value.availableLimitAfterGrosze,
);

const showOverLimitWarning = computed(
    () => warningPreview.value.showOverLimitWarning,
);

const showNegativeBalanceWarning = computed(
    () => warningPreview.value.showNegativeBalanceWarning,
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
            isRefund.value = props.operation.amount_grosze < 0;
            amountGrosze.value = Math.abs(props.operation.amount_grosze);
            amountInput.value = groszeToMoneyInput(
                Math.abs(props.operation.amount_grosze),
            );
            originalAmountGrosze.value = props.operation.amount_grosze;
            originalAccountId.value = props.operation.account_id;
            originalCategoryId.value = props.operation.category_id;

            return;
        }

        operationDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        counterparty.value = '';
        accountId.value = props.accounts[0]?.id ?? '';
        categoryId.value = '';
        amountGrosze.value = 0;
        amountInput.value = '0,00';
        isRefund.value = false;
        originalAmountGrosze.value = 0;
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

function syncAmount(): void {
    const parsed = parseMoneyInputToGrosze(amountInput.value);

    if (parsed !== null) {
        amountGrosze.value = Math.abs(parsed);
    } else {
        amountGrosze.value = 0;
    }
}

function prepareSubmit(): void {
    syncAmount();
    amountGrosze.value = signedAmountGrosze.value;
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
                @before="prepareSubmit"
                v-slot="{ errors, processing, reset, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            mode === 'create'
                                ? isRefund
                                    ? t.operations.add_refund_title
                                    : t.operations.add_expense_title
                                : isRefund
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

                <div class="grid gap-2">
                    <Label for="expense-operation-date">
                        {{ t.operations.date }}
                    </Label>
                    <Input
                        id="expense-operation-date"
                        name="operation_date"
                        v-model="operationDate"
                        type="date"
                        required
                    />
                    <InputError :message="errors.operation_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="expense-description">
                        {{ t.operations.description }}
                    </Label>
                    <Input
                        id="expense-description"
                        name="description"
                        v-model="description"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="expense-counterparty">
                        {{ t.operations.counterparty }}
                    </Label>
                    <Input
                        id="expense-counterparty"
                        name="counterparty"
                        v-model="counterparty"
                        autocomplete="off"
                    />
                    <InputError :message="errors.counterparty" />
                </div>

                <div
                    v-if="mode === 'edit' && operation?.raw_statement_description"
                    class="grid gap-2"
                >
                    <Label for="expense-raw-statement-description">
                        {{ t.operations.raw_statement_description }}
                    </Label>
                    <textarea
                        id="expense-raw-statement-description"
                        :value="operation.raw_statement_description"
                        readonly
                        rows="3"
                        class="border-input w-full rounded-xl border bg-transparent px-3 py-2 text-base md:text-sm"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="expense-account">{{ t.operations.account }}</Label>
                    <AccountSelect
                        id="expense-account"
                        name="account_id"
                        v-model="accountId"
                        :accounts="accounts"
                        :placeholder="t.operations.select_account"
                        required
                    />
                    <InputError :message="errors.account_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="expense-category">
                        {{ t.operations.expense_category }}
                    </Label>
                    <CategorySelect
                        id="expense-category"
                        name="category_id"
                        v-model="categoryId"
                        :categories="availableCategories"
                        :placeholder="
                            availableCategories.length === 0
                                ? t.operations.no_expense_category_for_year
                                : t.operations.select_category
                        "
                        required
                        :disabled="availableCategories.length === 0"
                    />
                    <InputError :message="errors.category_id" />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="expense-is-refund"
                        v-model="isRefund"
                        type="checkbox"
                        class="size-4 rounded border"
                    />
                    <Label for="expense-is-refund">
                        {{ t.operations.refund_label }}
                    </Label>
                </div>

                <div class="grid gap-2">
                    <Label for="expense-amount">{{ t.operations.amount }}</Label>
                    <Input
                        id="expense-amount"
                        v-model="amountInput"
                        inputmode="decimal"
                        autocomplete="off"
                        @input="syncAmount"
                        @blur="syncAmount"
                    />
                    <input
                        type="hidden"
                        name="amount_grosze"
                        :value="signedAmountGrosze"
                    />
                    <InputError :message="errors.amount_grosze" />
                </div>

                <div
                    v-if="selectedCategory && availableLimitGrosze !== null"
                    class="rounded-md border bg-muted/40 p-3 text-sm"
                >
                    <p>
                        {{ t.operations.available_limit_now }}:
                        <span class="font-medium">
                            {{ formatMoney(availableLimitGrosze) }}
                        </span>
                    </p>
                    <p v-if="predictedLimitGrosze !== null">
                        {{ t.operations.available_limit_after }}:
                        <span class="font-medium">
                            {{ formatMoney(predictedLimitGrosze) }}
                        </span>
                    </p>
                    <p class="text-muted-foreground mt-2 text-xs">
                        {{ t.operations.available_limit_hint }}
                    </p>
                </div>

                <Alert
                    v-if="showOverLimitWarning || showNegativeBalanceWarning"
                    variant="destructive"
                >
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{ t.operations.expense_warnings_title }}</AlertTitle>
                    <AlertDescription>
                        <ul class="list-inside list-disc text-sm">
                            <li v-if="showOverLimitWarning">
                                {{ t.operations.over_limit_warning }}
                            </li>
                            <li v-if="showNegativeBalanceWarning">
                                {{ t.operations.negative_balance_warning }}
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
