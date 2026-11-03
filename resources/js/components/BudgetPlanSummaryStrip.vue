<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

defineProps<{
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    plannedBalanceMinorUnits: number;
}>();

const t = useTranslations();
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 pt-6 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <p class="column-caption">
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
                    :class="amountToneClass(plannedBalanceMinorUnits)"
                >
                    {{ formatMoney(plannedBalanceMinorUnits) }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
