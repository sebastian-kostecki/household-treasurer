<script setup lang="ts">
import { Info } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    label: string;
    hint: string;
}>();
</script>

<template>
    <span class="inline-flex items-center justify-end gap-1">
        <span>{{ label }}</span>
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground inline-flex size-4 shrink-0 items-center justify-center rounded-sm"
                        :aria-label="hint"
                    >
                        <Info class="size-3" aria-hidden="true" />
                    </button>
                </TooltipTrigger>
                <TooltipContent
                    class="max-w-xs text-left tracking-normal normal-case"
                >
                    {{ hint }}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </span>
</template>
