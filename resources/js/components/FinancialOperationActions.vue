<script setup lang="ts">
import { computed, inject } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import OperationRowMenu from '@/components/OperationRowMenu.vue';
import { useTranslations } from '@/composables/useTranslations';
import { OPERATIONS_RETURN_QUERY } from '@/lib/operations-list-query';

type OperationRow = {
    id: number;
    type: 'income' | 'expense';
    sheet_operation: boolean;
};

const { operation } = defineProps<{
    operation: OperationRow;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();
const operationsReturnQuery = inject(OPERATIONS_RETURN_QUERY, undefined);
const writeOptions = computed(() =>
    operationsReturnQuery ? { query: operationsReturnQuery.value } : undefined,
);

const isIncome = computed(() => operation.type === 'income');
</script>

<template>
    <OperationRowMenu
        :destroy-form="
            isIncome
                ? IncomeController.destroy.form(operation, writeOptions)
                : ExpenseController.destroy.form(operation, writeOptions)
        "
        :delete-title="
            isIncome
                ? t.operations.delete_income_confirm_title
                : t.operations.delete_expense_confirm_title
        "
        :delete-description="
            isIncome
                ? t.operations.delete_income_confirm_body
                : t.operations.delete_expense_confirm_body
        "
        :edit-label="
            operation.sheet_operation ? t.operations.change_category : undefined
        "
        @edit="emit('edit')"
    />
</template>
