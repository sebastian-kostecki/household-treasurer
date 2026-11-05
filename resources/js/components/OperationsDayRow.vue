<script setup lang="ts">
import {
    formatDayLabel,
    formatOperationDaySum,
    operationDaySumClass,
} from '@/lib/format';

defineProps<{
    date: string;
    sumMinorUnits: number;
}>();
</script>

<template>
    <div class="operations-grid operations-day-row">
        <span class="operations-day-label">{{ formatDayLabel(date) }}</span>
        <span
            class="operations-cell-amount amount-sum"
            :class="operationDaySumClass(sumMinorUnits)"
        >
            {{ formatOperationDaySum(sumMinorUnits) }}
        </span>
    </div>
</template>
