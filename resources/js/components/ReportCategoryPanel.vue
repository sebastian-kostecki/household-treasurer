<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ReportMonthBars from '@/components/ReportMonthBars.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import {
    reportClosedRangeLabel,
    reportInProgressLabel,
    reportPanelCaption,
    reportPanelOperationsLabel,
} from '@/lib/report-category-panel';
import { reportCategoryOperationsQuery } from '@/lib/report-category-operations';
import { reportDifferenceTone } from '@/lib/report-difference';
import type { RankedReportCategory } from '@/lib/report-ranking';
import { reportMonthNamesFrom } from '@/lib/report-window';
import { operations } from '@/routes';
import type {
    ReportCategoryPanelData,
    ReportCategoryPanelItem,
} from '@/types/reports';

/**
 * Category Panel of the Year view (ADR-0097): the year amount and the amount
 * of the year before, the difference as an amount with its percent caption,
 * the Udział, 12 month bars, three rows and the button to Operations. The
 * server sends the readings; the page only formats them.
 */
const props = defineProps<{
    /** The `id` the Ranking buttons point at with `aria-controls`. */
    id: string;
    category: RankedReportCategory;
    type: 'income' | 'expense';
    item: ReportCategoryPanelItem;
    panel: ReportCategoryPanelData;
    budgetYear: number;
    /** Year of the compared Budget; the comparison KPIs show only when set. */
    comparisonYear?: number;
    /** The panel stands in a sheet (no card around it). */
    inSheet?: boolean;
}>();

const t = useTranslations();

const monthNames = computed(() => reportMonthNamesFrom(t.budget));
const monthChips = computed(() =>
    Array.from(
        { length: 12 },
        (_, index) => t.budget[`month_chip_${index + 1}`],
    ),
);

const compared = computed(() => props.comparisonYear !== undefined);

const caption = computed(() =>
    reportPanelCaption(
        props.type,
        props.category.groupName,
        props.item.rhythm === null
            ? null
            : (t.categories[`rhythm_${props.item.rhythm}`] ?? null),
        {
            expense: t.reports.panel_caption_expense,
            income: t.reports.panel_caption_income,
            group: t.reports.panel_caption_group,
            rhythm: t.reports.panel_caption_rhythm,
        },
    ),
);

const differenceToneClass = computed(() => {
    const tone = reportDifferenceTone(
        props.category.difference_minor_units,
        props.type,
    );

    return tone === null
        ? ''
        : planChangeToneClass(tone, props.category.is_archived);
});

const differencePercent = computed(() => {
    const tenths = props.item.difference_tenths_percent;

    if (tenths === null) {
        return '—';
    }

    return t.reports.panel_difference_percent
        .replace(
            ':percent',
            `${tenths > 0 ? '+' : ''}${formatShareTenths(tenths)}`,
        )
        .replace(':year', String(props.comparisonYear));
});

const isLargest = computed(
    () =>
        props.type === 'expense' &&
        props.category.position === 1 &&
        props.category.has_operations,
);

const timeTitle = computed(() =>
    props.type === 'expense'
        ? t.reports.panel_time_expense
        : t.reports.panel_time_income,
);

const averageLabel = computed(() => {
    const range = reportClosedRangeLabel(
        props.panel.closed_month_count,
        monthChips.value,
    );

    return range === null
        ? t.reports.panel_average
        : t.reports.panel_average_range.replace(':range', range);
});

function monthAmount(month: number): string {
    const cell = props.item.months[month - 1];

    return cell === null || cell === undefined ? '—' : formatMoney(cell);
}

const highestLabel = computed(() => {
    const month = props.item.highest_month;

    if (month === null) {
        return '—';
    }

    const name = monthNames.value[month - 1];

    return `${name.charAt(0).toLocaleUpperCase('pl-PL')}${name.slice(1)} · ${monthAmount(month)}`;
});

const inProgressLabel = computed(() =>
    props.panel.in_progress_month === null || props.panel.through === null
        ? null
        : reportInProgressLabel(
              props.panel.in_progress_month,
              props.panel.through,
              monthNames.value,
              t.reports.panel_in_progress_row,
          ),
);

