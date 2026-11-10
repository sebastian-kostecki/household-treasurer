<script setup lang="ts">
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import BudgetAmountCell from '@/components/BudgetAmountCell.vue';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
import BudgetCategoryRowActions from '@/components/BudgetCategoryRowActions.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import PlanChangeArrow from '@/components/PlanChangeArrow.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { executionClass, remainingClass } from '@/lib/budget-category-cells';
import { showsYearElapsedMark } from '@/lib/dashboard-categories';

const props = defineProps<{
    category: BudgetCategoryRow;
    budgetId: number;
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    showHandle: boolean;
    yearElapsedPercent: number | null;
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

const kindLabel = computed(() => {
    if (props.categoryType === 'income') {
        return t.budget.kind_income;
    }

    return props.category.month_item_kind === 'bill'
        ? t.budget.kind_bill
        : t.budget.kind_running;
});

const meterYearElapsedPercent = computed(() =>
    showsYearElapsedMark(props.categoryType, props.category)
        ? props.yearElapsedPercent
        : null,
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
                :name="category.name"
                :icon="category.icon"
                :color="category.color"
            />
            <Badge v-if="isArchived" variant="neutral">
                {{ t.budget.archived_badge }}
            </Badge>
            <Badge v-else-if="showsOverLimitBadge" variant="destructive-soft">
                {{ t.budget.over_limit_badge }}
            </Badge>
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
            @edit="emit('edit')"
        />
    </div>
</template>
