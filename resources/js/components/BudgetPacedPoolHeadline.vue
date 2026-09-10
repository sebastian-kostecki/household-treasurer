<script setup lang="ts">
import { computed } from 'vue';
import ColumnHeaderHint from '@/components/ColumnHeaderHint.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    amountMinorUnits: number;
}>();

const t = useTranslations();

const amountClass = computed(() =>
    props.amountMinorUnits < 0 ? 'text-destructive' : '',
);
</script>

<template>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <ColumnHeaderHint
            :label="t.budget.paced_pool_label"
            :hint="t.budget.paced_pool_hint"
        />
        <p class="text-lg font-semibold tabular-nums" :class="amountClass">
            {{ formatMoney(amountMinorUnits) }}
        </p>
    </div>
</template>
