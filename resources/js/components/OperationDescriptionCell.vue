<script setup lang="ts">
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import OperationTypeTile from '@/components/OperationTypeTile.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountIdentityFields } from '@/lib/account-identity';

const {
    variant,
    description,
    counterparty = null,
    detail = null,
    account = null,
    rawStatementDescription = null,
    rawStatementDescriptions = [],
} = defineProps<{
    variant: 'income' | 'expense' | 'refund' | 'transfer';
    description: string;
    counterparty?: string | null;
    detail?: string | null;
    account?: AccountIdentityFields | null;
    rawStatementDescription?: string | null;
    rawStatementDescriptions?: string[];
}>();

const t = useTranslations();
const primaryLine = counterparty?.trim() ? counterparty : description;
const secondaryLine =
    [counterparty?.trim() ? description : null, detail]
        .filter((part): part is string => !!part && part.trim() !== '')
        .join(' · ') || null;
const hasMetaLine = secondaryLine !== null || account !== null;
const tooltipDescriptions =
    rawStatementDescriptions.length > 0
        ? rawStatementDescriptions
        : rawStatementDescription !== null && rawStatementDescription !== ''
          ? [rawStatementDescription]
          : [];
const tooltipLabel = tooltipDescriptions.join('\n\n');
</script>

<template>
    <TooltipProvider
        v-if="tooltipDescriptions.length > 0"
        :delay-duration="200"
    >
        <Tooltip>
            <TooltipTrigger as-child>
                <div
                    class="operations-raw-trigger flex min-w-0 items-center gap-2.5"
                    tabindex="0"
                    role="group"
                    :aria-label="primaryLine"
                >
                    <OperationTypeTile :variant="variant" />
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ primaryLine }}</p>
                        <div
                            v-if="hasMetaLine"
                            class="meta text-muted-foreground flex min-w-0 items-center gap-1"
                        >
                            <span v-if="secondaryLine" class="truncate">{{ secondaryLine }}</span>
                            <span v-if="secondaryLine && account" aria-hidden="true">·</span>
                            <CompactAccountIdentity
                                v-if="account"
                                class="min-w-0"
                                mark-size="xs"
                                :name="account.name"
                                :type="account.type"
                                :bank="account.bank"
                                :mark_url="account.mark_url"
                            />
                        </div>
                    </div>
                </div>
            </TooltipTrigger>
            <TooltipContent class="max-w-sm">
                <p class="text-xs font-medium">
                    {{ t.operations.raw_statement_description }}
                </p>
                <p class="text-xs whitespace-pre-wrap">{{ tooltipLabel }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <div v-else class="flex min-w-0 items-center gap-2.5">
        <OperationTypeTile :variant="variant" />
        <div class="min-w-0">
            <p class="truncate font-medium">{{ primaryLine }}</p>
            <div
                v-if="hasMetaLine"
                class="meta text-muted-foreground flex min-w-0 items-center gap-1"
            >
                <span v-if="secondaryLine" class="truncate">{{ secondaryLine }}</span>
                <span v-if="secondaryLine && account" aria-hidden="true">·</span>
                <CompactAccountIdentity
                    v-if="account"
                    class="min-w-0"
                    mark-size="xs"
                    :name="account.name"
                    :type="account.type"
                    :bank="account.bank"
                    :mark_url="account.mark_url"
                />
            </div>
        </div>
    </div>
</template>
