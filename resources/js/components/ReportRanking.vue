<script setup lang="ts">
import { computed, reactive, useId } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportRankingGroupRow from '@/components/ReportRankingGroupRow.vue';
import ReportRankingRow from '@/components/ReportRankingRow.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount, formatSignedMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import { reportDifferenceTone } from '@/lib/report-difference';
import {
    rankReportCategories,
    rankReportGroups,
    reportLargestAmount,
    reportShareBarRatio,
} from '@/lib/report-ranking';
import type {
    PeriodReportEntry,
    ReportRange,
    ReportRankingMode,
} from '@/types/reports';

const props = defineProps<{
    type: 'income' | 'expense';
    title: string;
    entries: PeriodReportEntry[];
    mode: ReportRankingMode;
    /** The "Kategorie | Grupy" switch stands in the header of this list only. */
    showModeSwitch?: boolean;
    totalMinorUnits: number;
    /** Year of the viewed Budget; heads the amount column with a comparison. */
    budgetYear: number;
    /** Viewed month (1 to 12), `null` for the whole Budget year. */
    month: number | null;
    /** The last-12 window (ADR-0081); the name links to Operations of this range. */
    range?: ReportRange | null;
    /** Year of the compared Budget; the comparison columns show only when set. */
    comparisonYear?: number;
    /** Previous-year total of this type (with `comparisonYear`). */
    previousTotalMinorUnits?: number;
    /** Current minus previous total of this type (with `comparisonYear`). */
    differenceTotalMinorUnits?: number;
}>();

const emit = defineEmits<{
    'update:mode': [mode: ReportRankingMode];
}>();

const t = useTranslations();

const showShare = props.type === 'expense';
const listId = useId();

const compared = computed(() => props.comparisonYear !== undefined);

const modeModel = computed({
    get: () => props.mode,
    set: (mode: ReportRankingMode) => emit('update:mode', mode),
});

const modeOptions = computed(() => [
    { value: 'categories' as const, label: t.reports.ranking_categories },
    { value: 'groups' as const, label: t.reports.ranking_groups },
]);

const rankedCategories = computed(() => rankReportCategories(props.entries));
const rankedGroups = computed(() => rankReportGroups(props.entries));
const largestAmount = computed(() =>
    reportLargestAmount(props.entries, props.mode),
);

function barRatio(amountMinorUnits: number): number {
    return reportShareBarRatio(amountMinorUnits, largestAmount.value);
}

const totalToneClass = computed(() => {
    const tone = reportDifferenceTone(
        props.differenceTotalMinorUnits ?? null,
        props.type,
    );

    return tone === null ? '' : planChangeToneClass(tone, false);
});

const previousLabel = computed(() =>
    t.reports.previous_year_label.replace(
        ':year',
        String(props.comparisonYear),
    ),
);

/** Groups start collapsed; an expanded id is remembered only for this visit. */
const expanded = reactive(new Set<number>());

function isOpen(groupId: number): boolean {
    return expanded.has(groupId);
}

function toggle(groupId: number): void {
    if (expanded.has(groupId)) {
        expanded.delete(groupId);
    } else {
        expanded.add(groupId);
    }
}

function bodyId(groupId: number): string {
    return `${listId}-group-${groupId}`;
}
</script>

