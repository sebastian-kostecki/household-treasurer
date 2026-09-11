<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    type CategoryOrderMode,
    type DashboardCategory,
    hasMonthRemainingReading,
    isOverMonthRemaining,
    meterBarClass,
    meterPercentClass,
    monthUsagePercent,
    planChanged,
    progressWidth,
    remainingClass,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    category: DashboardCategory;
    categoryType: 'income' | 'expense';
    orderMode?: CategoryOrderMode;
}>();

const t = useTranslations();

const hasPlanChanged = planChanged(props.category);
const isMonthView = computed(
    () =>
        props.orderMode === 'month' &&
        props.categoryType === 'expense',
);
const showMonthRemaining = computed(
    () => isMonthView.value && hasMonthRemainingReading(props.category),
);
const displayRemainingMinorUnits = computed(() => {
    if (showMonthRemaining.value) {
        return props.category.month_remaining_minor_units ?? 0;
    }

    return props.category.remaining_minor_units;
});
const displayPercentComplete = computed(() => {
    if (isMonthView.value && !showMonthRemaining.value) {
        return null;
    }

    if (!showMonthRemaining.value) {
        return props.category.percent_complete;
    }

    return monthUsagePercent(
        props.category.month_execution_minor_units ?? 0,
        props.category.month_remaining_minor_units ?? 0,
    );
});
const displayIsOverLimit = computed(() => {
    if (showMonthRemaining.value) {
        return isOverMonthRemaining(props.category.month_remaining_minor_units);
    }

    return props.category.is_over_limit;
});
</script>

<template>
    <div
        class="category-meter-row"
        :class="category.is_archived ? 'opacity-70' : ''"
    >
        <div class="category-meter-cell-category min-w-0">
            <div class="flex min-w-0 items-center gap-2">
                <CategoryChip
                    :name="category.name"
                    :icon="category.icon"
                    :color="category.color"
                    size="sm"
                />
                <Badge
                    v-if="category.is_archived"
                    variant="secondary"
                    class="shrink-0"
                >
                    {{ t.budget.archived_badge }}
                </Badge>
                <span
                    v-if="hasPlanChanged"
                    class="text-muted-foreground shrink-0 text-xs"
                    :title="t.dashboard.plan_changed"
                    :aria-label="t.dashboard.plan_changed"
                >
                    ·
                </span>
            </div>
        </div>

        <div class="category-meter-cell-plan tabular-nums">
            <span class="text-muted-foreground text-[11px] md:hidden">
                {{ t.dashboard.current_plan }}
            </span>
            <span class="text-sm">
                {{ formatMoney(category.current_plan_minor_units) }}
            </span>
        </div>

        <div class="category-meter-cell-execution tabular-nums">
            <span class="text-muted-foreground text-[11px] md:hidden">
                {{ t.dashboard.execution }}
            </span>
            <span
                class="text-sm"
                :class="categoryType === 'income' ? 'text-success' : ''"
            >
                {{ formatMoney(category.execution_minor_units) }}
            </span>
        </div>

        <div
            class="category-meter-cell-remaining tabular-nums"
            :class="remainingClass(categoryType, displayIsOverLimit)"
        >
            <span
                v-if="!isMonthView"
                class="text-muted-foreground text-[11px] md:hidden"
            >
                {{ t.dashboard.remaining }}
            </span>
            <span v-if="showMonthRemaining" class="text-sm">
                {{ formatMoney(displayRemainingMinorUnits) }}
            </span>
            <span
                v-else-if="isMonthView"
                class="text-muted-foreground text-sm"
            >
                {{ t.common.not_applicable }}
            </span>
            <span v-else class="text-sm">
                {{ formatMoney(displayRemainingMinorUnits) }}
            </span>
        </div>

        <div class="category-meter-cell-bar">
            <div
                v-if="displayPercentComplete !== null"
                class="bg-muted h-1.5 overflow-hidden rounded-full"
            >
                <div
                    class="h-full rounded-full transition-all"
                    :class="
                        meterBarClass(
                            categoryType,
                            displayPercentComplete,
                            displayIsOverLimit,
                        )
                    "
                    :style="{
                        width: progressWidth(displayPercentComplete),
                    }"
                />
            </div>
        </div>

        <div
            class="category-meter-cell-percent text-right text-sm font-medium tabular-nums"
            :class="
                displayPercentComplete === null
                    ? 'text-muted-foreground'
                    : meterPercentClass(
                          categoryType,
                          displayPercentComplete,
                          displayIsOverLimit,
                      )
            "
        >
            <template v-if="displayPercentComplete === null">
                {{ t.common.not_applicable }}
            </template>
            <template v-else> {{ displayPercentComplete }}% </template>
        </div>
    </div>
</template>
