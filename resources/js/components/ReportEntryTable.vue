<script setup lang="ts">
import { computed, reactive, useId } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportCategoryRow from '@/components/ReportCategoryRow.vue';
import ReportGroupRow from '@/components/ReportGroupRow.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount, formatSignedMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import { reportDifferenceTone } from '@/lib/report-difference';
import type { PeriodReportEntry } from '@/types/reports';

const props = defineProps<{
    type: 'income' | 'expense';
    title: string;
    entries: PeriodReportEntry[];
    totalMinorUnits: number;
    /** Year of the viewed Budget; heads the amount column with a comparison. */
    budgetYear: number;
    /** Year of the compared Budget; the comparison columns show only when set. */
    comparisonYear?: number;
    /** Previous-year total of this type (with `comparisonYear`). */
    previousTotalMinorUnits?: number;
    /** Current minus previous total of this type (with `comparisonYear`). */
    differenceTotalMinorUnits?: number;
}>();

const t = useTranslations();

const showShare = props.type === 'expense';
const tableId = useId();

const compared = computed(() => props.comparisonYear !== undefined);

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

/** Groups start open; a collapsed id is remembered only for this visit. */
const collapsed = reactive(new Set<number>());

function isOpen(groupId: number): boolean {
    return !collapsed.has(groupId);
}

function toggle(groupId: number): void {
    if (collapsed.has(groupId)) {
        collapsed.delete(groupId);
    } else {
        collapsed.add(groupId);
    }
}

function bodyId(groupId: number): string {
    return `${tableId}-group-${groupId}`;
}
</script>

<template>
    <ListSurface flush>
        <template #header>
            <h2 class="section-title">{{ title }}</h2>
        </template>

        <div v-if="entries.length > 0" class="report-table">
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
                <div v-if="showShare" class="report-area-share text-right">
                    {{ t.reports.share }}
                </div>
            </div>

            <template
                v-for="entry in entries"
                :key="`${entry.kind}-${entry.id}`"
            >
                <ReportCategoryRow
                    v-if="entry.kind === 'category'"
                    :category="entry"
                    :show-share="showShare"
                    :type="type"
                    :comparison-year="comparisonYear"
                />
                <div v-else>
                    <ReportGroupRow
                        :group="entry"
                        :show-share="showShare"
                        :type="type"
                        :comparison-year="comparisonYear"
                        :open="isOpen(entry.id)"
                        :controls="bodyId(entry.id)"
                        @toggle="toggle(entry.id)"
                    />
                    <div v-show="isOpen(entry.id)" :id="bodyId(entry.id)">
                        <ReportCategoryRow
                            v-for="category in entry.categories"
                            :key="category.id"
                            nested
                            :category="category"
                            :show-share="showShare"
                            :type="type"
                            :comparison-year="comparisonYear"
                        />
                    </div>
                </div>
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
                    {{ formatShareTenths(totalMinorUnits > 0 ? 1000 : null) }}
                </div>
            </div>
        </div>
        <p v-else class="text-muted-foreground px-4 py-3 text-sm">
            {{ t.reports.section_empty }}
        </p>

        <template v-if="entries.length > 0" #footer>
            <p class="meta text-muted-foreground">
                {{ t.reports.amounts_in_zl }}
            </p>
        </template>
    </ListSurface>
</template>
