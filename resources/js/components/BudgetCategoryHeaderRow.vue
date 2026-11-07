<script setup lang="ts">
import { computed } from 'vue';
import ColumnHeaderHint from '@/components/ColumnHeaderHint.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    showHandle: boolean;
}>();

const t = useTranslations();

const isDraft = computed(() => props.phase === 'draft');
const isExecution = computed(() => props.phase === 'execution');

const remainingLabel = computed(() =>
    props.categoryType === 'income'
        ? t.budget.remaining_income
        : t.budget.remaining_expense,
);

const executionHint = computed(() =>
    props.categoryType === 'income'
        ? t.budget.execution_hint_income
        : t.budget.execution_hint_expense,
);

const remainingHint = computed(() =>
    props.categoryType === 'income'
        ? t.budget.remaining_hint_income
        : t.budget.remaining_hint_expense,
);
</script>

<template>
    <div
        class="budget-category-grid budget-category-header-row"
        :class="{
            'budget-category-grid-draft': isDraft,
            'budget-category-grid-no-handle': !showHandle,
        }"
    >
        <div class="budget-category-cell-category">
            {{ t.budget.category }}
        </div>
        <div v-if="isDraft" class="budget-category-cell-kind">
            {{ t.budget.type }}
        </div>
        <div v-if="isDraft" class="budget-category-cell-plan">
            {{ t.budget.plan }}
        </div>
        <template v-if="isExecution">
            <div class="budget-category-cell-initial">
                <ColumnHeaderHint
                    :label="t.budget.initial_plan"
                    :hint="t.budget.initial_plan_hint"
                />
            </div>
            <div class="budget-category-cell-current">
                <ColumnHeaderHint
                    :label="t.budget.current_plan"
                    :hint="t.budget.current_plan_hint"
                />
            </div>
            <div class="budget-category-cell-execution">
                <ColumnHeaderHint
                    :label="t.budget.execution"
                    :hint="executionHint"
                />
            </div>
            <div class="budget-category-cell-remaining">
                <ColumnHeaderHint
                    :label="remainingLabel"
                    :hint="remainingHint"
                />
            </div>
            <div class="budget-category-cell-meter">
                {{ t.budget.progress }}
            </div>
        </template>
        <div class="budget-category-cell-actions">
            {{ t.budget.actions }}
        </div>
    </div>
</template>
