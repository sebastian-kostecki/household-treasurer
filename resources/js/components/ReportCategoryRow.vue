<script setup lang="ts">
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import type { PeriodReportCategory } from '@/types/reports';

defineProps<{
    category: PeriodReportCategory;
    /** Expense tables show the Udział column; income does not. */
    showShare: boolean;
    /** The row stands under a group row, indented. */
    nested?: boolean;
}>();

const t = useTranslations();
</script>

<template>
    <div
        class="report-grid report-row"
        :class="{
            'report-grid-no-share': !showShare,
            'report-row-nested': nested,
            'text-muted-foreground': category.is_archived,
        }"
        data-report-row="category"
    >
        <div class="report-cell-name">
            <CategoryChip
                :name="category.name"
                :icon="category.icon ?? ''"
                :color="category.color"
            />
            <Badge v-if="category.is_archived" variant="neutral">
                {{ t.reports.archived_badge }}
            </Badge>
        </div>
        <div class="report-cell-amount">
            {{ formatMoneyAmount(category.amount_minor_units) }}
        </div>
        <div v-if="showShare" class="report-cell-share">
            {{ formatShareTenths(category.share_tenths_percent) }}
        </div>
    </div>
</template>
