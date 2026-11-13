<script setup lang="ts">
import CategoryChip from '@/components/CategoryChip.vue';
import type { ExpenseFormOperation } from '@/components/ExpenseFormDialog.vue';
import FinancialOperationActions from '@/components/FinancialOperationActions.vue';
import type { IncomeFormOperation } from '@/components/IncomeFormDialog.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PrefixedAccountIdentityFields } from '@/lib/account-identity';
import {
    financialOperationAmountClass,
    formatFinancialOperationAmount,
} from '@/lib/format';
import { computed } from 'vue';

export type FinancialOperationRowData = IncomeFormOperation &
    ExpenseFormOperation & {
        type: 'income' | 'expense';
        category_label: string;
        category_icon: string;
        category_color: string;
        category_archived: boolean;
        /** An operation of a Sheet Account: only its Category changes (ADR-0089). */
        sheet_operation: boolean;
        counterparty: string | null;
        raw_statement_description: string | null;
    } & PrefixedAccountIdentityFields<'account'>;

const { operation } = defineProps<{
    operation: FinancialOperationRowData;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();

const typeVariant = computed<'income' | 'expense' | 'refund'>(() => {
    if (operation.type === 'income') {
        return 'income';
    }

    if (operation.amount_minor_units < 0) {
        return 'refund';
    }

    return 'expense';
});

const detail = computed(() =>
    typeVariant.value === 'refund'
        ? `${t.operations.type_refund} · ${operation.account_name}`
        : operation.account_name,
);
</script>

<template>
    <div class="operations-grid operations-row">
        <div class="operations-cell-description">
            <OperationDescriptionCell
                :variant="typeVariant"
                :description="operation.description"
                :counterparty="operation.counterparty"
                :detail="detail"
                :raw-statement-description="operation.raw_statement_description"
            />
        </div>

        <div class="operations-cell-category">
            <CategoryChip
                :name="operation.category_label"
                :icon="operation.category_icon"
                :color="operation.category_color"
            />
        </div>

        <p
            class="operations-cell-amount"
            :class="
                financialOperationAmountClass(
                    operation.type,
                    operation.amount_minor_units,
                )
            "
        >
            {{
                formatFinancialOperationAmount(
                    operation.type,
                    operation.amount_minor_units,
                )
            }}
        </p>

        <FinancialOperationActions
            class="operations-cell-actions"
            :operation="operation"
            @edit="emit('edit')"
        />
    </div>
</template>
