<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import DialogsKitchenSinkController from '@/actions/App/Http/Controllers/Dev/DialogsKitchenSinkController';
import AccountFormDialog from '@/components/AccountFormDialog.vue';
import CategoryFormDialog from '@/components/CategoryFormDialog.vue';
import type { CategoryFormCategory } from '@/components/CategoryFormDialog.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import CapitalMovementFormDialog from '@/components/CapitalMovementFormDialog.vue';
import ExpenseFormDialog from '@/components/ExpenseFormDialog.vue';
import IncomeFormDialog from '@/components/IncomeFormDialog.vue';
import OwnTransferFormDialog from '@/components/OwnTransferFormDialog.vue';
import TermDepositFormDialog from '@/components/TermDepositFormDialog.vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { AccountFormAccount } from '@/types/accounts';
import type { TermDepositFormTermDeposit } from '@/types/term-deposits';
import type { CategoryAppearanceOptions } from '@/lib/category-appearance';
import {
    dialogAccounts,
    dialogCapitalMovement,
    dialogEntryToSpend,
    dialogExpenseCategories,
    dialogExpenseOperation,
    dialogHandEntryMonthRemaining,
    dialogIncomeCategories,
    dialogIncomeOperation,
    dialogOwnTransfer,
    dialogRefundOperation,
    dialogTermDeposits,
} from '@/lib/operation-dialogs-fixtures';

defineProps<{
    categoryAppearanceOptions: CategoryAppearanceOptions;
}>();

const states = [
    'category-new',
    'category-edit',
    'category-locked',
    'category-errors',
    'category-saving',
    'category-bad-amount',
    'category-month-item-kind',
    'budget-create',
    'budget-errors',
    'confirm-plain',
    'confirm-destructive',
    'confirm-saving',
    'confirm-deficit',
    'confirm-align-plan',
    'account-bank-new',
    'account-cash-new',
    'account-edit',
    'account-errors',
    'account-saving',
    'term-deposit-new',
    'term-deposit-edit',
    'term-deposit-negative',
    'term-deposit-errors',
    'term-deposit-saving',
    'expense-new',
    'expense-edit',
    'expense-refund-edit',
    'expense-warnings',
    'expense-month-without-plan',
    'expense-bad-amount',
    'expense-errors',
    'income-new',
    'income-edit',
    'income-errors',
    'transfer-new',
    'transfer-edit',
    'transfer-negative',
    'transfer-errors',
    'capital-new',
    'capital-edit',
    'capital-negative-deposit',
    'capital-errors',
] as const;

type SinkState = (typeof states)[number];

const labels: Record<SinkState, string> = {
    'category-new': 'Kategoria: nowa',
    'category-edit': 'Kategoria: edycja',
    'category-locked': 'Kategoria: typ zablokowany',
    'category-errors': 'Kategoria: błędy pól',
    'category-saving': 'Kategoria: zapis w toku',
    'category-bad-amount': 'Kategoria: zła kwota',
    'category-month-item-kind': 'Kategoria: rodzaj pozycji (wydatek)',
    'budget-create': 'Utwórz budżet',
    'budget-errors': 'Utwórz budżet: błędy',
    'confirm-plain': 'Potwierdzenie zwykłe',
    'confirm-destructive': 'Potwierdzenie destrukcyjne',
    'confirm-saving': 'Potwierdzenie: zapis w toku',
    'confirm-deficit': 'Potwierdzenie: ostrzeżenie o deficycie',
    'confirm-align-plan': 'Potwierdzenie: zakończ kategorię',
    'account-bank-new': 'Konto: nowe (bank)',
    'account-cash-new': 'Konto: nowe (gotówka, bez banku)',
    'account-edit': 'Konto: edycja',
    'account-errors': 'Konto: błędy pól',
    'account-saving': 'Konto: zapis w toku',
    'term-deposit-new': 'Lokata: nowa',
    'term-deposit-edit': 'Lokata: edycja',
    'term-deposit-negative': 'Lokata: ujemne saldo',
    'term-deposit-errors': 'Lokata: błędy pól',
    'term-deposit-saving': 'Lokata: zapis w toku',
    'expense-new': 'Wydatek: nowy',
    'expense-edit': 'Wydatek: edycja (przed = po)',
    'expense-refund-edit': 'Wydatek: edycja zwrotu (trzy typy)',
    'expense-warnings': 'Wydatek: ostrzeżenia w podglądzie',
    'expense-month-without-plan': 'Wydatek: miesiąc bez planu',
    'expense-bad-amount': 'Wydatek: zła kwota',
    'expense-errors': 'Wydatek: zapis i błędy (wybierz Dochód)',
    'income-new': 'Dochód: nowy',
    'income-edit': 'Dochód: edycja',
    'income-errors': 'Dochód: zapis i błędy (wybierz Zwrot)',
    'transfer-new': 'Transfer: nowy',
    'transfer-edit': 'Transfer: edycja (opis z wyciągu)',
    'transfer-negative': 'Transfer: ujemne saldo',
    'transfer-errors': 'Transfer: zapis i błędy',
    'capital-new': 'Ruch kapitału: nowy',
    'capital-edit': 'Ruch kapitału: edycja',
    'capital-negative-deposit': 'Ruch kapitału: ujemna lokata',
    'capital-errors': 'Ruch kapitału: zapis i błędy',
};

