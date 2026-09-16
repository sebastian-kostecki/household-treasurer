<script setup lang="ts">
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { planChanged } from '@/lib/dashboard-categories';

const props = defineProps<{
    category: {
        initial_plan_minor_units: number;
        current_plan_minor_units: number;
    };
    label: string;
}>();

const visible = computed(() => planChanged(props.category));
</script>

<template>
    <TooltipProvider v-if="visible" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex shrink-0 rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                    :aria-label="label"
                >
                    <Pencil
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
