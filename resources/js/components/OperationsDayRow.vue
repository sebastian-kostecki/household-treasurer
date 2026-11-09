<script setup lang="ts">
import {
    formatDayLabel,
    formatOperationDaySum,
    operationDaySumClass,
} from '@/lib/format';

// Bez sumy (transfery własne, ruchy kapitału) wiersz dnia niesie tylko datę.
defineProps<{
    date: string;
    sumMinorUnits?: number;
}>();
</script>

<template>
    <div class="operations-grid operations-day-row">
        <span class="operations-day-label">{{ formatDayLabel(date) }}</span>
        <span
            v-if="sumMinorUnits !== undefined"
            class="operations-cell-amount amount-sum"
            :class="operationDaySumClass(sumMinorUnits)"
        >
            {{ formatOperationDaySum(sumMinorUnits) }}
        </span>
    </div>
</template>
