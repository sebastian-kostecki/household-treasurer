<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    plannedBalanceMinorUnits: number;
}>();

const t = useTranslations();

const balanceClass = computed(() => {
    if (props.plannedBalanceMinorUnits < 0) {
        return 'text-destructive';
    }

    if (props.plannedBalanceMinorUnits > 0) {
        return 'text-success';
    }

    return '';
});
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 pt-6 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <p
                    class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                >
                    {{ t.budget.annual_plan }}
                </p>
            </div>
            <div>
                <p class="text-muted-foreground text-xs">
                    {{ t.budget.planned_income }}
                </p>
                <p class="text-lg font-semibold tabular-nums">
                    {{ formatMoney(incomeTotals.current_plan_minor_units) }}
                </p>
            </div>
            <div>
                <p class="text-muted-foreground text-xs">
                    {{ t.budget.planned_expense }}
                </p>
                <p class="text-lg font-semibold tabular-nums">
                    {{ formatMoney(expenseTotals.current_plan_minor_units) }}
                </p>
            </div>
            <div>
                <p class="text-muted-foreground text-xs">
                    {{ t.budget.planned_balance }}
                </p>
                <p
                    class="text-lg font-semibold tabular-nums"
                    :class="balanceClass"
                >
                    {{ formatMoney(plannedBalanceMinorUnits) }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
