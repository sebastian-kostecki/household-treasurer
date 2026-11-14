<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    categoryYearCells,
    categoryYearsRangeLabel,
} from '@/lib/category-list';

const props = defineProps<{
    /** The years in which the Category has a Budget Line. */
    years: number[];
    /** The years of the user's Budgets (`shell.budgetYears`), the axis of the strip. */
    axis: number[];
    /** The legend variant: two small cells with their captions, no years. */
    legend?: boolean;
}>();

const t = useTranslations();

const cells = computed(() => categoryYearCells(props.axis, props.years));

const label = computed(() =>
    props.years.length === 0
        ? t.categories.no_years_label
        : t.categories.years_label.replace(
              ':years',
              categoryYearsRangeLabel(props.years),
          ),
);

function cellTitle(cell: { year: number; on: boolean }): string {
    return cell.on
        ? String(cell.year)
        : t.categories.year_off_title.replace(':year', String(cell.year));
}
</script>

<template>
    <div v-if="legend" class="categories-years-legend">
        <span class="categories-years-key">
            <span
                class="categories-years-cell"
                data-state="on"
                aria-hidden="true"
            />
            {{ t.categories.years_legend_on }}
        </span>
        <span class="categories-years-key">
            <span
                class="categories-years-cell"
                data-state="off"
                aria-hidden="true"
            />
            {{ t.categories.years_legend_off }}
        </span>
    </div>
    <div v-else class="categories-years" role="img" :aria-label="label">
        <span
            v-for="cell in cells"
            :key="cell.year"
            class="categories-years-cell"
            :data-state="cell.on ? 'on' : 'off'"
            :title="cellTitle(cell)"
            aria-hidden="true"
        >
            {{ cell.short }}
        </span>
    </div>
</template>
