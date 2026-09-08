<script setup lang="ts">
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import OperationsDateCell from '@/components/OperationsDateCell.vue';
import OwnTransferActions from '@/components/OwnTransferActions.vue';
import type { OwnTransferFormTransfer } from '@/components/OwnTransferFormDialog.vue';
import type { PrefixedAccountIdentityFields } from '@/lib/account-identity';
import { formatMoney } from '@/lib/format';

export type OwnTransferRowData = OwnTransferFormTransfer &
    PrefixedAccountIdentityFields<'source_account'> &
    PrefixedAccountIdentityFields<'destination_account'> & {
        raw_statement_description: string | null;
    };

defineProps<{
    transfer: OwnTransferRowData;
}>();

const emit = defineEmits<{
    edit: [];
}>();
</script>

<template>
    <div
        class="operations-grid operations-grid-transfer gap-y-3 border-b border-border/60 py-3 text-sm transition-colors last:border-b-0 hover:bg-foreground/[0.03] md:gap-y-0"
    >
        <div class="operations-cell-date">
            <OperationsDateCell :date="transfer.transfer_date" />
        </div>

        <div class="operations-cell-description">
            <OperationDescriptionCell
                variant="transfer"
                :description="transfer.description"
                :raw-statement-description="
                    transfer.raw_statement_description
                "
            />
        </div>

        <div class="operations-cell-account flex items-center gap-1">
            <CompactAccountIdentity
                class="min-w-0 flex-1"
                :name="transfer.source_account_name"
                :type="transfer.source_account_type"
                :bank="transfer.source_account_bank"
                :mark_url="transfer.source_account_mark_url"
            />
            <span class="text-muted-foreground shrink-0">→</span>
            <CompactAccountIdentity
                class="min-w-0 flex-1"
                :name="transfer.destination_account_name"
                :type="transfer.destination_account_type"
                :bank="transfer.destination_account_bank"
                :mark_url="transfer.destination_account_mark_url"
            />
        </div>

        <p class="operations-cell-amount">
            {{ formatMoney(transfer.amount_grosze) }}
        </p>

        <OwnTransferActions
            class="operations-cell-actions"
            :transfer="transfer"
            @edit="emit('edit')"
        />
    </div>
</template>
