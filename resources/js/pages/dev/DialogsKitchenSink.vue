<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import DialogsKitchenSinkController from '@/actions/App/Http/Controllers/Dev/DialogsKitchenSinkController';
import AccountFormDialog from '@/components/AccountFormDialog.vue';
import CategoryFormDialog from '@/components/CategoryFormDialog.vue';
import CategoryListFormDialog from '@/components/CategoryListFormDialog.vue';
import type { CategoryFormCategory } from '@/components/CategoryFormDialog.vue';
import CategoryGroupAssignDialog from '@/components/CategoryGroupAssignDialog.vue';
import CategoryGroupFormDialog from '@/components/CategoryGroupFormDialog.vue';
import CategoryMergeDialog from '@/components/CategoryMergeDialog.vue';
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
import type { CategoryMergePreviewState } from '@/composables/useCategoryMergePreview';
import type {
    CategoryAppearanceOptions,
    CategoryPickerOption,
} from '@/lib/category-appearance';
import {
    expenseGroups,
    mergeArchivedSourcePreview,
    mergeCollectedLostPreview,
    mergeCollectedPreview,
    mergeFoldPreview,
    mergeMovePreview,
} from '@/lib/categories-fixtures';
import type { CategoryListItem } from '@/types/categories';
import {
    dialogAccounts,
    dialogCapitalMovement,
    dialogEntryToSpend,
    dialogPreviousMonthDate,
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
    'category-list-new',
    'category-list-edit',
    'category-list-locked',
    'category-list-errors',
    'category-list-name-archived',
    'category-list-saving',
    'category-list-rhythm',
    'category-list-income',
    'category-list-rhythm-error',
    'category-list-irregular-new',
    'category-list-irregular-edit',
    'category-list-irregular-bad-amount',
    'category-group-new',
    'category-group-rename-error',
    'category-group-assign',
    'category-group-assign-changed',
    'category-group-assign-saving',
    'category-group-assign-error',
    'merge-idle',
    'merge-loading',
    'merge-move',
    'merge-fold',
    'merge-collected',
    'merge-collected-lost',
    'merge-archived-source',
    'merge-preview-error',
    'merge-saving',
    'merge-server-error',
    'budget-create',
    'budget-create-no-source',
    'budget-create-empty',
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
    'expense-later-month-warning',
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
    'category-list-new': 'Kategoria listy: nowa',
    'category-list-edit': 'Kategoria listy: edycja z opisem',
    'category-list-locked': 'Kategoria listy: typ zablokowany',
    'category-list-errors': 'Kategoria listy: błędy pól',
    'category-list-name-archived':
        'Kategoria listy: nazwa zajęta przez archiwalną',
    'category-list-saving': 'Kategoria listy: zapis w toku',
    'category-list-rhythm':
        'Kategoria listy: edycja rytmu (równomierna roczna)',
    'category-list-income': 'Kategoria listy: przychód (bez pola Rytm)',
    'category-list-rhythm-error': 'Kategoria listy: błąd pola Rytm',
    'category-list-irregular-new':
        'Kategoria listy: nowe oznaczenie nieregularnej (zebrane startowe)',
    'category-list-irregular-edit':
        'Kategoria listy: edycja istniejącej nieregularnej',
    'category-list-irregular-bad-amount':
        'Kategoria listy: zła kwota zebranego startowego',
    'budget-create': 'Utwórz budżet: szkic z planem z poprzedniego roku',
    'budget-create-no-source': 'Utwórz budżet: bez wcześniejszego budżetu',
    'budget-create-empty': 'Utwórz budżet: pusta lista kategorii',
    'category-group-new': 'Grupa kategorii: nowa',
    'category-group-rename-error':
        'Grupa kategorii: zmiana nazwy (błąd po zapisie)',
    'category-group-assign': 'Grupa kategorii: zmiana grupy',
    'category-group-assign-changed':
        'Grupa kategorii: zmiana grupy (wybrana inna grupa)',
    'category-group-assign-saving':
        'Grupa kategorii: zmiana grupy (zapis w toku)',
    'category-group-assign-error':
        'Grupa kategorii: zmiana grupy (błąd po zapisie)',
    'merge-idle': 'Scalanie: bez wybranego celu',
    'merge-loading': 'Scalanie: wczytywanie podglądu',
    'merge-move': 'Scalanie: przepięcie pozycji (para z produkcji)',
    'merge-fold': 'Scalanie: złożenie pozycji i plany miesięcy',
    'merge-collected': 'Scalanie: zebrane obu kategorii',
    'merge-collected-lost': 'Scalanie: utrata zebranego',
    'merge-archived-source': 'Scalanie: archiwalne źródło',
    'merge-preview-error': 'Scalanie: błąd podglądu',
    'merge-saving': 'Scalanie: zapis w toku',
    'merge-server-error': 'Scalanie: błąd po zapisie',
    'budget-errors': 'Utwórz budżet: błędy',
    'confirm-plain': 'Potwierdzenie zwykłe',
    'confirm-destructive': 'Potwierdzenie destrukcyjne',
    'confirm-saving': 'Potwierdzenie: zapis w toku',
    'confirm-deficit': 'Potwierdzenie: ostrzeżenie o deficycie',
    'confirm-align-plan': 'Potwierdzenie: wyrównaj plan',
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
    'expense-later-month-warning':
        'Wydatek: ostrzeżenie o późniejszym miesiącu (nieregularna)',
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
};

