<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    planChangeDirection,
    planChangeToneClass,
} from '@/lib/plan-change-direction';

const props = withDefaults(
    defineProps<{
        reference: number;
        current: number;
        categoryType: 'income' | 'expense';
        muted?: boolean;
        increasedLabel: string;
        decreasedLabel: string;
    }>(),
    { muted: false },
);

const change = computed(() =>
    planChangeDirection(props.reference, props.current, props.categoryType),
);

const label = computed(() =>
    change.value?.direction === 'up'
        ? props.increasedLabel
        : props.decreasedLabel,
);

const toneClass = computed(() =>
    change.value === null
        ? ''
        : planChangeToneClass(change.value.tone, props.muted),
);
</script>

<template>
    <TooltipProvider v-if="change !== null" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="focus-visible:ring-ring inline-flex shrink-0 self-center rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                    :class="toneClass"
                    :aria-label="label"
                >
                    <ArrowUp
                        v-if="change.direction === 'up'"
                        class="size-3.5"
                        :stroke-width="1.7"
                        aria-hidden="true"
                    />
                    <ArrowDown
                        v-else
                        class="size-3.5"
                        :stroke-width="1.7"
                        aria-hidden="true"
                    />
                </button>
            </TooltipTrigger>
            <TooltipContent>
                {{ label }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
