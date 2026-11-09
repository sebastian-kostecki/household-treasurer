<script setup lang="ts">
import { computed } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import OperationRowMenu from '@/components/OperationRowMenu.vue';
import { useTranslations } from '@/composables/useTranslations';

type OperationRow = {
    id: number;
    type: 'income' | 'expense';
};

const { operation } = defineProps<{
    operation: OperationRow;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();

const isIncome = computed(() => operation.type === 'income');
</script>

<template>
    <OperationRowMenu
        :destroy-form="
            isIncome
                ? IncomeController.destroy.form(operation)
                : ExpenseController.destroy.form(operation)
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
        @edit="emit('edit')"
    />
</template>
