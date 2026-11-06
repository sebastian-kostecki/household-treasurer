<script setup lang="ts">
import { type HTMLAttributes, useId } from 'vue';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type StatPanelMetric = {
    /**
     * Names the metric's `label-extra-<id>` and `footer-<id>` slots and is
     * the `v-for` key; the label is the key when it is missing.
     */
    id?: string;
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

const panelId = useId();
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <div class="stat-panel">
            <div
                v-for="(metric, index) in metrics"
                :key="metric.id ?? metric.label"
                class="stat-panel-metric"
                role="group"
                :aria-labelledby="`${panelId}-${index}`"
            >
                <p
                    :id="`${panelId}-${index}`"
                    class="label-caps text-muted-foreground"
                >
                    {{ metric.label }}
                    <slot v-if="metric.id" :name="`label-extra-${metric.id}`" />
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
                <slot v-if="metric.id" :name="`footer-${metric.id}`" />
            </div>
        </div>
    </Card>
</template>
