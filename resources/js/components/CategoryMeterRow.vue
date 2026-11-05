<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    type DashboardCategory,
    remainingClass,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    category: DashboardCategory;
    categoryType: 'income' | 'expense';
    showYearElapsedMark?: boolean;
    yearElapsedPercent?: number | null;
}>();

const t = useTranslations();

const displayPercentComplete = computed(() => props.category.percent_complete);
</script>

<template>
    <div
        class="category-meter-row"
        :class="category.is_archived ? 'opacity-70' : ''"
    >
        <div class="category-meter-cell-category min-w-0">
            <div class="flex min-w-0 items-center gap-2">
                <CategoryChip
                    :name="category.name"
                    :icon="category.icon"
                    :color="category.color"
                    size="sm"
                />
                <Badge
                    v-if="category.is_archived"
                    variant="secondary"
                    class="shrink-0"
                >
                    {{ t.budget.archived_badge }}
                </Badge>
            </div>
        </div>

        <div class="category-meter-cell-plan tabular-nums max-md:hidden">
            <span class="text-sm">
                {{ formatMoney(category.current_plan_minor_units) }}
            </span>
        </div>

        <div class="category-meter-cell-execution tabular-nums max-md:hidden">
            <span
                class="text-sm"
                :class="categoryType === 'income' ? 'text-success' : ''"
            >
                {{ formatMoney(category.execution_minor_units) }}
            </span>
        </div>

        <div
            class="category-meter-cell-remaining tabular-nums"
            :class="remainingClass(categoryType, category.is_over_limit)"
        >
            <span class="sr-only">
                {{ t.dashboard.remaining }}
            </span>
            <span class="text-sm">
                {{ formatMoney(category.remaining_minor_units) }}
            </span>
        </div>

        <div class="category-meter-cell-meter">
            <ExecutionMeter
                :percent-complete="displayPercentComplete"
                :category-type="categoryType"
                :is-over-limit="category.is_over_limit"
                :year-elapsed-percent="
                    showYearElapsedMark ? (yearElapsedPercent ?? null) : null
                "
            />
        </div>
    </div>
</template>