const listCategory: CategoryListItem = {
    id: 1,
    name: 'Jedzenie',
    icon: 'shopping-cart',
    color: 'blue',
    type: 'expense',
    group_id: null,
    sort_order: 0,
    description:
        'Zakupy spożywcze, chemia domowa i drobiazgi z osiedlowego sklepu.',
    rhythm: 'running',
    opening_collected_minor_units: null,
    irregular_since: null,
    budget_years: [2026],
    can_change_type: true,
    can_delete: true,
    archived_at: null,
};

/** A Category in "Opłaty stałe" (`expenseGroups`), for the group assign dialog. */
const groupedCategory: CategoryListItem = {
    ...listCategory,
    name: 'Prąd',
    group_id: 32,
};

const rhythmChangeMonth = { year: 2026, month: 11 };

const mergeSource: CategoryListItem = {
    ...listCategory,
    name: 'Jedzenie poza domem',
};

const mergeTargets: CategoryPickerOption[] = [
    { id: 2, name: 'Restauracje', icon: 'shopping-cart', color: 'blue' },
    { id: 3, name: 'Utrzymanie domu', icon: 'tag', color: 'blue' },
];

const mergeTargetId = 2;

/** Each state of the merge dialog: the preview is given, so no request is made. */
const mergeDialogs: {
    state: SinkState;
    source?: CategoryListItem;
    previewState: CategoryMergePreviewState;
    form?: 'saving' | 'validate';
    withTarget?: boolean;
}[] = [
    {
        state: 'merge-idle',
        previewState: { status: 'idle' },
        withTarget: false,
    },
    { state: 'merge-loading', previewState: { status: 'loading' } },
    {
        state: 'merge-move',
        previewState: { status: 'loaded', preview: mergeMovePreview },
    },
    {
        state: 'merge-fold',
        previewState: { status: 'loaded', preview: mergeFoldPreview },
    },
    {
        state: 'merge-collected',
        source: { ...mergeSource, name: 'Energia' },
        previewState: { status: 'loaded', preview: mergeCollectedPreview },
    },
    {
        state: 'merge-collected-lost',
        source: { ...mergeSource, name: 'Energia' },
        previewState: { status: 'loaded', preview: mergeCollectedLostPreview },
    },
    {
        state: 'merge-archived-source',
        source: {
            ...mergeSource,
            name: 'Wakacje',
            archived_at: '2026-03-01T10:00:00Z',
        },
        previewState: {
            status: 'loaded',
            preview: mergeArchivedSourcePreview,
        },
    },
    {
        state: 'merge-preview-error',
        previewState: {
            status: 'error',
            message: 'Przywróć kategorię docelową przed scaleniem.',
        },
    },
    {
        state: 'merge-saving',
        previewState: { status: 'loaded', preview: mergeMovePreview },
        form: 'saving',
    },
    {
        state: 'merge-server-error',
        previewState: { status: 'loaded', preview: mergeMovePreview },
        form: 'validate',
    },
];

const evenAnnualListCategory: CategoryListItem = {
    ...listCategory,
    rhythm: 'even_annual',
};

const irregularListCategory: CategoryListItem = {
    ...listCategory,
    name: 'Serwis auta',
    rhythm: 'irregular',
    opening_collected_minor_units: 50000,
    irregular_since: { year: 2026, month: 8 },
};

const newlyIrregularListCategory: CategoryListItem = {
    ...listCategory,
    rhythm: 'irregular',
};

const incomeListCategory: CategoryListItem = {
    ...listCategory,
    name: 'Wynagrodzenie',
    type: 'income',
    rhythm: null,
};

const lockedListCategory: CategoryListItem = {
    ...listCategory,
    can_change_type: false,
    can_delete: false,
};

/** An expense in a tight category on a low account: every warning fires at 900,00 zł. */
const warningOperation = {
    ...dialogExpenseOperation,
    account_id: 2,
    category_id: 2,
};

