<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CategoryMeterRow from '@/components/CategoryMeterRow.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import {
    type CategoryOrderMode,
    type DashboardCategory,
    type DashboardTypeTotals,
    defaultCategoryOrderMode,
    progressWidth,
    sectionBarClass,
    sectionPercentClass,
    sortCategoriesByMonthRemaining,
    sortCategoriesByRisk,
    VISIBLE_CATEGORY_LIMIT,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    title: string;
    categoryType: 'income' | 'expense';
    categories: DashboardCategory[];
    totals: DashboardTypeTotals;
    showOrderToggle?: boolean;
    showMonthRemainingOrder?: boolean;
}>();

const t = useTranslations();
const isExpanded = ref(false);
const orderMode = ref<CategoryOrderMode>(
    defaultCategoryOrderMode(
        props.showOrderToggle ?? false,
        props.showMonthRemainingOrder ?? false,
    ),
);

const effectiveOrderMode = computed<CategoryOrderMode>(() => {
    if (orderMode.value === 'month' && !props.showMonthRemainingOrder) {
        return 'risk';
    }

    return orderMode.value;
});

const orderModeLabel = computed(() => {
    switch (effectiveOrderMode.value) {
        case 'budget':
            return t.dashboard.order_budget;
        case 'risk':
            return t.dashboard.order_risk;
        case 'month':
            return t.dashboard.order_month_remaining;
    }
});

const orderedCategories = computed(() => {
    if (effectiveOrderMode.value === 'risk') {
        return sortCategoriesByRisk(props.categories);
    }

    if (effectiveOrderMode.value === 'month') {
        return sortCategoriesByMonthRemaining(props.categories);
    }

    return props.categories;
});

const visibleCategories = computed(() => {
    if (
        isExpanded.value ||
        orderedCategories.value.length <= VISIBLE_CATEGORY_LIMIT
    ) {
        return orderedCategories.value;
    }

    return orderedCategories.value.slice(0, VISIBLE_CATEGORY_LIMIT);
});

const hiddenCategoryCount = computed(() =>
    Math.max(orderedCategories.value.length - VISIBLE_CATEGORY_LIMIT, 0),
);

const showExpander = computed(
    () => hiddenCategoryCount.value > 0 && !isExpanded.value,
);

const showCollapse = computed(
    () =>
        isExpanded.value &&
        orderedCategories.value.length > VISIBLE_CATEGORY_LIMIT,
);

watch(
    () => props.showMonthRemainingOrder,
    (showMonthRemainingOrder) => {
        if (!showMonthRemainingOrder && orderMode.value === 'month') {
            orderMode.value = 'risk';

            return;
        }

        if (showMonthRemainingOrder) {
            orderMode.value = defaultCategoryOrderMode(
                props.showOrderToggle ?? false,
                showMonthRemainingOrder,
            );
        }
    },
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
</script>

<template>
    <ListSurface>
        <template #header>
            <div class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-base font-semibold">
                        {{ title }}
                    </h3>
                    <DropdownMenu v-if="showOrderToggle">
                        <DropdownMenuTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :aria-label="t.dashboard.order_by"
                            >
                                {{ orderModeLabel }}
                                <ChevronDown class="size-4 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuRadioGroup v-model="orderMode">
                                <DropdownMenuRadioItem value="budget">
                                    {{ t.dashboard.order_budget }}
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="risk">
                                    {{ t.dashboard.order_risk }}
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem
                                    v-if="showMonthRemainingOrder"
                                    value="month"
                                >
                                    {{ t.dashboard.order_month_remaining }}
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-xs">
                            {{ t.dashboard.current_plan }}
                        </p>
                        <p class="font-medium tabular-nums">
                            {{ formatMoney(totals.current_plan_minor_units) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">
                            {{ t.dashboard.execution }}
                        </p>
                        <p
                            class="font-medium tabular-nums"
                            :class="
                                categoryType === 'income' ? 'text-success' : ''
                            "
                        >
                            {{ formatMoney(totals.execution_minor_units) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">
                            {{ t.dashboard.remaining }}
                        </p>
                        <p
                            class="font-medium tabular-nums"
                            :class="sectionRemainingClass"
                        >
                            {{ formatMoney(totals.remaining_minor_units) }}
                        </p>
                    </div>
                </div>

                <div v-if="totals.percent_complete !== null" class="space-y-1">
                    <div class="bg-muted h-1.5 overflow-hidden rounded-full">
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
                    <p
                        class="text-right text-xs tabular-nums"
                        :class="
                            sectionPercentClass(
                                categoryType,
                                totals.percent_complete,
                            )
                        "
                    >
                        {{ totals.percent_complete }}%
                    </p>
                </div>
            </div>
        </template>

        <p v-if="categories.length === 0" class="text-muted-foreground text-sm">
            {{ t.dashboard.no_categories }}
        </p>

        <div v-else class="divide-border/60 divide-y">
            <CategoryMeterRow
                v-for="category in visibleCategories"
                :key="category.id"
                :category="category"
                :category-type="categoryType"
                :order-mode="effectiveOrderMode"
            />
        </div>

        <div v-if="showExpander || showCollapse" class="pt-3">
            <Button
                type="button"
                variant="ghost"
                size="sm"
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
