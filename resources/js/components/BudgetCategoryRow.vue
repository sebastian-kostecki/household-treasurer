<script setup lang="ts">
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import BudgetAmountCell from '@/components/BudgetAmountCell.vue';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
import BudgetCategoryRowActions from '@/components/BudgetCategoryRowActions.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import PlanChangeArrow from '@/components/PlanChangeArrow.vue';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { executionClass, remainingClass } from '@/lib/budget-category-cells';
import { showsYearElapsedMark } from '@/lib/dashboard-categories';
import { formatMoneyAmount } from '@/lib/format';

const props = defineProps<{
    category: BudgetCategoryRow;
    budgetId: number;
    showArchived?: boolean;
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    showHandle: boolean;
    yearElapsedPercent: number | null;
    /** The row stands under a group row, indented. */
    nested?: boolean;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();

const isDraft = computed(() => props.phase === 'draft');
const isArchived = computed(() => props.category.is_archived);

const showsOverLimitBadge = computed(
    () =>
        props.phase === 'execution' &&
        props.categoryType === 'expense' &&
        props.category.is_over_limit &&
        !props.category.is_archived,
);

/** Badges next to the Category name; both chip sizes render this one list. */
const nameBadges = computed(
    (): { variant: BadgeVariants['variant']; label: string }[] => {
        if (isArchived.value) {
            return [{ variant: 'neutral', label: t.budget.archived_badge }];
        }

        if (showsOverLimitBadge.value) {
            return [
                {
                    variant: 'destructive-soft',
                    label: t.budget.over_limit_badge,
                },
            ];
        }

        return [];
    },
);

const kindLabel = computed(() => {
    if (props.categoryType === 'income') {
        return t.budget.kind_income;
    }

    return t.categories[`rhythm_${props.category.rhythm ?? 'running'}`];
});

const meterYearElapsedPercent = computed(() =>
    showsYearElapsedMark(props.categoryType, props.category.rhythm)
        ? props.yearElapsedPercent
        : null,
);

const collectedAtYearStart = computed(
    () => props.category.collected_at_year_start,
);

const collectedAtYearStartHint = computed(() =>
    collectedAtYearStart.value?.is_forecast
        ? t.budget.collected_at_year_start_forecast_hint
        : t.budget.collected_at_year_start_hint,
);

/** The Category description, read-only here (ADR-0088); null when empty. */
const description = computed(() => {
    const text = props.category.description;

    return text === null || text === '' ? null : text;
});

/** A line under the name (ADR-0076, ADR-0088) makes the chip `lg`. */
const hasSubtitle = computed(
    () => collectedAtYearStart.value !== null || description.value !== null,
);

const remainingLabel = computed(() =>
    props.categoryType === 'income'
        ? t.budget.remaining_income
        : t.budget.remaining_expense,
);
</script>

<template>
    <div
        class="budget-category-grid budget-category-row"
        :class="{
            'budget-category-grid-draft': isDraft,
            'budget-category-grid-no-handle': !showHandle,
            'budget-category-row-nested': nested,
            'text-muted-foreground': isArchived,
        }"
    >
        <div v-if="showHandle" class="budget-category-cell-handle">
            <button
                v-if="!isArchived"
                type="button"
                class="drag-handle text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-md active:cursor-grabbing"
                tabindex="-1"
                :aria-label="t.budget.drag_handle"
            >
                <GripVertical class="size-4" aria-hidden="true" />
            </button>
        </div>

        <div class="budget-category-cell-category flex items-center gap-2">
            <CategoryChip
                v-if="hasSubtitle"
                :name="category.name"
                :icon="category.icon"
                :color="category.color"
                size="lg"
            >
                <template #badges>
                    <Badge
                        v-for="badge in nameBadges"
                        :key="badge.label"
                        :variant="badge.variant"
                    >
                        {{ badge.label }}
                    </Badge>
                </template>

                <template #subtitle>
                    <span
                        class="budget-category-description"
                        :title="description ?? undefined"
                    >
                        <span
                            v-if="collectedAtYearStart !== null"
                            class="tabular-nums"
                            data-collected-at-year-start
                            :title="collectedAtYearStartHint"
                        >
                            {{ t.budget.collected_at_year_start }}
                            <span class="whitespace-nowrap"
                                >{{
                                    formatMoneyAmount(
                                        collectedAtYearStart.minor_units,
                                    )
                                }}<template
                                    v-if="collectedAtYearStart.is_forecast"
                                >
                                    ·
                                    {{
                                        t.budget
                                            .collected_at_year_start_forecast
                                    }}</template
                                ></span
                            >
                        </span>
                        <template v-if="description !== null">
                            <template v-if="collectedAtYearStart !== null">
                                {{ ' · ' }}
                            </template>
                            {{ description }}
                        </template>
                    </span>
                </template>
            </CategoryChip>
            <template v-else>
                <CategoryChip
                    :name="category.name"
                    :icon="category.icon"
                    :color="category.color"
                />
                <Badge
                    v-for="badge in nameBadges"
                    :key="badge.label"
                    :variant="badge.variant"
                >
                    {{ badge.label }}
                </Badge>
            </template>
        </div>

        <div v-if="isDraft" class="budget-category-cell-kind">
            <Badge variant="neutral">{{ kindLabel }}</Badge>
        </div>

        <BudgetAmountCell
            v-if="isDraft"
            area="plan"
            :label="t.budget.plan"
            :amount="category.current_plan_minor_units"
            label-hidden
        />
        <template v-else>
            <BudgetAmountCell
                area="initial"
                :label="t.budget.initial_plan"
                :amount="category.initial_plan_minor_units"
                value-class="text-muted-foreground"
            >
                <PlanChangeArrow
                    :reference="category.initial_plan_minor_units"
                    :current="category.current_plan_minor_units"
                    :category-type="categoryType"
                    :muted="isArchived"
                    :increased-label="t.budget.plan_increased"
                    :decreased-label="t.budget.plan_decreased"
                />
            </BudgetAmountCell>
            <BudgetAmountCell
                area="current"
                :label="t.budget.current_plan"
                :amount="category.current_plan_minor_units"
            />
            <BudgetAmountCell
                area="execution"
                :label="t.budget.execution"
                :amount="category.execution_minor_units"
                :value-class="executionClass(categoryType, isArchived)"
            />
            <BudgetAmountCell
                area="remaining"
                :label="remainingLabel"
                :amount="category.remaining_minor_units"
                :value-class="
                    remainingClass(
                        categoryType,
                        category.is_over_limit,
                        isArchived,
                    )
                "
            />
            <div
                class="budget-category-cell-meter"
                :title="t.budget.progress_hint"
            >
                <ExecutionMeter
                    :percent-complete="category.percent_complete"
                    :category-type="categoryType"
                    :is-over-limit="category.is_over_limit"
                    :year-elapsed-percent="meterYearElapsedPercent"
                    :muted="isArchived"
                />
            </div>
        </template>

        <BudgetCategoryRowActions
            class="budget-category-cell-actions"
            :category="category"
            :budget-id="budgetId"
            :show-archived="showArchived"
            @edit="emit('edit')"
        />
    </div>
</template>
