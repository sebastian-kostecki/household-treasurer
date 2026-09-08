<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
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
import IncomeFormDialog, {
    type IncomeFormCategory,
    type IncomeFormOperation,
} from '@/components/IncomeFormDialog.vue';
import OwnTransferFormDialog, {
    type OwnTransferFormTransfer,
} from '@/components/OwnTransferFormDialog.vue';
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
    DialogTrigger,
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
    operations: operationRows,
    ownTransfers,
    accounts,
    incomeCategories,
    expenseCategories,
} = defineProps<{
    operations: OperationRow[];
    ownTransfers: OwnTransferRow[];
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

        <Card class="hidden md:block">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.title }}
                </CardTitle>
                <CardDescription>
                    {{
                        operationRows.length === 0
                            ? t.operations.empty
                            : t.operations.list_description
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 font-medium">
                                {{ t.operations.date }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.description }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.type }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.account }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.amount }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="operationRows.length === 0">
                            <td
                                colspan="6"
                                class="text-muted-foreground py-6 text-center"
                            >
                                {{ t.operations.empty }}
                            </td>
                        </tr>
                        <tr
                            v-for="operation in operationRows"
                            :key="operation.id"
                            class="border-b"
                        >
                            <td class="py-3">
                                {{ formatDate(operation.operation_date) }}
                            </td>
                            <td class="py-3">{{ operation.description }}</td>
                            <td class="py-3">
                                <Badge variant="secondary">
                                    {{ operationTypeLabel(operation) }}
                                </Badge>
                            </td>
                            <td class="py-3">{{ operation.account_name }}</td>
                            <td
                                class="py-3 text-right font-medium"
                                :class="operationAmountClass(operation)"
                            >
                                {{ formatMoney(operation.amount_grosze) }}
                            </td>
                            <td class="py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            operation.type === 'income'
                                                ? openIncomeEditDialog(operation)
                                                : openExpenseEditDialog(operation)
                                        "
                                    >
                                        {{ t.accounts.edit }}
                                    </Button>
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                            >
                                                {{ t.accounts.delete }}
                                            </Button>
                                        </DialogTrigger>
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
            </CardContent>
        </Card>

        <div class="space-y-3 md:hidden">
            <Card v-if="operationRows.length === 0">
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.operations.empty }}
                    </CardDescription>
                </CardHeader>
            </Card>
            <Card
                v-for="operation in operationRows"
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
                            class="font-medium"
                            :class="operationAmountClass(operation)"
                        >
                            {{ formatMoney(operation.amount_grosze) }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="
                                operation.type === 'income'
                                    ? openIncomeEditDialog(operation)
                                    : openExpenseEditDialog(operation)
                            "
                        >
                            {{ t.accounts.edit }}
                        </Button>
                        <Dialog>
                            <DialogTrigger as-child>
                                <Button variant="destructive" size="sm">
                                    {{ t.accounts.delete }}
                                </Button>
                            </DialogTrigger>
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
        </div>

        <Card v-if="ownTransfers.length === 0">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.own_transfers }}
                </CardTitle>
                <CardDescription>{{ t.operations.empty }}</CardDescription>
            </CardHeader>
        </Card>

        <Card v-else class="hidden md:block">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.own_transfers }}
                </CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 font-medium">
                                {{ t.operations.date }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.description }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.accounts }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.amount }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transfer in ownTransfers"
                            :key="transfer.id"
                            class="border-b last:border-b-0"
                        >
                            <td class="py-3">
                                {{ formatDate(transfer.transfer_date) }}
                            </td>
                            <td class="py-3">{{ transfer.description }}</td>
                            <td class="py-3">
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
                            <td class="py-3 text-right font-medium">
                                {{ formatMoney(transfer.amount_grosze) }}
                            </td>
                            <td class="py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(transfer)"
                                    >
                                        {{ t.accounts.edit }}
                                    </Button>
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                            >
                                                {{ t.accounts.delete }}
                                            </Button>
                                        </DialogTrigger>
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
            </CardContent>
        </Card>

        <div v-if="ownTransfers.length > 0" class="space-y-3 md:hidden">
            <Card v-for="transfer in ownTransfers" :key="transfer.id">
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
                        <p class="font-semibold">
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
                <CardContent class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openEditDialog(transfer)"
                    >
                        {{ t.accounts.edit }}
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive" size="sm">
                                {{ t.accounts.delete }}
                            </Button>
                        </DialogTrigger>
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
