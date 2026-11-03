<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { ref } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import ExpenseFormDialog, {
    type ExpenseFormAccount,
    type ExpenseFormCategory,
} from '@/components/ExpenseFormDialog.vue';
import type { EntryToSpendInputs } from '@/lib/entry-to-spend-warning';
import type { HandEntryMonthRemainingInputs } from '@/lib/hand-entry-month-remaining-preview';
import FinancialOperationRow, {
    type FinancialOperationRowData,
} from '@/components/FinancialOperationRow.vue';
import IncomeFormDialog, {
    type IncomeFormCategory,
} from '@/components/IncomeFormDialog.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import OperationsFilters, {
    type OperationsFilterAccount,
    type OperationsFilterCategory,
    type OperationsFilterTermDeposit,
    type OperationsListFilters,
} from '@/components/OperationsFilters.vue';
import CapitalMovementFormDialog, {
    type CapitalMovementFormTermDeposit,
} from '@/components/CapitalMovementFormDialog.vue';
import CapitalMovementRow, {
    type CapitalMovementRowData,
} from '@/components/CapitalMovementRow.vue';
import OperationsPageActions from '@/components/OperationsPageActions.vue';
import OwnTransferFormDialog from '@/components/OwnTransferFormDialog.vue';
import OwnTransferRow, {
    type OwnTransferRowData,
} from '@/components/OwnTransferRow.vue';
import { type PaginatedCollection } from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
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

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.operations,
            href: operationsRoute(),
        },
    ],
});

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
            <h1 class="text-lg font-semibold">{{ t.operations.title }}</h1>
            <OperationsPageActions
                :accounts-count="accounts.length"
                :term-deposits-count="termDeposits.length"
                @add-income="incomeDialogOpen = true"
                @add-expense="expenseDialogOpen = true"
                @add-transfer="createDialogOpen = true"
                @add-capital-movement="capitalMovementCreateDialogOpen = true"
            />
        </div>

        <OperationsFilters
            v-if="hasAnyRecords"
            :filters="filters"
            :filters-active="filtersActive"
            :years="filterYears"
            :categories="filterCategories"
            :accounts="filterAccounts"
            :term-deposits="filterTermDeposits"
            :suppressed-sections="suppressedSections"
        />

        <IndexEmptyState
            v-if="!hasAnyRecords"
            :title="t.operations.empty_title"
            :description="t.operations.empty_description"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="CsvImportController.create.url()">
                        <Upload class="size-4" />
                        {{ t.operations.import_title }}
                    </Link>
                </Button>
                <Button variant="outline" @click="incomeDialogOpen = true">
                    <Plus class="size-4" />
                    {{ t.operations.add_income }}
                </Button>
            </template>
        </IndexEmptyState>

        <IndexEmptyState
            v-else-if="
                operations.total === 0 &&
                ownTransfers.total === 0 &&
                capitalMovements.total === 0
            "
            :title="t.operations.empty_filtered_title"
            :description="t.operations.empty_filtered_description"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="operationsRoute()">
                        {{ t.operations.filter_clear }}
                    </Link>
                </Button>
            </template>
        </IndexEmptyState>

        <ListSurface
            v-if="operations.total > 0"
            :description="t.operations.list_description"
            :paginator="operations"
        >
            <div class="operations-grid operations-header-row">
                <span class="operations-cell-date">
                    {{ t.operations.date }}
                </span>
                <span class="operations-cell-description">
                    {{ t.operations.description }}
                </span>
                <span class="operations-cell-category">
                    {{ t.operations.category }}
                </span>
                <span class="operations-cell-account">
                    {{ t.operations.account }}
                </span>
                <span class="operations-cell-amount">
                    {{ t.operations.amount }}
                </span>
                <span class="operations-cell-actions text-right">
                    {{ t.operations.actions }}
                </span>
            </div>

            <FinancialOperationRow
                v-for="operation in operations.data"
                :key="operation.id"
                :operation="operation"
                @edit="
                    operation.type === 'income'
                        ? openIncomeEditDialog(operation)
                        : openExpenseEditDialog(operation)
                "
            />
        </ListSurface>

        <ListSurface
            v-if="ownTransfers.total > 0"
            :title="t.operations.own_transfers"
            :paginator="ownTransfers"
        >
            <div
                class="operations-grid operations-grid-transfer operations-header-row"
            >
                <span class="operations-cell-date">
                    {{ t.operations.date }}
                </span>
                <span class="operations-cell-description">
                    {{ t.operations.description }}
                </span>
                <span class="operations-cell-account">
                    {{ t.operations.accounts }}
                </span>
                <span class="operations-cell-amount">
                    {{ t.operations.amount }}
                </span>
                <span class="operations-cell-actions text-right">
                    {{ t.operations.actions }}
                </span>
            </div>

            <OwnTransferRow
                v-for="transfer in ownTransfers.data"
                :key="transfer.id"
                :transfer="transfer"
                @edit="openEditDialog(transfer)"
            />
        </ListSurface>

        <ListSurface
            v-if="capitalMovements.total > 0"
            :title="t.operations.capital_movements"
            :paginator="capitalMovements"
        >
            <div
                class="operations-grid operations-grid-transfer operations-header-row"
            >
                <span class="operations-cell-date">
                    {{ t.operations.date }}
                </span>
                <span class="operations-cell-description">
                    {{ t.operations.description }}
                </span>
                <span class="operations-cell-account">
                    {{ t.operations.accounts }}
                </span>
                <span class="operations-cell-amount">
                    {{ t.operations.amount }}
                </span>
                <span class="operations-cell-actions text-right">
                    {{ t.operations.actions }}
                </span>
            </div>

            <CapitalMovementRow
                v-for="movement in capitalMovements.data"
                :key="movement.id"
                :movement="movement"
                @edit="openCapitalMovementEditDialog(movement)"
            />
        </ListSurface>
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
