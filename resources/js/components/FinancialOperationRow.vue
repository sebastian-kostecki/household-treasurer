<script setup lang="ts">
import CategoryChip from '@/components/CategoryChip.vue';
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import type { ExpenseFormOperation } from '@/components/ExpenseFormDialog.vue';
import FinancialOperationActions from '@/components/FinancialOperationActions.vue';
import type { IncomeFormOperation } from '@/components/IncomeFormDialog.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import OperationsDateCell from '@/components/OperationsDateCell.vue';
import type { PrefixedAccountIdentityFields } from '@/lib/account-identity';
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

const typeVariant = computed<'income' | 'expense' | 'refund'>(() => {
    if (operation.type === 'income') {
        return 'income';
    }

    if (operation.amount_minor_units < 0) {
        return 'refund';
    }

    return 'expense';
});
</script>

<template>
    <div
        class="operations-grid border-border/60 hover:bg-foreground/[0.03] gap-y-3 border-b py-3 text-sm transition-colors last:border-b-0 md:gap-y-0"
    >
        <div class="operations-cell-date">
            <OperationsDateCell :date="operation.operation_date" />
        </div>

        <div class="operations-cell-description">
            <OperationDescriptionCell
                :variant="typeVariant"
                :description="operation.description"
                :counterparty="operation.counterparty"
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

        <CompactAccountIdentity
            class="operations-cell-account"
            mark-size="md"
            :name="operation.account_name"
            :type="operation.account_type"
            :bank="operation.account_bank"
            :mark_url="operation.account_mark_url"
        />

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
