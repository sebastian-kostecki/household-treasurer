<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import StatPanel from '@/components/StatPanel.vue';
import type { StatPanelMetric } from '@/components/StatPanel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import { reportDifferenceTone } from '@/lib/report-difference';
import { formatYearsChange, reportYearsCutShort } from '@/lib/report-years';
import type { ReportYearsCut } from '@/lib/report-years';
import type { ReportYearsSection } from '@/types/reports';

const props = defineProps<{
    type: 'income' | 'expense';
    section: ReportYearsSection;
    columns: number[];
    currentYear: number | null;
    finalYear: number | null;
    /** The cut day when the mode is "Do D", otherwise null. */
    cut: ReportYearsCut | null;
}>();

const t = useTranslations();

const typeLabel = computed(() =>
    props.type === 'expense' ? t.reports.expense : t.reports.income,
);

function yearLabel(year: number | null): string {
    if (year === null) {
        return typeLabel.value;
    }

    const template =
        props.cut === null
            ? t.reports.years_tile_year
            : t.reports.years_tile_year_cut;

    return template
        .replace(':type', typeLabel.value)
        .replace(':year', String(year))
        .replace(':cut', props.cut ? reportYearsCutShort(props.cut) : '');
}

function totalOf(year: number | null): string {
    const index = year === null ? -1 : props.columns.indexOf(year);
    const cell = index < 0 ? null : props.section.total.cells[index];

    return cell === null || cell === undefined ? '—' : formatMoney(cell);
}

function toneClass(tenths: number | null): string {
    const tone = reportDifferenceTone(tenths, props.type);

    return tone === null ? '' : planChangeToneClass(tone, false);
}

const firstYearCaption = computed(() => {
    if (props.section.first_year === props.columns[0]) {
        return t.reports.years_caption_first_year_app;
    }

    return props.type === 'expense'
        ? t.reports.years_caption_first_year_expense
        : t.reports.years_caption_first_year_income;
});

const averageCaption = computed(() => {
    const tenths = props.section.average_yearly_growth_tenths;

    if (tenths === null || props.finalYear === null) {
        return undefined;
    }

    const key = `years_average_${props.type}_${tenths < 0 ? 'down' : 'up'}`;

    return t.reports[key as keyof typeof t.reports]
        .replace(':from', String(props.section.first_year))
        .replace(':to', String(props.finalYear));
});

const fastest = computed(() => props.section.fastest_growing);

const metrics = computed<StatPanelMetric[]>(() => [
    {
        id: 'final',
        label: yearLabel(props.finalYear),
        value: totalOf(props.finalYear),
        caption:
            props.finalYear === null
                ? undefined
                : props.finalYear === props.currentYear
                  ? t.reports.in_progress
                  : t.reports.years_caption_last_full_year,
    },
    {
        id: 'first',
        label: yearLabel(props.section.first_year),
        value: totalOf(props.section.first_year),
        caption:
            props.section.first_year === null
                ? undefined
                : firstYearCaption.value,
    },
    {
        id: 'average',
        label: t.reports.years_average_yearly,
        value: formatYearsChange(props.section.average_yearly_growth_tenths),
        toneClass: toneClass(props.section.average_yearly_growth_tenths),
        caption: averageCaption.value,
    },
    {
        id: 'fastest',
        label: t.reports.years_fastest_growing,
        value: fastest.value?.name ?? '—',
    },
]);

const fastestCaption = computed(() =>
    fastest.value === null
        ? null
        : t.reports.years_fastest_caption
              .replace(
                  ':change',
                  formatYearsChange(fastest.value.growth_tenths),
              )
              .replace(':year', String(fastest.value.first_year)),
);
</script>

<template>
    <StatPanel :metrics="metrics">
        <template v-if="fastest !== null" #label-extra-fastest>
            <CategoryChip
                size="sm"
                :show-name="false"
                :name="fastest.name"
                :icon="fastest.icon"
                :color="fastest.color"
            />
        </template>
        <template v-if="fastestCaption !== null" #footer-fastest>
            <p class="meta" :class="toneClass(fastest?.growth_tenths ?? null)">
                {{ fastestCaption }}
            </p>
        </template>
    </StatPanel>
</template>
