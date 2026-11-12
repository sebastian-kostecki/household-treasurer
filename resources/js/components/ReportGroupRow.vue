<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount, formatSignedMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import { reportDifferenceTone } from '@/lib/report-difference';
import type { PeriodReportGroupEntry } from '@/types/reports';

const props = defineProps<{
    group: PeriodReportGroupEntry;
    /** Expense tables show the Udział column; income does not. */
    showShare: boolean;
    /** Decides which way a difference is favorable. */
    type: 'income' | 'expense';
    /** Year of the compared Budget; the comparison cells show only when set. */
    comparisonYear?: number;
    open: boolean;
    /** Id of the element the toggle controls (the group's body). */
    controls: string;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const t = useTranslations();

const compared = computed(() => props.comparisonYear !== undefined);

const toggleLabel = computed(() =>
    (props.open ? t.reports.group_collapse : t.reports.group_expand).replace(
        ':name',
        props.group.name,
    ),
);

const previousLabel = computed(() =>
    t.reports.previous_year_label.replace(
        ':year',
        String(props.comparisonYear),
    ),
);

const differenceToneClass = computed(() => {
    const tone = reportDifferenceTone(
        props.group.difference_minor_units,
        props.type,
    );

    return tone === null ? '' : planChangeToneClass(tone, false);
});
</script>

<template>
    <div
        class="report-grid report-group-row"
        :class="{
            'report-grid-no-share': !showShare,
            'report-grid-compare': compared,
        }"
        data-report-row="group"
    >
        <div class="report-area-name report-cell-name">
            <button
                type="button"
                class="budget-category-group-toggle"
                :aria-expanded="open"
                :aria-controls="controls"
                :aria-label="toggleLabel"
                @click="emit('toggle')"
            >
                <ChevronDown
                    class="size-4 shrink-0 transition-transform"
                    :class="open ? '' : '-rotate-90'"
                    aria-hidden="true"
                />
                <span class="truncate">{{ group.name }}</span>
            </button>
            <Badge variant="neutral" :title="t.reports.group_category_count">
                {{ group.categories.length }}
            </Badge>
        </div>
        <div class="report-area-amount report-cell-amount amount-sum">
            {{
                group.has_operations
                    ? formatMoneyAmount(group.amount_minor_units)
                    : '—'
            }}
        </div>
        <div
            v-if="compared"
            class="report-area-previous report-cell-previous amount-sum"
        >
            <span class="report-previous-label">{{ previousLabel }}</span
            >{{
                group.previous_minor_units === null
                    ? '—'
                    : formatMoneyAmount(group.previous_minor_units)
            }}
        </div>
        <div
            v-if="compared"
            class="report-area-difference report-cell-difference amount-sum"
            :class="differenceToneClass"
        >
            {{
                group.difference_minor_units === null
                    ? '—'
                    : formatSignedMoneyAmount(group.difference_minor_units)
            }}
        </div>
        <div v-if="showShare" class="report-area-share report-cell-share">
            <span class="report-share-label">{{ t.reports.share_label }}</span>
            {{ formatShareTenths(group.share_tenths_percent) }}
        </div>
    </div>
</template>
