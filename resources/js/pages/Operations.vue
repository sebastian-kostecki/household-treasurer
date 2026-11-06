<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import ExpenseFormDialog, {
    type ExpenseFormAccount,
    type ExpenseFormCategory,
} from '@/components/ExpenseFormDialog.vue';
import type { EntryToSpendInputs } from '@/lib/entry-to-spend-warning';
import type { HandEntryMonthRemainingInputs } from '@/lib/hand-entry-month-remaining-preview';
import { type FinancialOperationRowData } from '@/components/FinancialOperationRow.vue';
import IncomeFormDialog, {
    type IncomeFormCategory,
} from '@/components/IncomeFormDialog.vue';
import {
    type OperationsFilterAccount,
    type OperationsFilterCategory,
    type OperationsFilterTermDeposit,
    type OperationsListFilters,
} from '@/components/OperationsFilters.vue';
import CapitalMovementFormDialog, {
    type CapitalMovementFormTermDeposit,
} from '@/components/CapitalMovementFormDialog.vue';
import { type CapitalMovementRowData } from '@/components/CapitalMovementRow.vue';
import type { OperationsListSection } from '@/components/OperationsActiveFilterChips.vue';
import OperationsListPanel from '@/components/OperationsListPanel.vue';
import OperationsPageActions from '@/components/OperationsPageActions.vue';
import OwnTransferFormDialog from '@/components/OwnTransferFormDialog.vue';
import { type OwnTransferRowData } from '@/components/OwnTransferRow.vue';
import { type PaginatedCollection } from '@/components/PaginationLinks.vue';
import { useTranslations } from '@/composables/useTranslations';
import { operationsListQuery } from '@/lib/operations-list-query';
import { listenForOperationsListVisits } from '@/lib/operations-list-visit';
import { operations as operationsRoute } from '@/routes';

type AccountRow = ExpenseFormAccount & {
    type: 'bank' | 'cash';
};

const {
    operations,
    ownTransfers,
    capitalMovements,
    termDeposits,
    accounts,
    incomeCategories,
    expenseCategories,
    handEntryMonthRemaining,
    entryToSpend,
    filters,
    filtersActive,
    suppressedSections,
    hasAnyRecords,
    section,
    operationDaySums,
    sectionTotals,
    filterYears,
    filterCategories,
    filterAccounts,
    filterTermDeposits,
} = defineProps<{
    operations: PaginatedCollection<FinancialOperationRowData>;
    ownTransfers: PaginatedCollection<OwnTransferRowData>;
    capitalMovements: PaginatedCollection<CapitalMovementRowData>;
    termDeposits: CapitalMovementFormTermDeposit[];
    accounts: AccountRow[];
    incomeCategories: IncomeFormCategory[];
    expenseCategories: ExpenseFormCategory[];
    handEntryMonthRemaining: HandEntryMonthRemainingInputs;
    entryToSpend: EntryToSpendInputs;
    filters: OperationsListFilters;
    filtersActive: boolean;
    suppressedSections: Array<
        'financial_operations' | 'own_transfers' | 'capital_movements'
    >;
    hasAnyRecords: boolean;
    section: OperationsListSection;
    operationDaySums: Record<string, number>;
    sectionTotals: Record<OperationsListSection, number>;
    filterYears: number[];
    filterCategories: OperationsFilterCategory[];
    filterAccounts: OperationsFilterAccount[];
    filterTermDeposits: OperationsFilterTermDeposit[];
}>();

const t = useTranslations();
const incomeDialogOpen = ref(false);
const expenseDialogOpen = ref(false);
const incomeEditDialogOpen = ref(false);
const expenseEditDialogOpen = ref(false);
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const capitalMovementCreateDialogOpen = ref(false);
const capitalMovementEditDialogOpen = ref(false);
const editingIncome = ref<FinancialOperationRowData | null>(null);
const editingExpense = ref<FinancialOperationRowData | null>(null);
const editingTransfer = ref<OwnTransferRowData | null>(null);
const editingCapitalMovement = ref<CapitalMovementRowData | null>(null);
const listBusy = ref(false);
let stopListeningForListVisits: (() => void) | null = null;

