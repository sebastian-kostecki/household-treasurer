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
    groszeToMoneyInput,
    parseMoneyInputToGrosze,
} from '@/lib/format';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

export type IncomeFormAccount = AccountPickerOption & {
    opening_date: string;
    current_balance_grosze: number;
};

export type IncomeFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
};

export type IncomeFormOperation = {
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
    operation?: IncomeFormOperation | null;
    accounts: IncomeFormAccount[];
    incomeCategories: IncomeFormCategory[];
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
const originalAmountGrosze = ref(0);
const originalAccountId = ref<number | null>(null);

const formBinding = computed(() =>
    props.mode === 'create'
        ? IncomeController.store.form()
        : IncomeController.update.form(props.operation ?? { id: 0 }),
);

const warningPreview = computed(() => {
    if (accountId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            proposal: {
                kind: 'income',
                account_id: 0,
                amount_grosze: 0,
            },
        });
    }

    return previewHandEntryWarnings({
        mode: props.mode,
        accounts: props.accounts,
        editedRecord:
            props.mode === 'edit' && originalAccountId.value !== null
                ? {
                      account_id: originalAccountId.value,
                      signed_amount_grosze: originalAmountGrosze.value,
                  }
                : null,
        proposal: {
            kind: 'income',
            account_id: accountId.value,
            amount_grosze: amountGrosze.value,
        },
    });
});

const showNegativeBalanceWarning = computed(
    () => warningPreview.value.showNegativeBalanceWarning,
);

const availableCategories = computed(() => {
    if (!operationDate.value) {
        return [];
    }

    const year = Number(operationDate.value.slice(0, 4));

    return props.incomeCategories.filter(
        (category) => category.budget_year === year,
    );
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
            amountGrosze.value = props.operation.amount_grosze;
            amountInput.value = groszeToMoneyInput(props.operation.amount_grosze);
            originalAmountGrosze.value = props.operation.amount_grosze;
            originalAccountId.value = props.operation.account_id;

            return;
        }

        operationDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        counterparty.value = '';
        accountId.value = props.accounts[0]?.id ?? '';
        categoryId.value = '';
        amountGrosze.value = 0;
        amountInput.value = '0,00';
        originalAmountGrosze.value = 0;
        originalAccountId.value = null;
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
        amountGrosze.value = parsed;
    } else {
        amountGrosze.value = 0;
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
                    v-if="mode === 'edit' && operation?.raw_statement_description"
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
                    <Label for="income-account">{{ t.operations.account }}</Label>
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

                <div class="grid gap-2">
                    <Label for="income-category">
                        {{ t.operations.income_category }}
                    </Label>
                    <CategorySelect
                        id="income-category"
                        name="category_id"
                        v-model="categoryId"
                        :categories="availableCategories"
                        :placeholder="
                            availableCategories.length === 0
                                ? t.operations.no_income_category_for_year
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
                        name="amount_grosze"
                        :value="amountGrosze"
                    />
                    <InputError :message="errors.amount_grosze" />
                </div>

                <Alert v-if="showNegativeBalanceWarning" variant="destructive">
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{ t.operations.expense_warnings_title }}</AlertTitle>
                    <AlertDescription>
                        {{ t.operations.negative_balance_warning }}
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
