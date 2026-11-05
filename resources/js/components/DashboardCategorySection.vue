<script setup lang="ts">
import { computed, ref } from 'vue';
import CategoryMeterRow from '@/components/CategoryMeterRow.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    type DashboardCategory,
    type DashboardTypeTotals,
    progressWidth,
    sectionBarClass,
    sectionPercentClass,
    showsYearElapsedMark,
    dashboardVisibleCategoryLimit,
    hiddenDashboardCategoryCount,
    sliceVisibleCategories,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    title: string;
    categoryType: 'income' | 'expense';
    categories: DashboardCategory[];
    totals: DashboardTypeTotals;
    showYearElapsed: boolean;
    yearElapsedPercent: number;
}>();

const t = useTranslations();
const isExpanded = ref(false);

const visibleCategoryLimit = computed(() =>
    dashboardVisibleCategoryLimit(props.categoryType),
);

const visibleCategories = computed(() =>
    sliceVisibleCategories(
        props.categories,
        visibleCategoryLimit.value,
        isExpanded.value,
    ),
);

const hiddenCategoryCount = computed(() =>
    hiddenDashboardCategoryCount(
        props.categories.length,
        visibleCategoryLimit.value,
    ),
);

const showExpander = computed(
    () => hiddenCategoryCount.value > 0 && !isExpanded.value,
);

const showCollapse = computed(
    () =>
        isExpanded.value &&
        props.categories.length > visibleCategoryLimit.value,
);

const sectionRemainingClass = computed(() => {
    if (
        props.categoryType === 'expense' &&
        props.totals.remaining_minor_units < 0
    ) {
        return 'text-destructive';
    }

    return '';
});

const totalsLabel = computed(() =>
    props.categoryType === 'income'
        ? t.dashboard.income_totals
        : t.dashboard.expense_totals,
);
</script>

<template>
    <ListSurface :title="title">
        <p v-if="categories.length === 0" class="text-muted-foreground text-sm">
            {{ t.dashboard.no_categories }}
        </p>

        <template v-else>
            <div class="category-meter-header-row table-header">
                <span>{{ t.budget.category }}</span>
                <span class="text-right max-md:hidden">
                    {{ t.dashboard.current_plan }}
                </span>
                <span class="text-right max-md:hidden">
                    {{ t.dashboard.execution }}
                </span>
                <span class="text-right">{{ t.dashboard.remaining }}</span>
                <span class="max-md:hidden" />
                <span class="text-right max-md:hidden">
                    {{ t.dashboard.percent_complete }}
                </span>
            </div>

            <div class="divide-border/60 divide-y">
                <CategoryMeterRow
                    v-for="category in visibleCategories"
                    :key="category.id"
                    :category="category"
                    :category-type="categoryType"
                    :show-year-elapsed-mark="
                        showYearElapsed &&
                        showsYearElapsedMark(categoryType, category)
                    "
                    :year-elapsed-percent="yearElapsedPercent"
                />
            </div>

            <div
                class="category-meter-row border-border/60 border-t font-semibold"
            >
                <div class="category-meter-cell-category min-w-0">
                    <span class="text-sm">{{ totalsLabel }}</span>
                </div>
                <div
                    class="category-meter-cell-plan tabular-nums max-md:hidden"
                >
                    <span class="text-sm">
                        {{ formatMoney(totals.current_plan_minor_units) }}
                    </span>
                </div>
                <div
                    class="category-meter-cell-execution tabular-nums max-md:hidden"
                >
                    <span
                        class="text-sm"
                        :class="categoryType === 'income' ? 'text-success' : ''"
                    >
                        {{ formatMoney(totals.execution_minor_units) }}
                    </span>
                </div>
                <div
                    class="category-meter-cell-remaining tabular-nums"
                    :class="sectionRemainingClass"
                >
                    <span class="sr-only">{{ t.dashboard.remaining }}</span>
                    <span class="text-sm">
                        {{ formatMoney(totals.remaining_minor_units) }}
                    </span>
                </div>
                <div class="category-meter-cell-bar">
                    <div
                        v-if="totals.percent_complete !== null"
                        class="bg-muted relative h-1.5 overflow-visible rounded-full"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :class="
                                sectionBarClass(
                                    categoryType,
                                    totals.percent_complete,
                                )
                            "
                            :style="{
                                width: progressWidth(totals.percent_complete),
                            }"
                        />
                    </div>
                </div>
                <div
                    class="category-meter-cell-percent text-right text-sm tabular-nums"
                    :class="
                        totals.percent_complete === null
                            ? 'text-muted-foreground'
                            : sectionPercentClass(
                                  categoryType,
                                  totals.percent_complete,
                              )
                    "
                >
                    <template v-if="totals.percent_complete !== null">
                        {{ totals.percent_complete }}%
                    </template>
                </div>
            </div>
        </template>

        <div
            v-if="showExpander || showCollapse"
            class="border-border/60 border-t pt-3"
        >
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="text-brand-text hover:text-brand-text -ml-3"
                @click="isExpanded = !isExpanded"
            >
                {{
                    showCollapse
                        ? t.dashboard.show_fewer_categories
                        : t.dashboard.show_more_categories.replace(
                              ':count',
                              String(hiddenCategoryCount),
                          )
                }}
            </Button>
        </div>
    </ListSurface>
</template>