onMounted(() => {
    stopListeningForListVisits = listenForOperationsListVisits(
        operationsRoute().url,
        (busy) => {
            listBusy.value = busy;
        },
    );
});

onUnmounted(() => {
    stopListeningForListVisits?.();
});

function changeSection(value: OperationsListSection): void {
    router.get(
        operationsRoute({ query: operationsListQuery(filters, value) }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function openEditDialog(transfer: OwnTransferRowData): void {
    editingTransfer.value = transfer;
    editDialogOpen.value = true;
}

function openCapitalMovementEditDialog(movement: CapitalMovementRowData): void {
    editingCapitalMovement.value = movement;
    capitalMovementEditDialogOpen.value = true;
}

function openIncomeEditDialog(operation: FinancialOperationRowData): void {
    editingIncome.value = operation;
    incomeEditDialogOpen.value = true;
}

function openExpenseEditDialog(operation: FinancialOperationRowData): void {
    editingExpense.value = operation;
    expenseEditDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t.operations.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <h1 class="page-title">{{ t.operations.title }}</h1>
                <p class="meta text-muted-foreground">
                    {{ t.operations.list_description }}
                </p>
            </div>
            <OperationsPageActions
                :accounts-count="accounts.length"
                :term-deposits-count="termDeposits.length"
                @add-income="incomeDialogOpen = true"
                @add-expense="expenseDialogOpen = true"
                @add-transfer="createDialogOpen = true"
                @add-capital-movement="capitalMovementCreateDialogOpen = true"
            />
        </div>

        <OperationsListPanel
            :section="section"
            :has-any-records="hasAnyRecords"
            :operations="operations"
            :own-transfers="ownTransfers"
            :capital-movements="capitalMovements"
            :operation-day-sums="operationDaySums"
            :section-totals="sectionTotals"
            :filters="filters"
            :filters-active="filtersActive"
            :suppressed-sections="suppressedSections"
            :filter-years="filterYears"
            :filter-categories="filterCategories"
            :filter-accounts="filterAccounts"
            :filter-term-deposits="filterTermDeposits"
            :busy="listBusy"
            @change-section="changeSection"
            @add-income="incomeDialogOpen = true"
            @edit-income="openIncomeEditDialog"
            @edit-expense="openExpenseEditDialog"
            @edit-transfer="openEditDialog"
            @edit-capital-movement="openCapitalMovementEditDialog"
        />
    </div>

    <IncomeFormDialog
        v-model:open="incomeDialogOpen"
        mode="create"
        :accounts="accounts"
        :income-categories="incomeCategories"
        :entry-to-spend="entryToSpend"
    />

    <IncomeFormDialog
        v-model:open="incomeEditDialogOpen"
        mode="edit"
        :operation="editingIncome"
        :accounts="accounts"
        :income-categories="incomeCategories"
        :expense-categories="expenseCategories"
        :entry-to-spend="entryToSpend"
    />

    <ExpenseFormDialog
        v-model:open="expenseDialogOpen"
        mode="create"
        :accounts="accounts"
        :expense-categories="expenseCategories"
        :hand-entry-month-remaining="handEntryMonthRemaining"
        :entry-to-spend="entryToSpend"
    />

    <ExpenseFormDialog
        v-model:open="expenseEditDialogOpen"
        mode="edit"
        :operation="editingExpense"
        :accounts="accounts"
        :expense-categories="expenseCategories"
        :income-categories="incomeCategories"
        :hand-entry-month-remaining="handEntryMonthRemaining"
        :entry-to-spend="entryToSpend"
    />

    <OwnTransferFormDialog
        v-model:open="createDialogOpen"
        mode="create"
        :accounts="accounts"
    />

    <OwnTransferFormDialog
        v-model:open="editDialogOpen"
        mode="edit"
        :transfer="editingTransfer"
        :accounts="accounts"
    />

    <CapitalMovementFormDialog
        v-model:open="capitalMovementCreateDialogOpen"
        mode="create"
        :accounts="accounts"
        :term-deposits="termDeposits"
    />

    <CapitalMovementFormDialog
        v-model:open="capitalMovementEditDialogOpen"
        mode="edit"
        :movement="editingCapitalMovement"
        :accounts="accounts"
        :term-deposits="termDeposits"
    />
</template>
