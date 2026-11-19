<script setup lang="ts">
import { computed, ref } from 'vue';
import ReportYearsStatPanel from '@/components/ReportYearsStatPanel.vue';
import ReportYearsTable from '@/components/ReportYearsTable.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    reportYearsCutLabel,
    reportYearsInitialRange,
} from '@/lib/report-years';
import type { ReportYearsData } from '@/types/reports';

const props = defineProps<{
    years: ReportYearsData;
}>();

const t = useTranslations();

type YearsType = 'expense' | 'income';
type YearsRange = 'same-day' | 'full';

/** Tab and range are local to the page (not in the URL); both modes are in the props. */
const type = ref<YearsType>('expense');
const range = ref<YearsRange>(reportYearsInitialRange(props.years));

const mode = computed(() =>
    range.value === 'same-day' && props.years.same_day !== null
        ? props.years.same_day
        : props.years.full,
);
const section = computed(() => mode.value[type.value]);
const isSameDay = computed(
    () => range.value === 'same-day' && props.years.same_day !== null,
);

const cutMonthGenitive = computed(() =>
    props.years.cut === null
        ? ''
        : t.categories[
              `month_genitive_${props.years.cut.month}` as keyof typeof t.categories
          ],
);

const cutLabel = computed(() =>
    props.years.cut === null
        ? ''
        : reportYearsCutLabel(
              props.years.cut,
              cutMonthGenitive.value,
              t.reports.years_range_same_day,
          ),
);

const typeOptions = computed(() => [
    { value: 'expense' as const, label: t.reports.expense },
    { value: 'income' as const, label: t.reports.income },
]);

const rangeOptions = computed(() => [
    { value: 'same-day' as const, label: cutLabel.value },
    { value: 'full' as const, label: t.reports.years_range_full },
]);

const subtitle = computed(() => {
    if (props.years.current_year === null || props.years.cut === null) {
        return t.reports.years_subtitle_complete;
    }

    return isSameDay.value
        ? t.reports.years_subtitle_same_day
              .replace(':day', String(props.years.cut.day))
              .replace(':month', cutMonthGenitive.value)
              .replace(':year', String(props.years.current_year))
        : t.reports.years_subtitle_full.replace(
              ':year',
              String(props.years.current_year),
          );
});

const firstYear = computed(() => props.years.columns[0]);

const changeLabel = computed(() => {
    const finalYear = mode.value.final_year;

    return finalYear === null || finalYear === props.years.columns.at(-1)
        ? t.reports.years_change_since.replace(':year', String(firstYear.value))
        : t.reports.years_change_range
              .replace(':from', String(firstYear.value))
              .replace(':to', String(finalYear));
});

/** Only "Pełne lata" marks the year in progress: it is not part of the change. */
const inProgressYear = computed(() =>
    !isSameDay.value &&
    props.years.current_year !== null &&
    props.years.current_year !== mode.value.final_year
        ? props.years.current_year
        : null,
);

const tableTitle = computed(() =>
    type.value === 'expense'
        ? t.reports.years_section_expense
        : t.reports.years_section_income,
);
</script>

<template>
    <div class="flex flex-col gap-4 md:gap-5">
        <p
            class="meta text-muted-foreground"
            data-testid="report-years-subtitle"
        >
            {{ subtitle }}
        </p>

        <ReportYearsStatPanel
            :type="type"
            :section="section"
            :columns="years.columns"
            :current-year="years.current_year"
            :final-year="mode.final_year"
            :cut="isSameDay ? years.cut : null"
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="max-w-full self-start overflow-x-auto">
                <SegmentedControl
                    v-model="type"
                    tone="neutral"
                    class="segmented-control-tabs"
                    :ariaLabel="t.reports.years_type_label"
                    :options="typeOptions"
                />
            </div>
            <div
                v-if="years.same_day !== null"
                class="max-w-full self-start overflow-x-auto"
            >
                <SegmentedControl
                    v-model="range"
                    tone="neutral"
                    :ariaLabel="t.reports.years_range_label"
                    :options="rangeOptions"
                />
            </div>
        </div>

        <ReportYearsTable
            :type="type"
            :title="tableTitle"
            :section="section"
            :columns="years.columns"
            :current-year="years.current_year"
            :in-progress-year="inProgressYear"
            :change-label="changeLabel"
        />
    </div>
</template>
