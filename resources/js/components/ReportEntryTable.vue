<script setup lang="ts">
import { reactive, useId } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportCategoryRow from '@/components/ReportCategoryRow.vue';
import ReportGroupRow from '@/components/ReportGroupRow.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';
import { formatShareTenths } from '@/lib/format-share';
import type { PeriodReportEntry } from '@/types/reports';

const props = defineProps<{
    type: 'income' | 'expense';
    title: string;
    entries: PeriodReportEntry[];
    totalMinorUnits: number;
}>();

const t = useTranslations();

const showShare = props.type === 'expense';
const tableId = useId();

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

        <div v-if="entries.length > 0">
            <div
                class="report-grid report-header-row"
                :class="{ 'report-grid-no-share': !showShare }"
            >
                <div>{{ t.reports.category }}</div>
                <div class="text-right">{{ t.reports.amount }}</div>
                <div v-if="showShare" class="text-right">
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
                />
                <div v-else>
                    <ReportGroupRow
                        :group="entry"
                        :show-share="showShare"
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
                        />
                    </div>
                </div>
            </template>

            <div
                class="report-grid report-total-row"
                :class="{ 'report-grid-no-share': !showShare }"
            >
                <div class="section-title">{{ t.reports.totals }}</div>
                <div class="report-cell-amount amount-sum">
                    {{ formatMoneyAmount(totalMinorUnits) }}
                </div>
                <div v-if="showShare" class="report-cell-share">
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