/** An overdue bill of an Irregular category from the previous month uses up the Collected of the current one. */
const laterMonthOperation = {
    ...dialogExpenseOperation,
    operation_date: dialogPreviousMonthDate,
    amount_minor_units: 1000,
    category_id: 5,
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
/** Rejects the name the way the server does when an archived Category holds it. */
const nameArchivedForm = DialogsKitchenSinkController.validate.form({
    query: { error: 'name-archived' },
});
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
        <CategoryListFormDialog
            :open="isOpen('category-list-new')"
            mode="create"
            default-type="expense"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-new', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-edit')"
            mode="edit"
            default-type="expense"
            :category="listCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-edit', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-locked')"
            mode="edit"
            default-type="expense"
            :category="lockedListCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-locked', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-errors')"
            mode="edit"
            default-type="expense"
            :category="listCategory"
            :form="validateForm"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-errors', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-name-archived')"
            mode="create"
            default-type="expense"
            :form="nameArchivedForm"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-name-archived', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-saving')"
            mode="edit"
            default-type="expense"
            :category="listCategory"
            :form="savingForm"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-saving', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-rhythm')"
            mode="edit"
            default-type="expense"
            :category="evenAnnualListCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-rhythm', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-income')"
            mode="edit"
            default-type="income"
            :category="incomeListCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-income', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-rhythm-error')"
            mode="edit"
            default-type="expense"
            :category="listCategory"
            :form="validateForm"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-rhythm-error', $event)"
        />
        <!-- A new marking: „nieregularna” chosen, no period yet, so the hint is the one for a new marking. -->
        <CategoryListFormDialog
            :open="isOpen('category-list-irregular-new')"
            mode="edit"
            default-type="expense"
            :category="newlyIrregularListCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-irregular-new', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-irregular-edit')"
            mode="edit"
            default-type="expense"
            :category="irregularListCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-irregular-edit', $event)"
        />
        <CategoryListFormDialog
            :open="isOpen('category-list-irregular-bad-amount')"
            mode="edit"
            default-type="expense"
            :category="irregularListCategory"
            initial-opening-collected-input="12,345"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
            @update:open="setOpen('category-list-irregular-bad-amount', $event)"
        />
        <CategoryGroupFormDialog
            :open="isOpen('category-group-new')"
            mode="create"
            type="expense"
            @update:open="setOpen('category-group-new', $event)"
        />
        <!-- The sink endpoint waits and rejects the name, so renaming touches no data. -->
        <CategoryGroupFormDialog
            :open="isOpen('category-group-rename-error')"
            mode="rename"
            type="expense"
            :group="{ id: 1, name: 'Opłaty stałe' }"
            :form="validateForm"
            @update:open="setOpen('category-group-rename-error', $event)"
        />
        <CategoryGroupAssignDialog
            :open="isOpen('category-group-assign')"
            :category="groupedCategory"
            :groups="expenseGroups"
            @update:open="setOpen('category-group-assign', $event)"
        />
        <CategoryGroupAssignDialog
            :open="isOpen('category-group-assign-changed')"
            :category="groupedCategory"
            :groups="expenseGroups"
            :initial-group-id="33"
            @update:open="setOpen('category-group-assign-changed', $event)"
        />
        <CategoryGroupAssignDialog
            :open="isOpen('category-group-assign-saving')"
            :category="groupedCategory"
            :groups="expenseGroups"
            :initial-group-id="33"
            :form="validateForm"
            @update:open="setOpen('category-group-assign-saving', $event)"
        />
        <CategoryGroupAssignDialog
            :open="isOpen('category-group-assign-error')"
            :category="groupedCategory"
            :groups="expenseGroups"
            :initial-group-id="33"
            :form="validateForm"
            @update:open="setOpen('category-group-assign-error', $event)"
        />
        <CategoryMergeDialog
            v-for="dialog in mergeDialogs"
            :key="dialog.state"
            :open="isOpen(dialog.state)"
            :source="dialog.source ?? mergeSource"
            :targets="mergeTargets"
            :preview-state="dialog.previewState"
            :initial-target-id="
                dialog.withTarget === false ? undefined : mergeTargetId
            "
            :form="
                dialog.form === 'saving'
                    ? savingForm
                    : dialog.form === 'validate'
                      ? validateForm
                      : undefined
            "
            @update:open="setOpen(dialog.state, $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-create')"
            :initial-year="2028"
            :budget-years="[2026, 2027]"
            :active-category-count="40"
            @update:open="setOpen('budget-create', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-create-no-source')"
            :initial-year="2025"
            :budget-years="[2026, 2027]"
            :active-category-count="40"
            @update:open="setOpen('budget-create-no-source', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-create-empty')"
            :initial-year="2028"
            :budget-years="[2026, 2027]"
            :active-category-count="0"
            @update:open="setOpen('budget-create-empty', $event)"
        />
        <CreateBudgetDialog
            :open="isOpen('budget-errors')"
            :initial-year="2028"
            :budget-years="[2026, 2027]"
            :active-category-count="40"
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
            title="Wyrównać plan do wykonania?"
            description="Plan bieżący zostanie ustawiony na kwotę wykonania. Pozostało będzie wynosić 0."
            confirm-label="Wyrównaj"
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
            :open="isOpen('expense-later-month-warning')"
            mode="edit"
            :operation="laterMonthOperation"
            initial-amount-input="200,00"
            :accounts="dialogAccounts"
            :expense-categories="dialogExpenseCategories"
            :income-categories="dialogIncomeCategories"
            :hand-entry-month-remaining="dialogHandEntryMonthRemaining"
            :entry-to-spend="dialogEntryToSpend"
            @update:open="setOpen('expense-later-month-warning', $event)"
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
