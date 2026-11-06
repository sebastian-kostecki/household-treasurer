<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    type DashboardCategory,
    categoryOperationsQuery,
    remainingClass,
    showsYearElapsedMark,
} from '@/lib/dashboard-categories';
import { formatMoneyAmount } from '@/lib/format';
import { operations } from '@/routes';

const props = defineProps<{
    category: DashboardCategory;
    categoryType: 'income' | 'expense';
    budgetYear: number;
    yearElapsedPercent: number | null;
}>();

const t = useTranslations();

const href = computed(() =>
    operations({
        query: categoryOperationsQuery(
            props.category,
            props.categoryType,
            props.budgetYear,
        ),
    }),
);

const meterYearElapsedPercent = computed(() =>
    showsYearElapsedMark(props.categoryType, props.category)
        ? props.yearElapsedPercent
        : null,
);

const showsOverLimitBadge = computed(
    () => props.categoryType === 'expense' && props.category.is_over_limit,
);
</script>

<template>
    <Link :href="href" class="category-meter-row category-meter-row-link">
        <div class="category-meter-cell-category">
            <CategoryChip
                :name="category.name"
                :icon="category.icon"
                :color="category.color"
                size="sm"
                text-size="sm"
            />
        </div>

        <div class="category-meter-cell-plan amount">
            {{ formatMoneyAmount(category.current_plan_minor_units) }}
        </div>

        <div class="category-meter-cell-execution amount">
            {{ formatMoneyAmount(category.execution_minor_units) }}
        </div>

        <div
            class="category-meter-cell-remaining amount"
            :class="remainingClass(categoryType, category.is_over_limit)"
        >
            <span class="sr-only">{{ t.dashboard.remaining }}</span>
            {{ formatMoneyAmount(category.remaining_minor_units) }}
        </div>

        <div class="category-meter-cell-meter">
            <ExecutionMeter
                :percent-complete="category.percent_complete"
                :category-type="categoryType"
                :is-over-limit="category.is_over_limit"
                :year-elapsed-percent="meterYearElapsedPercent"
            />
        </div>

        <div v-if="showsOverLimitBadge" class="category-meter-cell-status">
            <Badge variant="destructive-soft">
                {{ t.budget.over_limit_badge }}
            </Badge>
        </div>
    </Link>
</template>
