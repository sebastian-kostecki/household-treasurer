<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

withDefaults(
    defineProps<{
        title: string;
        /** Plan state badge (ADR-0042); null for a month without a plan. */
        badge: {
            variant: 'brand' | 'warning' | 'outline';
            label: string;
        } | null;
        /** One sentence of state under the title; null hides it. */
        hint: string | null;
        /** `h1` on the page; the kitchen sink renders it under its own `h1`. */
        headingLevel?: 'h1' | 'h3';
    }>(),
    { headingLevel: 'h1' },
);
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <component :is="headingLevel" class="page-title">
                    {{ title }}
                </component>
                <Badge v-if="badge" :variant="badge.variant">
                    {{ badge.label }}
                </Badge>
            </div>
            <p v-if="hint" class="meta text-muted-foreground">
                {{ hint }}
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center justify-end gap-3"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