const operationsQuery = computed(() =>
    reportCategoryOperationsQuery(props.category, props.budgetYear, null),
);

const operationsLabel = computed(() =>
    reportPanelOperationsLabel(
        t.reports.panel_open_operations,
        props.category.name,
        props.budgetYear,
    ),
);
</script>

<template>
    <div
        :id="id"
        class="report-panel"
        :class="inSheet ? '' : 'report-panel-card'"
        data-report-panel
    >
        <div class="grid gap-1">
            <CategoryChip
                :name="category.name"
                :icon="category.icon ?? ''"
                :color="category.color"
                size="lg"
            >
                <template #subtitle>
                    <span class="meta text-muted-foreground truncate">{{
                        caption
                    }}</span>
                </template>
            </CategoryChip>
        </div>

        <div class="report-panel-kpis">
            <div class="report-panel-kpi">
                <span class="report-panel-kpi-label">{{ budgetYear }}</span>
                <span class="report-panel-kpi-main" data-panel-amount>{{
                    formatMoney(category.amount_minor_units)
                }}</span>
            </div>
            <div v-if="compared" class="report-panel-kpi">
                <span class="report-panel-kpi-label">{{ comparisonYear }}</span>
                <span class="report-panel-kpi-main" data-panel-previous>{{
                    category.previous_minor_units === null
                        ? '—'
                        : formatMoney(category.previous_minor_units)
                }}</span>
            </div>
            <div v-if="compared" class="report-panel-kpi">
                <span class="report-panel-kpi-label">{{
                    t.reports.difference
                }}</span>
                <span
                    class="report-panel-kpi-value"
                    :class="differenceToneClass"
                    data-panel-difference
                    >{{
                        category.difference_minor_units === null
                            ? '—'
                            : formatSignedMoney(category.difference_minor_units)
                    }}</span
                >
                <span class="report-panel-kpi-label" data-panel-percent>{{
                    differencePercent
                }}</span>
            </div>
            <div v-if="type === 'expense'" class="report-panel-kpi">
                <span class="report-panel-kpi-label">{{
                    t.reports.share
                }}</span>
                <span class="report-panel-kpi-value" data-panel-share>{{
                    formatShareTenths(category.share_tenths_percent)
                }}</span>
                <span v-if="isLargest" class="report-panel-kpi-label">{{
                    t.reports.panel_largest
                }}</span>
            </div>
        </div>

        <div class="grid gap-2">
            <div class="flex items-baseline justify-between gap-2">
                <h3 class="section-title">{{ timeTitle }}</h3>
                <span class="meta text-muted-foreground">{{
                    t.reports.panel_months_label.replace(
                        ':year',
                        String(budgetYear),
                    )
                }}</span>
            </div>
            <ReportMonthBars
                :type="type"
                :months="item.months"
                :closed-month-count="panel.closed_month_count"
                :in-progress-month="panel.in_progress_month"
                :highest-month="item.highest_month"
            />
        </div>

        <div class="report-panel-rows">
            <div class="report-panel-row" data-panel-row="average">
                <span class="text-muted-foreground">{{ averageLabel }}</span>
                <span class="amount">{{
                    item.closed_average_minor_units === null
                        ? '—'
                        : formatMoney(item.closed_average_minor_units)
                }}</span>
            </div>
            <div class="report-panel-row" data-panel-row="highest">
                <span class="text-muted-foreground">{{
                    t.reports.panel_highest
                }}</span>
                <span class="amount">{{ highestLabel }}</span>
            </div>
            <div
                v-if="
                    inProgressLabel !== null && panel.in_progress_month !== null
                "
                class="report-panel-row"
                data-panel-row="in-progress"
            >
                <span class="text-muted-foreground">{{ inProgressLabel }}</span>
                <span class="amount">{{
                    monthAmount(panel.in_progress_month)
                }}</span>
            </div>
        </div>

        <Button
            v-if="operationsQuery !== null"
            as-child
            variant="outline"
            class="w-full"
        >
            <Link :href="operations({ query: operationsQuery })">{{
                operationsLabel
            }}</Link>
        </Button>
    </div>
</template>
