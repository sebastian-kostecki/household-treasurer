<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { ref } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import CategoryChip from '@/components/CategoryChip.vue';
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import ExpenseFormDialog, {
    type ExpenseFormAccount,
    type ExpenseFormCategory,
    type ExpenseFormOperation,
} from '@/components/ExpenseFormDialog.vue';
import FinancialOperationActions from '@/components/FinancialOperationActions.vue';
import IncomeFormDialog, {
    type IncomeFormCategory,
    type IncomeFormOperation,
} from '@/components/IncomeFormDialog.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import OperationsDateCell from '@/components/OperationsDateCell.vue';
import OwnTransferActions from '@/components/OwnTransferActions.vue';
import OwnTransferFormDialog, {
    type OwnTransferFormTransfer,
} from '@/components/OwnTransferFormDialog.vue';
import {
    type PaginatedCollection,
} from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { PrefixedAccountIdentityFields } from '@/lib/account-identity';
import {
    financialOperationAmountClass,
    formatFinancialOperationAmount,
    formatMoney,
} from '@/lib/format';
import { operations as operationsRoute } from '@/routes';

type OperationRow = IncomeFormOperation &
    ExpenseFormOperation & {
        type: 'income' | 'expense';
        category_name: string;
        counterparty: string | null;
        raw_statement_description: string | null;
    } & PrefixedAccountIdentityFields<'account'>;

type OwnTransferRow = OwnTransferFormTransfer &
    PrefixedAccountIdentityFields<'source_account'> &
    PrefixedAccountIdentityFields<'destination_account'> & {
        raw_statement_description: string | null;
    };

type AccountRow = ExpenseFormAccount & {
    type: 'bank' | 'cash';
};

const {
    operations,
    ownTransfers,
    accounts,
    incomeCategories,
    expenseCategories,
} = defineProps<{
    operations: PaginatedCollection<OperationRow>;
    ownTransfers: PaginatedCollection<OwnTransferRow>;
    accounts: AccountRow[];
    incomeCategories: IncomeFormCategory[];
    expenseCategories: ExpenseFormCategory[];
}>();

const t = useTranslations();
const incomeDialogOpen = ref(false);
const expenseDialogOpen = ref(false);
const incomeEditDialogOpen = ref(false);
const expenseEditDialogOpen = ref(false);
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingIncome = ref<OperationRow | null>(null);
const editingExpense = ref<OperationRow | null>(null);
const editingTransfer = ref<OwnTransferRow | null>(null);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.operations,
            href: operationsRoute(),
        },
    ],
});

function operationTypeVariant(
    operation: OperationRow,
): 'income' | 'expense' | 'refund' {
    if (operation.type === 'income') {
        return 'income';
    }

    if (operation.amount_grosze < 0) {
        return 'refund';
    }

    return 'expense';
}

function openEditDialog(transfer: OwnTransferRow): void {
    editingTransfer.value = transfer;
    editDialogOpen.value = true;
}

function openIncomeEditDialog(operation: OperationRow): void {
    editingIncome.value = operation;
    incomeEditDialogOpen.value = true;
}

