<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import type { ReconciliationPreviewState } from '@/composables/useBalanceReconciliationPreview';
import { useTranslations } from '@/composables/useTranslations';
import {
    reconciliationDifference,
    reconciliationKindLabel,
    reconciliationOperationsHeading,
    reconciliationTruncationNote,
} from '@/lib/balance-reconciliation';
import { formatDate, formatMoney, formatSignedMoneyAmount } from '@/lib/format';

const props = defineProps<{
    state: ReconciliationPreviewState;
    /** The bank balance typed in the dialog; null while the field is not a readable amount. */
    bankBalanceMinorUnits: number | null;
}>();

const t = useTranslations();

const preview = computed(() =>
    props.state.status === 'loaded' ? props.state.preview : null,
);

const difference = computed(() =>
    preview.value === null || props.bankBalanceMinorUnits === null
        ? null
        : reconciliationDifference(
              props.bankBalanceMinorUnits,
              preview.value.balance_minor_units,
          ),
);

const existingNote = computed(() =>
    preview.value?.existing_bank_balance_minor_units == null
        ? null
        : t.accounts.reconcile_existing.replace(
              ':amount',
              formatMoney(preview.value.existing_bank_balance_minor_units),
          ),
);
</script>

<template>
    <div class="grid gap-3" aria-live="polite">
        <p
            v-if="state.status === 'loading'"
            class="text-muted-foreground flex items-center gap-2 text-sm"
        >
            <Spinner />
            {{ t.accounts.reconcile_loading }}
        </p>

        <InputError
            v-else-if="state.status === 'error'"
            :message="state.message"
        />

        <template v-else-if="preview !== null">
            <div class="form-preview" role="group">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="form-preview-label">
                        {{ t.accounts.reconcile_app_balance }}
                        ({{ formatDate(preview.reconciled_on) }})
                    </span>
                    <span class="form-preview-value">{{
                        formatMoney(preview.balance_minor_units)
                    }}</span>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <span class="form-preview-label">
                        {{ t.accounts.reconcile_difference }}
                    </span>
                    <span class="flex items-baseline gap-2">
                        <span
                            v-if="difference !== null"
                            class="form-preview-value"
                        >
                            {{ formatMoney(difference) }}
                        </span>
                        <span v-else class="form-preview-value">—</span>
                        <Badge
                            v-if="difference !== null"
                            :variant="difference === 0 ? 'ok' : 'warning'"
                        >
                            {{
                                difference === 0
                                    ? t.accounts.reconcile_agrees
                                    : t.accounts.reconcile_disagrees
                            }}
                        </Badge>
                    </span>
                </div>
                <p
                    v-if="difference !== null && difference !== 0"
                    class="form-preview-hint"
                >
                    {{ t.accounts.reconcile_difference_hint }}
                </p>
                <p
                    v-if="existingNote !== null"
                    role="status"
                    class="form-preview-warning"
                >
                    {{ existingNote }}
                </p>
            </div>

            <h3 class="section-title">
                {{ reconciliationOperationsHeading(preview, t.accounts) }}
            </h3>

            <p
                v-if="preview.operations.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ t.accounts.reconcile_operations_empty }}
            </p>

            <ul
                v-else
                class="reconciliation-operations"
                tabindex="0"
                :aria-label="
                    reconciliationOperationsHeading(preview, t.accounts)
                "
            >
                <li
                    v-for="operation in preview.operations"
                    :key="operation.key"
                    class="reconciliation-operation-row"
                >
                    <span class="meta">
                        {{ formatDate(operation.date) }}
                    </span>
                    <span
                        class="reconciliation-operation-text"
                        :title="operation.description"
                    >
                        <span class="meta text-muted-foreground">
                            {{
                                reconciliationKindLabel(
                                    operation.kind,
                                    t.accounts,
                                )
                            }}
                        </span>
                        {{ operation.description }}
                    </span>
                    <span class="reconciliation-operation-amount amount">
                        {{
                            formatSignedMoneyAmount(
                                operation.amount_minor_units,
                            )
                        }}
                    </span>
                </li>
            </ul>

            <p class="meta text-muted-foreground">
                <template
                    v-if="reconciliationTruncationNote(preview, t.accounts)"
                >
                    {{ reconciliationTruncationNote(preview, t.accounts) }}
                    ·
                </template>
                {{ t.common.amounts_in_zl }}
            </p>
        </template>
    </div>
</template>
