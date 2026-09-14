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
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';

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

type EditableExpectation = {
    id: number;
    type: 'income' | 'expense';
    expectation_date: string;
    amount_minor_units: number;
    description: string;
    counterparty: string | null;
    is_expense_refund: boolean;
    account_id: number;
    category_id: number;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        mode?: 'create' | 'edit';
        expectation?: EditableExpectation | null;
        accounts: CashExpectationFormAccount[];
        incomeCategories: CashExpectationFormCategory[];
        expenseCategories: CashExpectationFormCategory[];
        defaultDate?: string;
    }>(),
    {
        mode: 'create',
        expectation: null,
    },
);

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
const occurrence = ref<'once' | 'monthly'>('once');

const isEdit = computed(
    () => props.mode === 'edit' && props.expectation !== null,
);

const formBinding = computed(() =>
    isEdit.value && props.expectation
        ? CashExpectationController.update.form(props.expectation)
        : CashExpectationController.store.form(),
);

const persistedType = computed(() =>
    expectationKind.value === 'income' ? 'income' : 'expense',
);

const submittedAmountMinorUnits = computed(() => {
    if (expectationKind.value === 'expense_refund') {
        return -Math.abs(amountMinorUnits.value);
    }

    return amountMinorUnits.value;
});

function categoriesForDate(
    categories: CashExpectationFormCategory[],
    date: string,
): CashExpectationFormCategory[] {
    if (!date) {
        return [];
    }

    const year = Number(date.slice(0, 4));

    return categories.filter((category) => category.budget_year === year);
}

const availableCategories = computed(() =>
    categoriesForDate(
        expectationKind.value === 'income'
            ? props.incomeCategories
            : props.expenseCategories,
        expectationDate.value,
    ),
);

const categoryPlaceholder = computed(() =>
    availableCategories.value.length === 0
        ? t.agenda.no_category_for_year
        : t.agenda.select_category,
);

watch(
    () => [props.open, props.mode, props.expectation] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (isEdit.value && props.expectation) {
            expectationKind.value = props.expectation.is_expense_refund
                ? 'expense_refund'
                : props.expectation.type;
            expectationDate.value = props.expectation.expectation_date;
            description.value = props.expectation.description;
            counterparty.value = props.expectation.counterparty ?? '';
            accountId.value = props.expectation.account_id;
            categoryId.value = props.expectation.category_id;
            amountMinorUnits.value = Math.abs(
                props.expectation.amount_minor_units,
            );
            amountInput.value = minorUnitsToMoneyInput(
                Math.abs(props.expectation.amount_minor_units),
            );

            return;
        }

        expectationDate.value = props.defaultDate ?? '';
        const expenseForDate = categoriesForDate(
            props.expenseCategories,
            expectationDate.value,
        );
        const incomeForDate = categoriesForDate(
            props.incomeCategories,
            expectationDate.value,
        );
        expectationKind.value =
            expenseForDate.length === 0 && incomeForDate.length > 0
                ? 'income'
                : 'expense';
        description.value = '';
        counterparty.value = '';
        accountId.value = props.accounts[0]?.id ?? '';
        categoryId.value =
            (expectationKind.value === 'income'
                ? incomeForDate[0]?.id
                : expenseForDate[0]?.id) ?? '';
        amountInput.value = '0,00';
        amountMinorUnits.value = 0;
        occurrence.value = 'once';
    },
    { immediate: true },
);

watch(availableCategories, (categories) => {
    if (
        categoryId.value !== '' &&
        categories.some((category) => category.id === categoryId.value)
    ) {
        return;
    }

    if (isEdit.value) {
        categoryId.value = '';

        return;
    }

    categoryId.value = categories[0]?.id ?? '';
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
                <DialogTitle>
                    {{ isEdit ? t.agenda.edit_title : t.agenda.create_title }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? t.agenda.edit_description
                            : t.agenda.create_description
                    }}
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
                        <option value="income">
                            {{ t.agenda.type_income }}
                        </option>
                        <option value="expense">
                            {{ t.agenda.type_expense }}
                        </option>
                        <option value="expense_refund">
                            {{ t.agenda.type_expense_refund }}
                        </option>
                    </select>
                </div>

                <div v-if="!isEdit" class="space-y-2">
                    <Label for="expectation-occurrence">
                        {{ t.agenda.occurrence_label }}
                    </Label>
                    <select
                        id="expectation-occurrence"
                        v-model="occurrence"
                        class="native-select w-full"
                        name="occurrence"
                    >
                        <option value="once">
                            {{ t.agenda.occurrence_once }}
                        </option>
                        <option value="monthly">
                            {{ t.agenda.occurrence_monthly }}
                        </option>
                    </select>
                    <InputError name="occurrence" />
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
                    <Label for="expectation-amount">{{
                        t.agenda.amount
                    }}</Label>
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
                    <Label for="expectation-account">{{
                        t.agenda.account
                    }}</Label>
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
                        :placeholder="categoryPlaceholder"
                        required
                        :disabled="availableCategories.length === 0"
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
