<script setup lang="ts">
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    type DashboardCategory,
    meterBarClass,
    meterPercentClass,
    planChanged,
    progressWidth,
    remainingClass,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    category: DashboardCategory;
    categoryType: 'income' | 'expense';
}>();

const t = useTranslations();

const hasPlanChanged = planChanged(props.category);
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
                {{ formatMoney(category.current_plan_grosze) }}
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
                {{ formatMoney(category.execution_grosze) }}
            </span>
        </div>

        <div
            class="category-meter-cell-remaining tabular-nums"
            :class="remainingClass(categoryType, category.is_over_limit)"
        >
            <span class="text-muted-foreground text-[11px] md:hidden">
                {{ t.dashboard.remaining }}
            </span>
            <span class="text-sm">
                {{ formatMoney(category.remaining_grosze) }}
            </span>
        </div>

        <div class="category-meter-cell-bar">
            <div
                v-if="category.percent_complete !== null"
                class="bg-muted h-1.5 overflow-hidden rounded-full"
            >
                <div
                    class="h-full rounded-full transition-all"
                    :class="
                        meterBarClass(
                            categoryType,
                            category.percent_complete,
                            category.is_over_limit,
                        )
                    "
                    :style="{
                        width: progressWidth(category.percent_complete),
                    }"
                />
            </div>
        </div>

        <div
            class="category-meter-cell-percent text-right text-sm font-medium tabular-nums"
            :class="
                category.percent_complete === null
                    ? 'text-muted-foreground'
                    : meterPercentClass(
                          categoryType,
                          category.percent_complete,
                          category.is_over_limit,
                      )
            "
        >
            <template v-if="category.percent_complete === null">
                {{ t.common.not_applicable }}
            </template>
            <template v-else>
                {{ category.percent_complete }}%
            </template>
        </div>
    </div>
</template>
