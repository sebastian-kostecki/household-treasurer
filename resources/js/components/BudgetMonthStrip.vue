<script setup lang="ts">
import { computed } from 'vue';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import BudgetPacedPoolHeadline from '@/components/BudgetPacedPoolHeadline.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    budgetYear: number;
    viewedMonth: number;
    showArchived: boolean;
    currentMonth: number | null;
    viewedMonthTiming: 'past' | 'current' | 'future';
    pacedPoolMonthRemainingMinorUnits: number;
}>();

const t = useTranslations();

const viewedMonthLabel = computed(() => {
    const monthName =
        t.budget[`month_name_${props.viewedMonth}` as keyof typeof t.budget];

    return `${monthName} ${props.budgetYear}`;
});
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 pt-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p
                    class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                >
                    {{ t.budget.month_scope }}
                </p>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium capitalize">
                        {{ viewedMonthLabel }}
                    </span>
                    <Badge
                        v-if="viewedMonthTiming === 'current'"
                        variant="secondary"
                    >
                        {{ t.budget.month_today_badge }}
                    </Badge>
                </div>
            </div>
            <BudgetMonthPicker
                :budget-year="budgetYear"
                :viewed-month="viewedMonth"
                :show-archived="showArchived"
                :current-month="currentMonth"
            />
            <BudgetPacedPoolHeadline
                :amount-minor-units="pacedPoolMonthRemainingMinorUnits"
                :viewed-month-timing="viewedMonthTiming"
            />
        </CardContent>
    </Card>
</template>