<template>
    <ListSurface flush>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="section-title">{{ title }}</h2>
                <SegmentedControl
                    v-if="showModeSwitch && entries.length > 0"
                    v-model="modeModel"
                    :options="modeOptions"
                    :ariaLabel="t.reports.ranking_mode_label"
                    tone="neutral"
                />
            </div>
        </template>

        <div
            v-if="entries.length > 0"
            class="report-table"
            :data-ranking-list="type"
        >
            <div
                class="report-grid report-header-row"
                :class="{
                    'report-grid-no-share': !showShare,
                    'report-grid-compare': compared,
                }"
            >
                <div class="report-area-name">{{ t.reports.category }}</div>
                <div class="report-area-amount text-right">
                    {{ compared ? budgetYear : t.reports.amount }}
                </div>
                <div
                    v-if="compared"
                    class="report-area-previous report-head-previous text-right"
                >
                    {{ comparisonYear }}
                </div>
                <div
                    v-if="compared"
                    class="report-area-difference report-head-difference text-right"
                >
                    {{ t.reports.difference }}
                </div>
                <div
                    v-if="showShare"
                    class="report-area-share report-head-share text-right"
                >
                    {{ t.reports.share }}
                </div>
            </div>

            <template v-if="mode === 'categories'">
                <ReportRankingRow
                    v-for="row in rankedCategories"
                    :key="row.id"
                    :category="row"
                    :position="row.position"
                    :bar-ratio="barRatio(row.amount_minor_units)"
                    :show-share="showShare"
                    :type="type"
                    :comparison-year="comparisonYear"
                    :budget-year="budgetYear"
                    :month="month"
                    :range="range"
                />
            </template>
            <template v-else>
                <template
                    v-for="entry in rankedGroups"
                    :key="`${entry.kind}-${entry.id}`"
                >
                    <ReportRankingRow
                        v-if="entry.kind === 'category'"
                        :category="entry"
                        :position="null"
                        :bar-ratio="barRatio(entry.amount_minor_units)"
                        :show-share="showShare"
                        :type="type"
                        :comparison-year="comparisonYear"
                        :budget-year="budgetYear"
                        :month="month"
                        :range="range"
                    />
                    <div v-else>
                        <ReportRankingGroupRow
                            :group="entry"
                            :bar-ratio="barRatio(entry.amount_minor_units)"
                            :show-share="showShare"
                            :type="type"
                            :comparison-year="comparisonYear"
                            :open="isOpen(entry.id)"
                            :controls="bodyId(entry.id)"
                            @toggle="toggle(entry.id)"
                        />
                        <div v-show="isOpen(entry.id)" :id="bodyId(entry.id)">
                            <ReportRankingRow
                                v-for="category in entry.categories"
                                :key="category.id"
                                nested
                                :category="category"
                                :position="null"
                                :bar-ratio="
                                    barRatio(category.amount_minor_units)
                                "
                                :show-share="showShare"
                                :type="type"
                                :comparison-year="comparisonYear"
                                :budget-year="budgetYear"
                                :month="month"
                                :range="range"
                            />
                        </div>
                    </div>
                </template>
            </template>

            <div
                class="report-grid report-total-row"
                :class="{
                    'report-grid-no-share': !showShare,
                    'report-grid-compare': compared,
                }"
            >
                <div class="report-area-name section-title">
                    {{ t.reports.totals }}
                </div>
                <div class="report-area-amount report-cell-amount amount-sum">
                    {{ formatMoneyAmount(totalMinorUnits) }}
                </div>
                <div
                    v-if="compared"
                    class="report-area-previous report-cell-previous amount-sum"
                >
                    <span class="report-previous-label">{{
                        previousLabel
                    }}</span
                    >{{ formatMoneyAmount(previousTotalMinorUnits ?? 0) }}
                </div>
                <div
                    v-if="compared"
                    class="report-area-difference report-cell-difference amount-sum"
                    :class="totalToneClass"
                >
                    {{
                        formatSignedMoneyAmount(differenceTotalMinorUnits ?? 0)
                    }}
                </div>
                <div
                    v-if="showShare"
                    class="report-area-share report-cell-share"
                >
                    <span class="report-share-label">{{
                        t.reports.share_label
                    }}</span>
                    {{ formatShareTenths(totalMinorUnits > 0 ? 1000 : null) }}
                </div>
            </div>
        </div>
        <p v-else class="text-muted-foreground px-4 py-3 text-sm">
            {{ t.reports.section_empty }}
        </p>

        <template v-if="entries.length > 0" #footer>
            <p class="meta text-muted-foreground">
                {{
                    compared
                        ? t.reports.amounts_in_zl_difference.replace(
                              ':year',
                              String(comparisonYear),
                          )
                        : t.reports.amounts_in_zl
                }}
            </p>
        </template>
    </ListSurface>
</template>
