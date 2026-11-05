<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type StatPanelMetric = {
    label: string;
    /** Already formatted (money through `formatMoney`). */
    value: string;
    /** Optional note under the value. */
    caption?: string;
    /** Value colour class, e.g. from `amountToneClass`. */
    toneClass?: HTMLAttributes['class'];
};

defineProps<{
    /** 2 to 4 metrics; the first one is the largest. */
    metrics: StatPanelMetric[];
}>();
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <div class="stat-panel">
            <div
                v-for="(metric, index) in metrics"
                :key="metric.label"
                class="stat-panel-metric"
            >
                <p class="label-caps text-muted-foreground">
                    {{ metric.label }}
                </p>
                <p
                    :class="
                        cn(index === 0 ? 'kpi-lg' : 'kpi', metric.toneClass)
                    "
                >
                    {{ metric.value }}
                </p>
                <p v-if="metric.caption" class="meta text-muted-foreground">
                    {{ metric.caption }}
                </p>
            </div>
        </div>
    </Card>
</template>