const account: AccountFormAccount = {
    id: 1,
    name: 'Konto osobiste',
    type: 'bank',
    bank: 'mbank',
    opening_date: '2026-01-01',
    opening_balance_minor_units: 150000,
};

const termDeposit: TermDepositFormTermDeposit = {
    id: 1,
    name: 'Lokata mBank 6M',
    bank: 'mbank',
    opening_date: '2026-01-15',
    end_date: '2026-07-15',
    opening_balance_minor_units: 1000000,
    current_balance_minor_units: 1200000,
};

/** Current balance already below zero, so the negative-balance warning shows on open. */
const negativeTermDeposit: TermDepositFormTermDeposit = {
    ...termDeposit,
    current_balance_minor_units: -50000,
};

const category: CategoryFormCategory = {
    id: 1,
    name: 'Jedzenie',
    icon: 'shopping-cart',
    color: 'blue',
    type: 'expense',
    planned_amount_minor_units: 120000,
    is_paced: true,
    month_item_kind: 'running',
};

/** An expense in a tight category on a low account: every warning fires at 900,00 zł. */
const warningOperation = {
    ...dialogExpenseOperation,
    account_id: 2,
    category_id: 2,
};

/** The current month has no plan, so Month Remaining warnings replace To Spend. */
const entryToSpendWithoutPlan = {
    budgets: dialogEntryToSpend.budgets.map((budget) => ({
        ...budget,
        months: [],
    })),
};

/**
 * Lokata -> konto ponad saldo małej lokaty. Zapisany ruch (200,00 zł) jest już
 * odjęty od salda 250,00 zł, więc ujemne saldo daje dopiero kwota ponad 450,00 zł.
 */
const negativeDepositMovement = {
    ...dialogCapitalMovement,
    direction: 'from_term_deposit' as const,
    term_deposit_id: 2,
    amount_minor_units: 20000,
};

const savingForm = DialogsKitchenSinkController.validate.form();
const validateForm = DialogsKitchenSinkController.validate.form();
const plainForm = { action: '#', method: 'post' } as const;

function initialState(): SinkState | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const requested = new URLSearchParams(window.location.search).get('open');

    return states.find((state) => state === requested) ?? null;
}

const openState = ref<SinkState | null>(initialState());

function isOpen(state: SinkState): boolean {
    return openState.value === state;
}

function setOpen(state: SinkState, value: boolean): void {
    openState.value = value ? state : null;
}
</script>

