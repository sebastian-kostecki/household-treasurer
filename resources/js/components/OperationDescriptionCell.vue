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
    detail = null,
    rawStatementDescription = null,
    rawStatementDescriptions = [],
} = defineProps<{
    variant: 'income' | 'expense' | 'refund' | 'transfer';
    description: string;
    counterparty?: string | null;
    detail?: string | null;
    rawStatementDescription?: string | null;
    rawStatementDescriptions?: string[];
}>();

const t = useTranslations();
const primaryLine = counterparty?.trim() ? counterparty : description;
const secondaryLine =
    [counterparty?.trim() ? description : null, detail]
        .filter((part): part is string => !!part && part.trim() !== '')
        .join(' · ') || null;
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
                        <p
                            v-if="secondaryLine"
                            class="meta text-muted-foreground truncate"
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
                <p class="text-xs whitespace-pre-wrap">{{ tooltipLabel }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <div v-else class="flex min-w-0 items-center gap-2.5">
        <OperationTypeTile :variant="variant" />
        <div class="min-w-0">
            <p class="truncate font-medium">{{ primaryLine }}</p>
            <p v-if="secondaryLine" class="meta text-muted-foreground truncate">
                {{ secondaryLine }}
            </p>
        </div>
    </div>
</template>
