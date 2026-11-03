<script setup lang="ts">
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import BudgetAmountCell from '@/components/BudgetAmountCell.vue';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
import BudgetCategoryRowActions from '@/components/BudgetCategoryRowActions.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import PlanChangedHint from '@/components/PlanChangedHint.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    executionClass,
    percentClass,
    remainingClass,
} from '@/lib/budget-category-cells';

const props = defineProps<{
    category: BudgetCategoryRow;
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    showHandle: boolean;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();

const isDraft = computed(() => props.phase === 'draft');
const isArchived = computed(() => props.category.is_archived);

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
                class="drag-handle text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-xl active:cursor-grabbing"
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
            <Badge v-if="isArchived" variant="secondary">
                {{ t.budget.archived_badge }}
            </Badge>
        </div>

        <BudgetAmountCell
            v-if="isDraft"
            area="plan"
            :label="t.budget.plan"
            :amount="category.current_plan_minor_units"
            label-hidden
            value-class="font-semibold"
        />
        <template v-else>
            <BudgetAmountCell
                area="initial"
                :label="t.budget.initial_plan"
                :amount="category.initial_plan_minor_units"
                value-class="font-medium text-muted-foreground"
            >
                <PlanChangedHint
                    :category="category"
                    :label="t.budget.plan_changed"
                />
            </BudgetAmountCell>
            <BudgetAmountCell
                area="current"
                :label="t.budget.current_plan"
                :amount="category.current_plan_minor_units"
                value-class="font-semibold"
            />
            <BudgetAmountCell
                area="execution"
                :label="t.budget.execution"
                :amount="category.execution_minor_units"
                :value-class="[
                    'font-medium',
                    executionClass(categoryType, isArchived),
                ]"
            >
                <span
                    v-if="category.percent_complete !== null"
                    class="text-xs tabular-nums"
                    :class="
                        percentClass(
                            categoryType,
                            category.percent_complete,
                            category.is_over_limit,
                            isArchived,
                        )
                    "
                    :title="t.budget.progress_hint"
                >
                    {{ category.percent_complete }}%
                </span>
            </BudgetAmountCell>
            <BudgetAmountCell
                area="remaining"
                :label="remainingLabel"
                :amount="category.remaining_minor_units"
                :value-class="[
                    'font-medium',
                    remainingClass(
                        categoryType,
                        category.is_over_limit,
                        isArchived,
                    ),
                ]"
            />
        </template>

        <BudgetCategoryRowActions
            class="budget-category-cell-actions"
            :category="category"
            @edit="emit('edit')"
        />
    </div>
</template>
