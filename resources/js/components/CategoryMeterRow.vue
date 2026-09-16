<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    type DashboardCategory,
    meterBarClass,
    meterPercentClass,
    progressWidth,
    remainingClass,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    category: DashboardCategory;
    categoryType: 'income' | 'expense';
    showYearElapsedMark?: boolean;
    yearElapsedPercent?: number | null;
}>();

const t = useTranslations();

const displayPercentComplete = computed(() => props.category.percent_complete);
const showMeterTrack = computed(
    () =>
        displayPercentComplete.value !== null ||
        props.showYearElapsedMark === true,
);
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
            :class="remainingClass(categoryType, category.is_over_limit)"
        >
            <span class="text-muted-foreground text-[11px] md:hidden">
                {{ t.dashboard.remaining }}
            </span>
            <span class="text-sm">
                {{ formatMoney(category.remaining_minor_units) }}
            </span>
        </div>

        <div class="category-meter-cell-bar">
            <div
                v-if="showMeterTrack"
                class="bg-muted relative h-1.5 overflow-visible rounded-full"
            >
                <div
                    v-if="displayPercentComplete !== null"
                    class="h-full rounded-full transition-all"
                    :class="
                        meterBarClass(
                            categoryType,
                            displayPercentComplete,
                            category.is_over_limit,
                        )
                    "
                    :style="{
                        width: progressWidth(displayPercentComplete),
                    }"
                />
                <div
                    v-if="
                        showYearElapsedMark &&
                        yearElapsedPercent !== null &&
                        yearElapsedPercent !== undefined
                    "
                    class="bg-foreground ring-background pointer-events-none absolute top-1/2 z-10 h-3 w-0.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-1"
                    :style="{ left: `${yearElapsedPercent}%` }"
                    aria-hidden="true"
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
                          category.is_over_limit,
                      )
            "
        >
            <template v-if="displayPercentComplete !== null">
                {{ displayPercentComplete }}%
            </template>
        </div>
    </div>
</template>
