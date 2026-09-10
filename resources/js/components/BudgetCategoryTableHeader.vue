<script setup lang="ts">
import { computed } from 'vue';
import ColumnHeaderHint from '@/components/ColumnHeaderHint.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    canReorder?: boolean;
    showMonthColumn?: boolean;
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
    <thead>
        <tr>
            <th v-if="canReorder" class="w-10" />
            <th>{{ t.budget.category }}</th>
            <th v-if="isDraft" class="text-right">
                {{ t.budget.plan }}
            </th>
            <template v-if="isExecution">
                <th class="text-right">
                    <ColumnHeaderHint
                        :label="t.budget.initial_plan"
                        :hint="t.budget.initial_plan_hint"
                    />
                </th>
                <th class="text-right">
                    <ColumnHeaderHint
                        :label="t.budget.current_plan"
                        :hint="t.budget.current_plan_hint"
                    />
                </th>
                <th class="text-right">
                    <ColumnHeaderHint
                        :label="t.budget.execution"
                        :hint="executionHint"
                    />
                </th>
                <th class="text-right">
                    <ColumnHeaderHint
                        :label="remainingLabel"
                        :hint="remainingHint"
                    />
                </th>
            </template>
            <th v-if="showMonthColumn" class="text-right">
                <ColumnHeaderHint
                    :label="t.budget.in_month_column"
                    :hint="t.budget.in_month_hint"
                />
            </th>
            <th class="text-right">
                {{ t.budget.actions }}
            </th>
        </tr>
    </thead>
</template>
