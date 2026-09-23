<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CategoryMeterRow from '@/components/CategoryMeterRow.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import {
    type CategoryOrderMode,
    type DashboardCategory,
    type DashboardTypeTotals,
    progressWidth,
    sectionBarClass,
    sectionPercentClass,
    showsYearElapsedMark,
    dashboardDefaultOrderMode,
    dashboardVisibleCategoryLimit,
    hiddenDashboardCategoryCount,
    sliceVisibleCategories,
    sortCategoriesByRisk,
} from '@/lib/dashboard-categories';
import { formatDate, formatMoney } from '@/lib/format';

const props = defineProps<{
    title: string;
    categoryType: 'income' | 'expense';
    categories: DashboardCategory[];
    totals: DashboardTypeTotals;
    showOrderToggle?: boolean;
    yearElapsedAsOf: string;
    yearElapsedPercent: number;
    budgetYear: number;
    canSelectBudgetYear: boolean;
}>();

const t = useTranslations();
const isExpanded = ref(false);
const showYearElapsed = ref(false);
const orderMode = ref<CategoryOrderMode>(
    dashboardDefaultOrderMode(props.categoryType),
);
const createBudgetDialogOpen = ref(false);

const storageKey = computed(
    () => `dashboard-year-elapsed-${props.categoryType}`,
);
const checkboxId = computed(() => `year-elapsed-${props.categoryType}`);

onMounted(() => {
    showYearElapsed.value =
        window.localStorage.getItem(storageKey.value) === '1';
});

watch(showYearElapsed, (enabled) => {
    window.localStorage.setItem(storageKey.value, enabled ? '1' : '0');
});

const yearElapsedLabel = computed(
    () => `${t.dashboard.year_elapsed} ${formatDate(props.yearElapsedAsOf)}`,
);

const orderModeLabel = computed(() => {
    if (orderMode.value === 'budget') {
        return t.dashboard.order_budget;
    }

    return t.dashboard.order_risk;
});

const orderedCategories = computed(() => {
    if (orderMode.value === 'risk') {
        return sortCategoriesByRisk(props.categories);
    }

    return props.categories;
});

const visibleCategoryLimit = computed(() =>
    dashboardVisibleCategoryLimit(props.categoryType),
);

const visibleCategories = computed(() =>
    sliceVisibleCategories(
        orderedCategories.value,
        visibleCategoryLimit.value,
        isExpanded.value,
    ),
);

const hiddenCategoryCount = computed(() =>
    hiddenDashboardCategoryCount(
        orderedCategories.value.length,
        visibleCategoryLimit.value,
    ),
);

const showExpander = computed(
    () => hiddenCategoryCount.value > 0 && !isExpanded.value,
);

const showCollapse = computed(
    () =>
        isExpanded.value &&
        orderedCategories.value.length > visibleCategoryLimit.value,
);

const createBudgetYearInputId = computed(
    () => `dashboard-section-budget-year-${props.categoryType}`,
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
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                :id="checkboxId"
                                v-model="showYearElapsed"
                            />
                            <Label
                                :for="checkboxId"
                                class="cursor-pointer text-sm font-normal"
                            >
                                {{ yearElapsedLabel }}
                            </Label>
                        </div>
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
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
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
                :show-year-elapsed-mark="
                    showYearElapsed &&
                    showsYearElapsedMark(categoryType, category)
                "
                :year-elapsed-percent="yearElapsedPercent"
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

        <div class="pt-3">
            <Form
                v-if="canSelectBudgetYear"
                v-bind="BudgetController.selectYear.form()"
                v-slot="{ processing }"
            >
                <input type="hidden" name="year" :value="budgetYear" />
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                >
                    {{ t.dashboard.open_budget }}
                </Button>
            </Form>
            <CreateBudgetDialog
                v-else
                v-model:open="createBudgetDialogOpen"
                :year-input-id="createBudgetYearInputId"
                :suggested-year="budgetYear"
            >
                <template #trigger>
                    <Button variant="outline" size="sm">
                        {{ t.dashboard.open_budget }}
                    </Button>
                </template>
            </CreateBudgetDialog>
        </div>
    </ListSurface>
</template>