function openExpenseEditDialog(operation: OperationRow): void {
    editingExpense.value = operation;
    expenseEditDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t.operations.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.operations.title }}</h1>
            <div class="flex flex-wrap gap-2">
                <Button @click="incomeDialogOpen = true">
                    <Plus class="mr-2 size-4" />
                    {{ t.operations.add_income }}
                </Button>
                <Button @click="expenseDialogOpen = true">
                    <Plus class="mr-2 size-4" />
                    {{ t.operations.add_expense }}
                </Button>
                <Button
                    :disabled="accounts.length < 2"
                    :title="
                        accounts.length < 2
                            ? t.operations.transfer_requires_two_accounts
                            : undefined
                    "
                    @click="createDialogOpen = true"
                >
                    {{ t.operations.add_transfer }}
                </Button>
                <Button as-child>
                    <Link :href="CsvImportController.create.url()">
                        <Upload class="mr-2 size-4" />
                        {{ t.operations.import_title }}
                    </Link>
                </Button>
            </div>
        </div>

        <IndexEmptyState
            v-if="operations.total === 0"
            :title="t.operations.empty_title"
            :description="t.operations.empty_description"
        >
            <template #actions>
                <Button @click="incomeDialogOpen = true">
                    <Plus class="mr-2 size-4" />
                    {{ t.operations.add_income }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="CsvImportController.create.url()">
                        <Upload class="mr-2 size-4" />
                        {{ t.operations.import_title }}
                    </Link>
                </Button>
            </template>
        </IndexEmptyState>

        <template v-else>
            <ListSurface
                class="hidden md:block"
                :description="t.operations.list_description"
                :paginator="operations"
            >
                <div class="overflow-x-auto">
                    <table class="list-surface-table">
                        <thead>
                            <tr>
                                <th>{{ t.operations.date }}</th>
                                <th>{{ t.operations.description }}</th>
                                <th>{{ t.operations.category }}</th>
                                <th>{{ t.operations.account }}</th>
                                <th class="text-right">
                                    {{ t.operations.amount }}
                                </th>
                                <th class="text-right">
                                    {{ t.operations.actions }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="operation in operations.data"
                                :key="operation.id"
                            >
                                <td>
                                    <OperationsDateCell
                                        :date="operation.operation_date"
                                    />
                                </td>
                                <td>
                                    <OperationDescriptionCell
                                        :variant="
                                            operationTypeVariant(operation)
                                        "
                                        :description="operation.description"
                                        :counterparty="operation.counterparty"
                                        :raw-statement-description="
                                            operation.raw_statement_description
                                        "
                                    />
                                </td>
                                <td>
                                    <CategoryChip
                                        :name="operation.category_name"
                                    />
                                </td>
                                <td>
                                    <CompactAccountIdentity
                                        :name="operation.account_name"
                                        :type="operation.account_type"
                                        :bank="operation.account_bank"
                                        :mark_url="operation.account_mark_url"
                                    />
                                </td>
                                <td
                                    class="tabular-amount"
                                    :class="
                                        financialOperationAmountClass(
                                            operation.type,
                                            operation.amount_grosze,
                                        )
                                    "
                                >
                                    {{
                                        formatFinancialOperationAmount(
                                            operation.type,
                                            operation.amount_grosze,
                                        )
                                    }}
                                </td>
                                <td>
                                    <FinancialOperationActions
                                        :operation="operation"
                                        @edit="
                                            operation.type === 'income'
                                                ? openIncomeEditDialog(operation)
                                                : openExpenseEditDialog(
                                                      operation,
                                                  )
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </ListSurface>

            <ListSurface class="md:hidden" :paginator="operations">
                <div
                    v-for="operation in operations.data"
                    :key="operation.id"
                    class="flex flex-col gap-3 border-b border-border/60 py-3 transition-colors last:border-b-0 hover:bg-foreground/[0.03]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <OperationsDateCell
                            :date="operation.operation_date"
                        />
                        <p
                            class="shrink-0 font-medium tabular-nums"
                            :class="
                                financialOperationAmountClass(
                                    operation.type,
                                    operation.amount_grosze,
                                )
                            "
                        >
                            {{
                                formatFinancialOperationAmount(
                                    operation.type,
                                    operation.amount_grosze,
                                )
                            }}
                        </p>
                    </div>
                    <OperationDescriptionCell
                        :variant="operationTypeVariant(operation)"
                        :description="operation.description"
                        :counterparty="operation.counterparty"
                        :raw-statement-description="
                            operation.raw_statement_description
                        "
                    />
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <CategoryChip :name="operation.category_name" />
                        <CompactAccountIdentity
                            :name="operation.account_name"
                            :type="operation.account_type"
                            :bank="operation.account_bank"
                            :mark_url="operation.account_mark_url"
                        />
                    </div>
                    <FinancialOperationActions
                        :operation="operation"
                        @edit="
                            operation.type === 'income'
                                ? openIncomeEditDialog(operation)
                                : openExpenseEditDialog(operation)
                        "
                    />
                </div>
            </ListSurface>
        </template>

        <ListSurface
            v-if="ownTransfers.total > 0"
            class="hidden md:block"
            :title="t.operations.own_transfers"
            :paginator="ownTransfers"
        >
            <div class="overflow-x-auto">
                <table class="list-surface-table">
                    <thead>
                        <tr>
                            <th>{{ t.operations.date }}</th>
                            <th>{{ t.operations.description }}</th>
                            <th>{{ t.operations.category }}</th>
                            <th>{{ t.operations.accounts }}</th>
                            <th class="text-right">
                                {{ t.operations.amount }}
                            </th>
                            <th class="text-right">
                                {{ t.operations.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transfer in ownTransfers.data"
                            :key="transfer.id"
                        >
                            <td>
                                <OperationsDateCell
                                    :date="transfer.transfer_date"
                                />
                            </td>
                            <td>
                                <OperationDescriptionCell
                                    variant="transfer"
                                    :description="transfer.description"
                                    :raw-statement-description="
                                        transfer.raw_statement_description
                                    "
                                />
                            </td>
                            <td>
                                <span class="text-muted-foreground">—</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1">
                                    <CompactAccountIdentity
                                        :name="transfer.source_account_name"
                                        :type="transfer.source_account_type"
                                        :bank="transfer.source_account_bank"
                                        :mark_url="
                                            transfer.source_account_mark_url
                                        "
                                    />
                                    <span class="text-muted-foreground">→</span>
                                    <CompactAccountIdentity
                                        :name="transfer.destination_account_name"
                                        :type="transfer.destination_account_type"
                                        :bank="transfer.destination_account_bank"
                                        :mark_url="
                                            transfer.destination_account_mark_url
                                        "
                                    />
                                </div>
                            </td>
                            <td class="tabular-amount">
                                {{ formatMoney(transfer.amount_grosze) }}
                            </td>
                            <td>
                                <OwnTransferActions
                                    :transfer="transfer"
                                    @edit="openEditDialog(transfer)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </ListSurface>

        <ListSurface
            v-if="ownTransfers.total > 0"
            class="md:hidden"
            :title="t.operations.own_transfers"
            :paginator="ownTransfers"
        >
            <div
                v-for="transfer in ownTransfers.data"
                :key="transfer.id"
                class="flex flex-col gap-3 border-b border-border/60 py-3 transition-colors last:border-b-0 hover:bg-foreground/[0.03]"
            >
                <div class="flex items-start justify-between gap-3">
                    <OperationsDateCell :date="transfer.transfer_date" />
                    <p class="shrink-0 font-medium tabular-nums">
                        {{ formatMoney(transfer.amount_grosze) }}
                    </p>
                </div>
                <OperationDescriptionCell
                    variant="transfer"
                    :description="transfer.description"
                    :raw-statement-description="
                        transfer.raw_statement_description
                    "
                />
                <div class="flex flex-wrap items-center gap-1">
                    <CompactAccountIdentity
                        :name="transfer.source_account_name"
                        :type="transfer.source_account_type"
                        :bank="transfer.source_account_bank"
                        :mark_url="transfer.source_account_mark_url"
                    />
                    <span class="text-muted-foreground">→</span>
                    <CompactAccountIdentity
                        :name="transfer.destination_account_name"
                        :type="transfer.destination_account_type"
                        :bank="transfer.destination_account_bank"
                        :mark_url="transfer.destination_account_mark_url"
                    />
                </div>
                <OwnTransferActions
                    :transfer="transfer"
                    @edit="openEditDialog(transfer)"
                />
            </div>
        </ListSurface>
    </div>

    <IncomeFormDialog
        v-model:open="incomeDialogOpen"
        mode="create"
        :accounts="accounts"
        :income-categories="incomeCategories"
    />

    <IncomeFormDialog
        v-model:open="incomeEditDialogOpen"
        mode="edit"
        :operation="editingIncome"
        :accounts="accounts"
        :income-categories="incomeCategories"
    />

    <ExpenseFormDialog
        v-model:open="expenseDialogOpen"
        mode="create"
        :accounts="accounts"
        :expense-categories="expenseCategories"
    />

    <ExpenseFormDialog
        v-model:open="expenseEditDialogOpen"
        mode="edit"
        :operation="editingExpense"
        :accounts="accounts"
        :expense-categories="expenseCategories"
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
</template>
