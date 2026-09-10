<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CashExpectationController from '@/actions/App/Http/Controllers/CashExpectationController';
import AccountSelect from '@/components/AccountSelect.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import InputError from '@/components/InputError.vue';
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
import type { AccountPickerOption } from '@/lib/account-identity';
import { parseMoneyInputToMinorUnits } from '@/lib/format';

export type CashExpectationFormAccount = AccountPickerOption & {
    opening_date: string;
};

export type CashExpectationFormCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    budget_year: number;
};

type ExpectationKind = 'income' | 'expense' | 'expense_refund';

const props = defineProps<{
    open: boolean;
    accounts: CashExpectationFormAccount[];
    incomeCategories: CashExpectationFormCategory[];
    expenseCategories: CashExpectationFormCategory[];
    defaultDate?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const expectationKind = ref<ExpectationKind>('expense');
const expectationDate = ref('');
const description = ref('');
const counterparty = ref('');
const accountId = ref<number | ''>('');
const categoryId = ref<number | ''>('');
const amountInput = ref('0,00');
const amountMinorUnits = ref(0);

const formBinding = computed(() => CashExpectationController.store.form());

const persistedType = computed(() =>
    expectationKind.value === 'income' ? 'income' : 'expense',
);

const submittedAmountMinorUnits = computed(() => {
    if (expectationKind.value === 'expense_refund') {
        return -Math.abs(amountMinorUnits.value);
    }

    return amountMinorUnits.value;
});

const availableCategories = computed(() => {
    if (!expectationDate.value) {
        return [];
    }

    const year = Number(expectationDate.value.slice(0, 4));
    const categories =
        expectationKind.value === 'income'
            ? props.incomeCategories
            : props.expenseCategories;

    return categories.filter((category) => category.budget_year === year);
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        expectationKind.value = 'expense';
        expectationDate.value = props.defaultDate ?? '';
        description.value = '';
        counterparty.value = '';
        accountId.value = '';
        categoryId.value = '';
        amountInput.value = '0,00';
        amountMinorUnits.value = 0;
    },
);

watch(expectationKind, () => {
    categoryId.value = '';
});

watch(expectationDate, () => {
    categoryId.value = '';
});

watch(amountInput, (value) => {
    const parsed = parseMoneyInputToMinorUnits(value);

    if (parsed !== null) {
        amountMinorUnits.value = Math.abs(parsed);
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t.agenda.create_title }}</DialogTitle>
                <DialogDescription>
                    {{ t.agenda.create_description }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="formBinding"
                reset-on-success
                class="space-y-4"
                @success="emit('update:open', false)"
            >
                <input type="hidden" name="type" :value="persistedType" />
                <input
                    type="hidden"
                    name="amount_minor_units"
                    :value="submittedAmountMinorUnits"
                />

                <div class="space-y-2">
                    <Label for="expectation-kind">{{ t.agenda.type }}</Label>
                    <select
                        id="expectation-kind"
                        v-model="expectationKind"
                        class="native-select w-full"
                    >
                        <option value="income">{{ t.agenda.type_income }}</option>
                        <option value="expense">
                            {{ t.agenda.type_expense }}
                        </option>
                        <option value="expense_refund">
                            {{ t.agenda.type_expense_refund }}
                        </option>
                    </select>
                </div>

                <div class="space-y-2">
                    <Label for="expectation-date">{{ t.agenda.date }}</Label>
                    <Input
                        id="expectation-date"
                        v-model="expectationDate"
                        name="expectation_date"
                        type="date"
                        required
                    />
                    <InputError name="expectation_date" />
                </div>

                <div class="space-y-2">
                    <Label for="expectation-amount">{{ t.agenda.amount }}</Label>
                    <Input
                        id="expectation-amount"
                        v-model="amountInput"
                        inputmode="decimal"
                        required
                    />
                    <InputError name="amount_minor_units" />
                </div>

                <div class="space-y-2">
                    <Label for="expectation-description">
                        {{ t.agenda.description }}
                    </Label>
                    <Input
                        id="expectation-description"
                        v-model="description"
                        name="description"
                        required
                    />
                    <InputError name="description" />
                </div>

                <div class="space-y-2">
                    <Label for="expectation-counterparty">
                        {{ t.agenda.counterparty }}
                    </Label>
                    <Input
                        id="expectation-counterparty"
                        v-model="counterparty"
                        name="counterparty"
                    />
                    <InputError name="counterparty" />
                </div>

                <div class="space-y-2">
                    <Label for="expectation-account">{{ t.agenda.account }}</Label>
                    <AccountSelect
                        id="expectation-account"
                        v-model="accountId"
                        name="account_id"
                        :accounts="accounts"
                        required
                    />
                    <InputError name="account_id" />
                </div>

                <div class="space-y-2">
                    <Label for="expectation-category">
                        {{ t.agenda.category }}
                    </Label>
                    <CategorySelect
                        id="expectation-category"
                        v-model="categoryId"
                        name="category_id"
                        :categories="availableCategories"
                        required
                    />
                    <InputError name="category_id" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            {{ t.agenda.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit">{{ t.agenda.save }}</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
