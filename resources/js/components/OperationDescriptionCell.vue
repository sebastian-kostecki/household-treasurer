<script setup lang="ts">
import OperationTypeTile from '@/components/OperationTypeTile.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';

const {
    variant,
    description,
    counterparty = null,
    rawStatementDescription = null,
    rawStatementDescriptions = [],
} = defineProps<{
    variant: 'income' | 'expense' | 'refund' | 'transfer';
    description: string;
    counterparty?: string | null;
    rawStatementDescription?: string | null;
    rawStatementDescriptions?: string[];
}>();

const t = useTranslations();
const primaryLine = counterparty?.trim() ? counterparty : description;
const secondaryLine = counterparty?.trim() ? description : null;
const tooltipDescriptions = rawStatementDescriptions.length > 0
    ? rawStatementDescriptions
    : rawStatementDescription !== null && rawStatementDescription !== ''
        ? [rawStatementDescription]
        : [];
const tooltipLabel = tooltipDescriptions.join('\n\n');
</script>

<template>
    <TooltipProvider v-if="tooltipDescriptions.length > 0" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <div
                    class="flex min-w-0 items-stretch gap-2.5"
                    :aria-label="`${primaryLine}. ${t.operations.raw_statement_description}: ${tooltipLabel}`"
                >
                    <OperationTypeTile :variant="variant" />
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ primaryLine }}</p>
                        <p
                            v-if="secondaryLine"
                            class="truncate text-muted-foreground text-sm"
                        >
                            {{ secondaryLine }}
                        </p>
                    </div>
                </div>
            </TooltipTrigger>
            <TooltipContent class="max-w-sm">
                <p class="text-xs font-medium">
                    {{ t.operations.raw_statement_description }}
                </p>
                <p class="whitespace-pre-wrap text-xs">{{ tooltipLabel }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <div v-else class="flex min-w-0 items-stretch gap-2.5">
        <OperationTypeTile :variant="variant" />
        <div class="min-w-0">
            <p class="truncate font-medium">{{ primaryLine }}</p>
            <p
                v-if="secondaryLine"
                class="truncate text-muted-foreground text-sm"
            >
                {{ secondaryLine }}
            </p>
        </div>
    </div>
</template>
