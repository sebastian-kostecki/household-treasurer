<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import type { PeriodReportGroupEntry } from '@/types/reports';

const props = defineProps<{
    group: PeriodReportGroupEntry;
    /** Expense tables show the Udział column; income does not. */
    showShare: boolean;
    open: boolean;
    /** Id of the element the toggle controls (the group's body). */
    controls: string;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const t = useTranslations();

const toggleLabel = computed(() =>
    (props.open ? t.reports.group_collapse : t.reports.group_expand).replace(
        ':name',
        props.group.name,
    ),
);
</script>

<template>
    <div
        class="report-grid report-group-row"
        :class="{ 'report-grid-no-share': !showShare }"
        data-report-row="group"
    >
        <div class="report-cell-name">
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
            <Badge variant="neutral">{{ group.categories.length }}</Badge>
        </div>
        <div class="report-cell-amount amount-sum">
            {{ formatMoneyAmount(group.amount_minor_units) }}
        </div>
        <div v-if="showShare" class="report-cell-share">
            {{ formatShareTenths(group.share_tenths_percent) }}
        </div>
    </div>
</template>
