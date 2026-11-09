<script setup lang="ts">
import { ArrowLeftToLine, Equal } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    state: 'offer' | 'raised';
    offerLabel: string;
    raisedLabel: string;
}>();

const emit = defineEmits<{
    match: [];
}>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    v-if="state === 'offer'"
                    type="button"
                    class="month-plan-match-actual text-muted-foreground hover:text-foreground focus-visible:text-foreground focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none"
                    :aria-label="offerLabel"
                    @click="emit('match')"
                >
                    <ArrowLeftToLine
                        class="size-3.5"
                        :stroke-width="1.7"
                        aria-hidden="true"
                    />
                </button>
                <span
                    v-else
                    role="img"
                    tabindex="0"
                    class="text-muted-foreground focus-visible:ring-ring inline-flex shrink-0 self-center rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                    :aria-label="raisedLabel"
                >
                    <Equal
                        class="size-3.5"
                        :stroke-width="1.7"
                        aria-hidden="true"
                    />
                </span>
            </TooltipTrigger>
            <TooltipContent>
                {{ state === 'offer' ? offerLabel : raisedLabel }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
