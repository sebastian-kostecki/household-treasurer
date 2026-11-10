<script setup lang="ts">
import { ChevronDown, GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import BudgetAmountCell from '@/components/BudgetAmountCell.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    executionClass,
    totalsRemainingClass,
} from '@/lib/budget-category-cells';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';

const props = defineProps<{
    name: string;
    /** Totals of the group's active lines, from the server. */
    totals: DashboardTypeTotals;
    /** How many Categories stand in the group right now. */
    count: number;
    hasOverLimitCategory: boolean;
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    showHandle: boolean;
    open: boolean;
    /** Id of the element the toggle controls (the group's body). */
    controls: string;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const t = useTranslations();

const isDraft = computed(() => props.phase === 'draft');

const toggleLabel = computed(() =>
    (props.open ? t.budget.group_collapse : t.budget.group_expand).replace(
        ':name',
        props.name,
    ),
);

const remainingLabel = computed(() =>
    props.categoryType === 'income'
        ? t.budget.remaining_income
        : t.budget.remaining_expense,
);

const totalsAreOverLimit = computed(
    () =>
        props.categoryType === 'expense' &&
        props.totals.remaining_minor_units < 0,
);
</script>

<template>
    <div
        class="budget-category-grid budget-category-group-row"
        :class="{
            'budget-category-grid-draft': isDraft,
            'budget-category-grid-no-handle': !showHandle,
        }"
        data-group-row
    >
        <div v-if="showHandle" class="budget-category-cell-handle">
            <button
                type="button"
                class="drag-handle text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-md active:cursor-grabbing"
                tabindex="-1"
                :aria-label="t.budget.group_drag_handle"
            >
                <GripVertical class="size-4" aria-hidden="true" />
            </button>
        </div>

        <div class="budget-category-cell-category budget-category-group-name">
            <button
                type="button"
                class="budget-category-group-toggle"
                :aria-expanded="open"
                :aria-controls="controls"
                :aria-label="toggleLabel"
                @click="emit('toggle')"
            >
                <ChevronDown
                    class="size-4 shrink-0 transition-transform"
                    :class="open ? '' : '-rotate-90'"
                    aria-hidden="true"
                />
                <span class="truncate">{{ name }}</span>
            </button>
            <Badge variant="neutral" :title="t.budget.group_category_count">
                {{ count }}
            </Badge>
            <Badge
                v-if="hasOverLimitCategory"
                variant="destructive-soft"
                :title="t.budget.group_over_plan_hint"
            >
                {{ t.budget.group_over_plan_badge }}
            </Badge>
        </div>

        <BudgetAmountCell
            v-if="isDraft"
            area="plan"
            :label="t.budget.plan"
            :amount="totals.current_plan_minor_units"
            label-hidden
            total
        />
        <template v-else>
            <BudgetAmountCell
                area="initial"
                :label="t.budget.initial_plan"
                :amount="totals.initial_plan_minor_units"
                total
                value-class="text-muted-foreground"
            />
            <BudgetAmountCell
                area="current"
                :label="t.budget.current_plan"
                :amount="totals.current_plan_minor_units"
                total
            />
            <BudgetAmountCell
                area="execution"
                :label="t.budget.execution"
                :amount="totals.execution_minor_units"
                total
                :value-class="executionClass(categoryType, false)"
            />
            <BudgetAmountCell
                area="remaining"
                :label="remainingLabel"
                :amount="totals.remaining_minor_units"
                total
                :value-class="
                    totalsRemainingClass(
                        categoryType,
                        totals.remaining_minor_units,
                    )
                "
            />
            <div
                class="budget-category-cell-meter"
                :title="t.budget.progress_hint"
            >
                <ExecutionMeter
                    :percent-complete="totals.percent_complete"
                    :category-type="categoryType"
                    :is-over-limit="totalsAreOverLimit"
                    :year-elapsed-percent="null"
                />
            </div>
        </template>
    </div>
</template>
