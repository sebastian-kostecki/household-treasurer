<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const {
    icon,
    label,
    destructive = false,
    asDialogTrigger = false,
} = defineProps<{
    icon: LucideIcon;
    label: string;
    destructive?: boolean;
    asDialogTrigger?: boolean;
}>();

const buttonClass = computed(() =>
    cn(
        destructive &&
            'text-destructive hover:bg-destructive/10 hover:text-destructive',
    ),
);
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <DialogTrigger v-if="asDialogTrigger" as-child>
                    <Button
                        v-bind="$attrs"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="label"
                        :class="buttonClass"
                    >
                        <component :is="icon" aria-hidden="true" />
                    </Button>
                </DialogTrigger>
                <Button
                    v-else
                    v-bind="$attrs"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="label"
                    :class="buttonClass"
                >
                    <component :is="icon" aria-hidden="true" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {{ label }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
