<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount, formatSignedMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import {
    reportCategoryOperationsQuery,
    reportOperationsLinkLabel,
} from '@/lib/report-category-operations';
import { reportDifferenceTone } from '@/lib/report-difference';
import { operations } from '@/routes';
import type { PeriodReportCategory } from '@/types/reports';

const props = defineProps<{
    category: PeriodReportCategory;
    /** Expense tables show the Udział column; income does not. */
    showShare: boolean;
    /** Decides which way a difference is favorable. */
    type: 'income' | 'expense';
    /** Year of the compared Budget; the comparison cells show only when set. */
    comparisonYear?: number;
    /** The row stands under a group row, indented. */
    nested?: boolean;
    /** Year of the viewed Budget; the name links to Operations of this period. */
    budgetYear: number;
    /** Viewed month (1 to 12), `null` for the whole Budget year. */
    month: number | null;
}>();

const t = useTranslations();

const compared = computed(() => props.comparisonYear !== undefined);

const previousLabel = computed(() =>
    t.reports.previous_year_label.replace(
        ':year',
        String(props.comparisonYear),
    ),
);

const operationsQuery = computed(() =>
    reportCategoryOperationsQuery(
        props.category,
        props.budgetYear,
        props.month,
    ),
);

const operationsLinkLabel = computed(() => {
    const period =
        props.month === null
            ? String(props.budgetYear)
            : `${t.budget[`month_name_${props.month}` as keyof typeof t.budget]} ${props.budgetYear}`;

    return reportOperationsLinkLabel(
        t.reports.operations_link_label,
        props.category.name,
        period,
    );
});

const differenceToneClass = computed(() => {
    const tone = reportDifferenceTone(
        props.category.difference_minor_units,
        props.type,
    );

    return tone === null
        ? ''
        : planChangeToneClass(tone, props.category.is_archived);
});
</script>

<template>
    <div
        class="report-grid report-row"
        :class="{
            'report-grid-no-share': !showShare,
            'report-grid-compare': compared,
            'report-row-nested': nested,
            'text-muted-foreground': category.is_archived,
        }"
        data-report-row="category"
    >
        <div class="report-area-name report-cell-name">
            <Link
                v-if="operationsQuery !== null"
                :href="operations({ query: operationsQuery })"
                class="report-name-link"
                :aria-label="operationsLinkLabel"
            >
                <CategoryChip
                    :name="category.name"
                    :icon="category.icon ?? ''"
                    :color="category.color"
                />
            </Link>
            <CategoryChip
                v-else
                :name="category.name"
                :icon="category.icon ?? ''"
                :color="category.color"
            />
            <Badge v-if="category.is_archived" variant="neutral">
                {{ t.reports.archived_badge }}
            </Badge>
        </div>
        <div class="report-area-amount report-cell-amount">
            {{
                category.has_operations
                    ? formatMoneyAmount(category.amount_minor_units)
                    : '—'
            }}
        </div>
        <div
            v-if="compared"
            class="report-area-previous report-cell-previous"
            :class="{ 'text-muted-foreground': category.is_archived }"
        >
            <span class="report-previous-label">{{ previousLabel }}</span
            >{{
                category.previous_minor_units === null
                    ? '—'
                    : formatMoneyAmount(category.previous_minor_units)
            }}
        </div>
        <div
            v-if="compared"
            class="report-area-difference report-cell-difference"
            :class="differenceToneClass"
        >
            {{
                category.difference_minor_units === null
                    ? '—'
                    : formatSignedMoneyAmount(category.difference_minor_units)
            }}
        </div>
        <div v-if="showShare" class="report-area-share report-cell-share">
            {{ formatShareTenths(category.share_tenths_percent) }}
        </div>
    </div>
</template>
