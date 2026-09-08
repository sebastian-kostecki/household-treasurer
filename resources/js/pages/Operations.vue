<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import ExpenseFormDialog, {
    type ExpenseFormAccount,
    type ExpenseFormCategory,
    type ExpenseFormOperation,
} from '@/components/ExpenseFormDialog.vue';
import IconAction from '@/components/IconAction.vue';
import IncomeFormDialog, {
    type IncomeFormCategory,
    type IncomeFormOperation,
} from '@/components/IncomeFormDialog.vue';
import OwnTransferFormDialog, {
    type OwnTransferFormTransfer,
} from '@/components/OwnTransferFormDialog.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import PaginationLinks, {
    type PaginatedCollection,
} from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatMoney } from '@/lib/format';
import { operations as operationsRoute } from '@/routes';

type OperationRow = IncomeFormOperation &
    ExpenseFormOperation & {
    type: 'income' | 'expense';
    account_name: string;
    category_name: string;
};

type OwnTransferRow = OwnTransferFormTransfer & {
    source_account_name: string;
    destination_account_name: string;
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

function operationTypeLabel(operation: OperationRow): string {
    if (operation.type === 'income') {
        return t.operations.type_income;
    }

    if (operation.amount_grosze < 0) {
        return t.operations.type_refund;
    }

    return t.operations.type_expense;
}

function operationAmountClass(operation: OperationRow): string {
    if (operation.type === 'income' || operation.amount_grosze < 0) {
        return 'text-success';
    }

    return 'text-destructive';
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
                :title="t.operations.title"
                :description="t.operations.list_description"
                :paginator="operations"
            >
                <div class="overflow-x-auto">
                    <table class="list-surface-table">
                        <thead>
                            <tr>
                                <th>{{ t.operations.date }}</th>
                                <th>{{ t.operations.description }}</th>
                                <th>{{ t.operations.type }}</th>
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
                                    {{ formatDate(operation.operation_date) }}
                                </td>
                                <td>{{ operation.description }}</td>
                                <td>
                                    <Badge variant="secondary">
                                        {{ operationTypeLabel(operation) }}
                                    </Badge>
                                </td>
                                <td>{{ operation.account_name }}</td>
                                <td
                                    class="tabular-amount"
                                    :class="operationAmountClass(operation)"
                                >
                                    {{ formatMoney(operation.amount_grosze) }}
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <IconAction
                                            :icon="Pencil"
                                            :label="t.accounts.edit"
                                            @click="
                                                operation.type === 'income'
                                                    ? openIncomeEditDialog(operation)
                                                    : openExpenseEditDialog(operation)
                                            "
                                        />
                                        <Dialog>
                                            <IconAction
                                                as-dialog-trigger
                                                :icon="Trash2"
                                                :label="t.accounts.delete"
                                                destructive
                                            />
                                            <DialogContent>
                                                <Form
                                                    v-bind="
                                                        operation.type === 'income'
                                                            ? IncomeController.destroy.form(
                                                                  operation,
                                                              )
                                                            : ExpenseController.destroy.form(
                                                                  operation,
                                                              )
                                                    "
                                                    v-slot="{ processing }"
                                                >
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            {{
                                                                operation.type ===
                                                                'income'
                                                                    ? t.operations
                                                                          .delete_income_confirm_title
                                                                    : t.operations
                                                                          .delete_expense_confirm_title
                                                            }}
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            {{
                                                                operation.type ===
                                                                'income'
                                                                    ? t.operations
                                                                          .delete_income_confirm_body
                                                                    : t.operations
                                                                          .delete_expense_confirm_body
                                                            }}
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <DialogFooter class="gap-2">
                                                        <DialogClose as-child>
                                                            <Button
                                                                type="button"
                                                                variant="secondary"
                                                            >
                                                                {{
                                                                    t.accounts.cancel
                                                                }}
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            variant="destructive"
                                                            :disabled="processing"
                                                        >
                                                            {{ t.accounts.delete }}
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </ListSurface>

            <div class="space-y-3 md:hidden">
                <Card
                    v-for="operation in operations.data"
                    :key="operation.id"
                >
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">
                        {{ operation.description }}
                    </CardTitle>
                    <CardDescription>
                        {{ formatDate(operation.operation_date) }}
                        · {{ operation.account_name }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <Badge variant="secondary">
                            {{ operationTypeLabel(operation) }}
                        </Badge>
                        <span
                            class="font-medium tabular-nums"
                            :class="operationAmountClass(operation)"
                        >
                            {{ formatMoney(operation.amount_grosze) }}
                        </span>
                    </div>
                    <div class="flex gap-1">
                        <IconAction
                            :icon="Pencil"
                            :label="t.accounts.edit"
                            @click="
                                operation.type === 'income'
                                    ? openIncomeEditDialog(operation)
                                    : openExpenseEditDialog(operation)
                            "
                        />
                        <Dialog>
                            <IconAction
                                as-dialog-trigger
                                :icon="Trash2"
                                :label="t.accounts.delete"
                                destructive
                            />
                            <DialogContent>
                                <Form
                                    v-bind="
                                        operation.type === 'income'
                                            ? IncomeController.destroy.form(
                                                  operation,
                                              )
                                            : ExpenseController.destroy.form(
                                                  operation,
                                              )
                                    "
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            {{
                                                operation.type === 'income'
                                                    ? t.operations
                                                          .delete_income_confirm_title
                                                    : t.operations
                                                          .delete_expense_confirm_title
                                            }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{
                                                operation.type === 'income'
                                                    ? t.operations
                                                          .delete_income_confirm_body
                                                    : t.operations
                                                          .delete_expense_confirm_body
                                            }}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                            >
                                                {{ t.accounts.cancel }}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            :disabled="processing"
                                        >
                                            {{ t.accounts.delete }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardContent>
            </Card>
            <PaginationLinks :paginator="operations" />
            </div>
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
                                {{ formatDate(transfer.transfer_date) }}
                            </td>
                            <td>{{ transfer.description }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <Badge variant="secondary">
                                        {{ transfer.source_account_name }}
                                    </Badge>
                                    <span class="text-muted-foreground">→</span>
                                    <Badge variant="secondary">
                                        {{ transfer.destination_account_name }}
                                    </Badge>
                                </div>
                            </td>
                            <td class="tabular-amount">
                                {{ formatMoney(transfer.amount_grosze) }}
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <IconAction
                                        :icon="Pencil"
                                        :label="t.accounts.edit"
                                        @click="openEditDialog(transfer)"
                                    />
                                    <Dialog>
                                        <IconAction
                                            as-dialog-trigger
                                            :icon="Trash2"
                                            :label="t.accounts.delete"
                                            destructive
                                        />
                                        <DialogContent>
                                            <Form
                                                v-bind="
                                                    OwnTransferController.destroy.form(
                                                        transfer,
                                                    )
                                                "
                                                v-slot="{ processing }"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        {{
                                                            t.operations.delete_transfer_confirm_title
                                                        }}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{
                                                            t.operations.delete_transfer_confirm_body
                                                        }}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                        >
                                                            {{
                                                                t.accounts.cancel
                                                            }}
                                                        </Button>
                                                    </DialogClose>
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        :disabled="processing"
                                                    >
                                                        {{ t.accounts.delete }}
                                                    </Button>
                                                </DialogFooter>
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </ListSurface>

        <div v-if="ownTransfers.total > 0" class="space-y-3 md:hidden">
            <Card v-for="transfer in ownTransfers.data" :key="transfer.id">
                <CardHeader class="gap-3 pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                {{ transfer.description }}
                            </CardTitle>
                            <CardDescription>
                                {{ formatDate(transfer.transfer_date) }}
                            </CardDescription>
                        </div>
                        <p class="font-semibold tabular-nums">
                            {{ formatMoney(transfer.amount_grosze) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Badge variant="secondary">
                            {{ transfer.source_account_name }}
                        </Badge>
                        <span class="text-muted-foreground">→</span>
                        <Badge variant="secondary">
                            {{ transfer.destination_account_name }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="flex gap-1">
                    <IconAction
                        :icon="Pencil"
                        :label="t.accounts.edit"
                        @click="openEditDialog(transfer)"
                    />
                    <Dialog>
                        <IconAction
                            as-dialog-trigger
                            :icon="Trash2"
                            :label="t.accounts.delete"
                            destructive
                        />
                        <DialogContent>
                            <Form
                                v-bind="
                                    OwnTransferController.destroy.form(transfer)
                                "
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.operations.delete_transfer_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.operations.delete_transfer_confirm_body
                                        }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.accounts.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                    >
                                        {{ t.accounts.delete }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </CardContent>
            </Card>
            <PaginationLinks :paginator="ownTransfers" />
        </div>
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
