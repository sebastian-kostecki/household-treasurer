<script setup lang="ts">
import { computed } from 'vue';
import ColumnHeaderHint from '@/components/ColumnHeaderHint.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    amountMinorUnits: number;
    viewedMonthTiming: 'past' | 'current' | 'future';
}>();

const t = useTranslations();

const amountClass = computed(() =>
    props.amountMinorUnits < 0 ? 'text-destructive' : '',
);

const timingNote = computed(() => {
    if (props.viewedMonthTiming === 'future') {
        return t.budget.paced_pool_future_note;
    }

    if (props.viewedMonthTiming === 'past') {
        return t.budget.paced_pool_past_note;
    }

    return null;
});
</script>

<template>
    <div class="border-border/60 flex flex-col gap-1 border-t pt-4">
        <ColumnHeaderHint
            :label="t.budget.paced_pool_label"
            :hint="t.budget.paced_pool_hint"
        />
        <p class="text-2xl font-semibold tabular-nums" :class="amountClass">
            {{ formatMoney(amountMinorUnits) }}
        </p>
        <p v-if="timingNote" class="text-muted-foreground text-xs">
            {{ timingNote }}
        </p>
    </div>
</template>
