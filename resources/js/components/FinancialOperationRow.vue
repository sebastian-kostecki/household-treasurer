<script setup lang="ts">
import CategoryChip from '@/components/CategoryChip.vue';
import type { ExpenseFormOperation } from '@/components/ExpenseFormDialog.vue';
import FinancialOperationActions from '@/components/FinancialOperationActions.vue';
import type { IncomeFormOperation } from '@/components/IncomeFormDialog.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import { useTranslations } from '@/composables/useTranslations';
import type {
    AccountIdentityFields,
    PrefixedAccountIdentityFields,
} from '@/lib/account-identity';
import {
    financialOperationAmountClass,
    formatFinancialOperationAmount,
} from '@/lib/format';
import { computed } from 'vue';

export type FinancialOperationRowData = IncomeFormOperation &
    ExpenseFormOperation & {
        type: 'income' | 'expense';
        category_name: string;
        category_icon: string;
        category_color: string;
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
    typeVariant.value === 'refund' ? t.operations.type_refund : null,
);

const account = computed<AccountIdentityFields>(() => ({
    name: operation.account_name,
    type: operation.account_type,
    bank: operation.account_bank,
    mark_url: operation.account_mark_url,
}));
</script>

<template>
    <div class="operations-grid operations-row">
        <div class="operations-cell-description">
            <OperationDescriptionCell
                :variant="typeVariant"
                :description="operation.description"
                :counterparty="operation.counterparty"
                :detail="detail"
                :account="account"
                :raw-statement-description="operation.raw_statement_description"
            />
        </div>

        <div class="operations-cell-category">
            <CategoryChip
                :name="operation.category_name"
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
