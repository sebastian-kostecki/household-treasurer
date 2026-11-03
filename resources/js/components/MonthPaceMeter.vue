<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { monthElapsedPercent } from '@/lib/month-pace-calculator';

const props = defineProps<{
    percent: number | null;
    isAhead: boolean;
    elapsedDay: number;
    daysInMonth: number;
}>();

const t = useTranslations();

const elapsedPercent = computed(() =>
    monthElapsedPercent(props.elapsedDay, props.daysInMonth),
);

const markerLeft = computed(() =>
    props.daysInMonth > 0 ? (100 * props.elapsedDay) / props.daysInMonth : 0,
);

const fillWidth = computed(() =>
    props.percent === null ? 0 : Math.min(Math.max(props.percent, 0), 100),
);

function fillDays(text: string): string {
    return text
        .replace(':days', String(props.daysInMonth))
        .replace(':day', String(props.elapsedDay))
        .replace(':elapsed', String(elapsedPercent.value));
}

/** The reading is split around the pace so the number alone can carry the colour. */
const readingParts = computed(() => {
    const [before, ...rest] = t.month_plan.pace_reading.split(':pace');

    return {
        before: fillDays(before),
        after: fillDays(rest.join(':pace')),
    };
});
</script>

<template>
    <div class="grid gap-2">
        <template v-if="percent !== null">
            <div
                class="bg-muted relative h-1.5 overflow-visible rounded-full"
                aria-hidden="true"
            >
                <div
                    class="h-full rounded-full transition-all"
                    :class="isAhead ? 'bg-warning' : 'bg-primary'"
                    :style="{ width: `${fillWidth}%` }"
                />
                <div
                    class="bg-foreground ring-background pointer-events-none absolute top-1/2 z-10 h-3 w-0.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-1"
                    :style="{ left: `${markerLeft}%` }"
                />
            </div>
            <p class="text-sm tabular-nums">
                {{ readingParts.before
                }}<span :class="isAhead ? 'text-warning' : ''">{{
                    percent
                }}</span
                >{{ readingParts.after }}
            </p>
            <p v-if="isAhead" class="text-warning text-xs">
                {{ t.month_plan.pace_ahead }}
            </p>
        </template>
        <template v-else>
            <p class="text-muted-foreground text-xs">
                {{ t.month_plan.pace_none }}
            </p>
            <p class="text-muted-foreground text-xs tabular-nums">
                {{ fillDays(t.month_plan.pace_days) }}
            </p>
        </template>
    </div>
</template>
