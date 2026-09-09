<script setup lang="ts">
import CapitalMovementActions from '@/components/CapitalMovementActions.vue';
import type { CapitalMovementFormMovement } from '@/components/CapitalMovementFormDialog.vue';
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import OperationsDateCell from '@/components/OperationsDateCell.vue';
import type { PrefixedAccountIdentityFields } from '@/lib/account-identity';
import { formatMoney } from '@/lib/format';

export type CapitalMovementRowData = CapitalMovementFormMovement &
    PrefixedAccountIdentityFields<'account'> & {
        term_deposit_name: string;
    };

defineProps<{
    movement: CapitalMovementRowData;
}>();

const emit = defineEmits<{
    edit: [];
}>();
</script>

<template>
    <div
        class="operations-grid operations-grid-transfer border-border/60 hover:bg-foreground/[0.03] gap-y-3 border-b py-3 text-sm transition-colors last:border-b-0 md:gap-y-0"
    >
        <div class="operations-cell-date">
            <OperationsDateCell :date="movement.movement_date" />
        </div>

        <div class="operations-cell-description">
            <OperationDescriptionCell
                variant="transfer"
                :description="movement.description"
            />
        </div>

        <div class="operations-cell-account flex items-center gap-1">
            <template v-if="movement.direction === 'to_term_deposit'">
                <CompactAccountIdentity
                    class="min-w-0 flex-1"
                    mark-size="md"
                    :name="movement.account_name"
                    :type="movement.account_type"
                    :bank="movement.account_bank"
                    :mark_url="movement.account_mark_url"
                />
                <span class="text-muted-foreground shrink-0">→</span>
                <span class="min-w-0 flex-1 truncate font-medium">
                    {{ movement.term_deposit_name }}
                </span>
            </template>
            <template v-else>
                <span class="min-w-0 flex-1 truncate font-medium">
                    {{ movement.term_deposit_name }}
                </span>
                <span class="text-muted-foreground shrink-0">→</span>
                <CompactAccountIdentity
                    class="min-w-0 flex-1"
                    mark-size="md"
                    :name="movement.account_name"
                    :type="movement.account_type"
                    :bank="movement.account_bank"
                    :mark_url="movement.account_mark_url"
                />
            </template>
        </div>

        <p class="operations-cell-amount">
            {{ formatMoney(movement.amount_minor_units) }}
        </p>

        <CapitalMovementActions
            class="operations-cell-actions"
            :movement="movement"
            @edit="emit('edit')"
        />
    </div>
</template>
