<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    incomeExecutionMinorUnits: number;
    expenseExecutionMinorUnits: number;
    monthResultMinorUnits: number;
}>();

const t = useTranslations();

const resultClass = computed(() => {
    if (props.monthResultMinorUnits < 0) {
        return 'text-destructive';
    }

    if (props.monthResultMinorUnits > 0) {
        return 'text-success';
    }

    return '';
});
</script>

<template>
    <div class="border-border/60 grid gap-4 border-t pt-4 sm:grid-cols-3">
        <div>
            <p class="text-muted-foreground text-xs">
                {{ t.budget.month_income }}
            </p>
            <p class="text-lg font-semibold tabular-nums">
                {{ formatMoney(incomeExecutionMinorUnits) }}
            </p>
        </div>
        <div>
            <p class="text-muted-foreground text-xs">
                {{ t.budget.month_expense }}
            </p>
            <p class="text-lg font-semibold tabular-nums">
                {{ formatMoney(expenseExecutionMinorUnits) }}
            </p>
        </div>
        <div>
            <p class="text-muted-foreground text-xs">
                {{ t.budget.month_result }}
            </p>
            <p
                class="text-lg font-semibold tabular-nums"
                :class="resultClass"
            >
                {{ formatMoney(monthResultMinorUnits) }}
            </p>
        </div>
    </div>
</template>