<template>
    <Head title="Dialogs kitchen sink" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Dialogs kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Każdy przycisk
                otwiera dialog; <code>?open=&lt;stan&gt;</code> otwiera stan
                przy wejściu. Zapisy formularzy kategorii i budżetu trafiają na
                ich zwykłe trasy.
            </p>
        </header>

        <div class="flex flex-wrap gap-2">
            <Button
                v-for="state in states"
                :key="state"
                variant="outline"
                :data-state-button="state"
                @click="setOpen(state, true)"
            >
                {{ labels[state] }}
            </Button>
        </div>

        <CategoryFormDialog
            :open="isOpen('category-new')"
            mode="create"
            :budget-id="1"
            default-type="expense"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-new', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-edit')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-edit', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-locked')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            lock-type
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-locked', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-errors')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            :form="validateForm"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-errors', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-saving')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            :form="validateForm"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-saving', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-bad-amount')"
            mode="edit"
            :budget-id="1"
            default-type="expense"
            :category="category"
            initial-planned-amount-input="12,345"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-bad-amount', $event)"
        />
        <CategoryFormDialog
            :open="isOpen('category-month-item-kind')"
            mode="create"
            :budget-id="1"
            default-type="expense"
            :category-appearance-options="categoryAppearanceOptions"
            @update:open="setOpen('category-month-item-kind', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-create')"
            @update:open="setOpen('budget-create', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-errors')"
            :form="validateForm"
            @update:open="setOpen('budget-errors', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-plain')"
            :form="plainForm"
            title="Rozpocząć realizację budżetu?"
            description="Plan zostanie zamrożony, a operacje będą liczone względem planu."
            confirm-label="Rozpocznij"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-plain', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-destructive')"
            :form="plainForm"
            destructive
            title="Usunąć kategorię?"
            description="Tej operacji nie można cofnąć."
            confirm-label="Usuń"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-destructive', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-saving')"
            :form="savingForm"
            title="Zapis w toku"
            description="Potwierdź, żeby zobaczyć spinner, ukryty X i zablokowane zamykanie (odpowiedź po około 1,5 s kończy się błędem)."
            confirm-label="Zapisz"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-saving', $event)"
        />
        <ConfirmActionDialog
            :open="isOpen('confirm-deficit')"
            :form="plainForm"
            title="Rozpocząć realizację budżetu?"
            description="Plan zostanie zamrożony, a operacje będą liczone względem planu."
            confirm-label="Rozpocznij"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-deficit', $event)"
        >
            <Alert variant="warning">
                Planowany deficyt wynosi 1 200,00 zł, a płynność na początek
                roku 400,00 zł. Niepokryty deficyt: 800,00 zł.
            </Alert>
        </ConfirmActionDialog>
        <ConfirmActionDialog
            :open="isOpen('confirm-align-plan')"
            :form="plainForm"
            title="Zakończyć kategorię?"
            description="Plan bieżący zostanie ustawiony na kwotę wykonania. Pozostało będzie wynosić 0."
            confirm-label="Zakończ"
            cancel-label="Anuluj"
            @update:open="setOpen('confirm-align-plan', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-new')"
            mode="create"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-new', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-edit')"
            mode="edit"
            :operation="dialogExpenseOperation"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-edit', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-refund-edit')"
            mode="edit"
            :operation="dialogRefundOperation"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-refund-edit', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-warnings')"
            mode="edit"
            :operation="warningOperation"
            initial-amount-input="900,00"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-warnings', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-month-without-plan')"
            mode="edit"
            :operation="dialogExpenseOperation"
            initial-amount-input="5000,00"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="entryToSpendWithoutPlan"
            @update:open="setOpen('expense-month-without-plan', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-bad-amount')"
            mode="edit"
            :operation="dialogExpenseOperation"
            initial-amount-input="abc"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-bad-amount', $event)"
        />
        <ExpenseFormDialog
            :open="isOpen('expense-errors')"
            mode="edit"
            :operation="dialogRefundOperation"
            :form="validateForm"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-errors', $event)"
        />
        <IncomeFormDialog
            :open="isOpen('income-new')"
            mode="create"
            :accounts="dialogAccounts"
            :income-categories="dialogIncomeCategories"
            :expense-categories="dialogExpenseCategories"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('income-new', $event)"
        />
        <IncomeFormDialog
            :open="isOpen('income-edit')"
            mode="edit"
            :operation="dialogIncomeOperation"
            :accounts="dialogAccounts"
            :income-categories="dialogIncomeCategories"
            :expense-categories="dialogExpenseCategories"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('income-edit', $event)"
        />
        <IncomeFormDialog
            :open="isOpen('income-errors')"
            mode="edit"
            :operation="dialogIncomeOperation"
            :form="validateForm"
            :accounts="dialogAccounts"
            :income-categories="dialogIncomeCategories"
            :expense-categories="dialogExpenseCategories"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('income-errors', $event)"
        />
        <OwnTransferFormDialog
            :open="isOpen('transfer-new')"
            mode="create"
            :accounts="dialogAccounts"
            @update:open="setOpen('transfer-new', $event)"
        />
        <OwnTransferFormDialog
            :open="isOpen('transfer-edit')"
            mode="edit"
            :transfer="dialogOwnTransfer"
            :accounts="dialogAccounts"
            @update:open="setOpen('transfer-edit', $event)"
        />
        <OwnTransferFormDialog
            :open="isOpen('transfer-negative')"
            mode="edit"
            :transfer="dialogOwnTransfer"
            initial-amount-input="5000,00"
            :accounts="dialogAccounts"
            @update:open="setOpen('transfer-negative', $event)"
        />
        <OwnTransferFormDialog
            :open="isOpen('transfer-errors')"
            mode="edit"
            :transfer="dialogOwnTransfer"
            :form="validateForm"
            :accounts="dialogAccounts"
            @update:open="setOpen('transfer-errors', $event)"
        />
        <CapitalMovementFormDialog
            :open="isOpen('capital-new')"
            mode="create"
            :accounts="dialogAccounts"
            :term-deposits="dialogTermDeposits"
            @update:open="setOpen('capital-new', $event)"
        />
        <CapitalMovementFormDialog
            :open="isOpen('capital-edit')"
            mode="edit"
            :movement="dialogCapitalMovement"
            :accounts="dialogAccounts"
            :term-deposits="dialogTermDeposits"
            @update:open="setOpen('capital-edit', $event)"
        />
        <CapitalMovementFormDialog
            :open="isOpen('capital-negative-deposit')"
            mode="edit"
            :movement="negativeDepositMovement"
            initial-amount-input="500,00"
            :accounts="dialogAccounts"
            :term-deposits="dialogTermDeposits"
            @update:open="setOpen('capital-negative-deposit', $event)"
        />
        <CapitalMovementFormDialog
            :open="isOpen('capital-errors')"
            mode="edit"
            :movement="dialogCapitalMovement"
            :form="validateForm"
            :accounts="dialogAccounts"
            :term-deposits="dialogTermDeposits"
            @update:open="setOpen('capital-errors', $event)"
        />
        <AccountFormDialog
            :open="isOpen('account-bank-new')"
            mode="create"
            @update:open="setOpen('account-bank-new', $event)"
        />
        <AccountFormDialog
            :open="isOpen('account-cash-new')"
            mode="create"
            default-type="cash"
            @update:open="setOpen('account-cash-new', $event)"
        />
        <AccountFormDialog
            :open="isOpen('account-edit')"
            mode="edit"
            :account="account"
            @update:open="setOpen('account-edit', $event)"
        />
        <AccountFormDialog
            :open="isOpen('account-errors')"
            mode="create"
            :form="validateForm"
            @update:open="setOpen('account-errors', $event)"
        />
        <AccountFormDialog
            :open="isOpen('account-saving')"
            mode="create"
            :form="savingForm"
            @update:open="setOpen('account-saving', $event)"
        />
        <TermDepositFormDialog
            :open="isOpen('term-deposit-new')"
            mode="create"
            @update:open="setOpen('term-deposit-new', $event)"
        />
        <TermDepositFormDialog
            :open="isOpen('term-deposit-edit')"
            mode="edit"
            :term-deposit="termDeposit"
            @update:open="setOpen('term-deposit-edit', $event)"
        />
        <TermDepositFormDialog
            :open="isOpen('term-deposit-negative')"
            mode="edit"
            :term-deposit="negativeTermDeposit"
            @update:open="setOpen('term-deposit-negative', $event)"
        />
        <TermDepositFormDialog
            :open="isOpen('term-deposit-errors')"
            mode="create"
            :form="validateForm"
            @update:open="setOpen('term-deposit-errors', $event)"
        />
        <TermDepositFormDialog
            :open="isOpen('term-deposit-saving')"
            mode="create"
            :form="savingForm"
            @update:open="setOpen('term-deposit-saving', $event)"
        />
    </div>
</template>
