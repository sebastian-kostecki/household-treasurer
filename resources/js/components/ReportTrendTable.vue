<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, reactive, useId } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportTrendRow from '@/components/ReportTrendRow.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    reportCategoryOperationsQuery,
    reportOperationsLinkLabel,
} from '@/lib/report-category-operations';
import { operations } from '@/routes';
import type { ReportTrendCategory, ReportTrendEntry } from '@/types/reports';

const props = defineProps<{
    type: 'income' | 'expense';
    title: string;
    entries: ReportTrendEntry[];
    /** The type's total of every month (12 cells, null without an operation). */
    totalMonths: (number | null)[];
    /** The type's total of the year. */
    totalMinorUnits: number;
    inProgressMonth: number | null;
    /** Year of the viewed Budget; a category name links to Operations of it. */
    budgetYear: number;
}>();

const t = useTranslations();

const tableId = useId();

const regionLabel = computed(() =>
    t.reports.trend_table_label.replace(':title', props.title),
);

/** Groups start collapsed; an open id is remembered only for this visit. */
const opened = reactive(new Set<number>());

function isOpen(groupId: number): boolean {
    return opened.has(groupId);
}

function toggle(groupId: number): void {
    if (opened.has(groupId)) {
        opened.delete(groupId);
    } else {
        opened.add(groupId);
    }
}

function bodyId(groupId: number): string {
    return `${tableId}-group-${groupId}`;
}

function categoryOperationsQuery(category: ReportTrendCategory) {
    return reportCategoryOperationsQuery(category, props.budgetYear, null);
}

function operationsLinkLabel(name: string): string {
    return reportOperationsLinkLabel(
        t.reports.operations_link_label,
        name,
        String(props.budgetYear),
    );
}

function toggleLabel(name: string, open: boolean): string {
    return (open ? t.reports.group_collapse : t.reports.group_expand).replace(
        ':name',
        name,
    );
}
</script>

<template>
    <ListSurface flush :data-trend-table="type">
        <template #header>
            <h2 class="section-title">{{ title }}</h2>
        </template>

        <div
            v-if="entries.length > 0"
            class="report-trend-scroll"
            role="region"
            tabindex="0"
            :aria-label="regionLabel"
        >
            <div role="table" :aria-label="title">
                <div role="rowgroup">
                    <ReportTrendRow
                        variant="header"
                        :in-progress-month="inProgressMonth"
                    >
                        {{ t.reports.category }}
                    </ReportTrendRow>
                </div>

                <div role="rowgroup">
                    <template
                        v-for="entry in entries"
                        :key="`${entry.kind}-${entry.id}`"
                    >
                        <ReportTrendRow
                            v-if="entry.kind === 'category'"
                            variant="row"
                            :months="entry.months"
                            :total-minor-units="entry.amount_minor_units"
                            :muted="entry.is_archived"
                            data-report-row="category"
                        >
                            <Link
                                v-if="categoryOperationsQuery(entry) !== null"
                                :href="
                                    operations({
                                        query: categoryOperationsQuery(entry)!,
                                    })
                                "
                                class="report-name-link"
                                :aria-label="operationsLinkLabel(entry.name)"
                            >
                                <CategoryChip
                                    :name="entry.name"
                                    :icon="entry.icon ?? ''"
                                    :color="entry.color"
                                />
                            </Link>
                            <CategoryChip
                                v-else
                                :name="entry.name"
                                :icon="entry.icon ?? ''"
                                :color="entry.color"
                            />
                            <Badge v-if="entry.is_archived" variant="neutral">
                                {{ t.reports.archived_badge }}
                            </Badge>
                        </ReportTrendRow>
                        <div v-else role="none">
                            <ReportTrendRow
                                variant="group"
                                :months="entry.months"
                                :total-minor-units="entry.amount_minor_units"
                                data-report-row="group"
                            >
                                <button
                                    type="button"
                                    class="budget-category-group-toggle"
                                    :aria-expanded="isOpen(entry.id)"
                                    :aria-controls="bodyId(entry.id)"
                                    :aria-label="
                                        toggleLabel(
                                            entry.name,
                                            isOpen(entry.id),
                                        )
                                    "
                                    @click="toggle(entry.id)"
                                >
                                    <ChevronDown
                                        class="size-4 shrink-0 transition-transform"
                                        :class="
                                            isOpen(entry.id) ? '' : '-rotate-90'
                                        "
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        entry.name
                                    }}</span>
                                </button>
                                <Badge
                                    variant="neutral"
                                    :title="t.reports.group_category_count"
                                >
                                    {{ entry.categories.length }}
                                </Badge>
                            </ReportTrendRow>
                            <div
                                v-show="isOpen(entry.id)"
                                :id="bodyId(entry.id)"
                                role="none"
                            >
                                <ReportTrendRow
                                    v-for="category in entry.categories"
                                    :key="category.id"
                                    variant="row"
                                    nested
                                    :months="category.months"
                                    :total-minor-units="
                                        category.amount_minor_units
                                    "
                                    :muted="category.is_archived"
                                    data-report-row="category"
                                >
                                    <Link
                                        v-if="
                                            categoryOperationsQuery(
                                                category,
                                            ) !== null
                                        "
                                        :href="
                                            operations({
                                                query: categoryOperationsQuery(
                                                    category,
                                                )!,
                                            })
                                        "
                                        class="report-name-link"
                                        :aria-label="
                                            operationsLinkLabel(category.name)
                                        "
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
                                    <Badge
                                        v-if="category.is_archived"
                                        variant="neutral"
                                    >
                                        {{ t.reports.archived_badge }}
                                    </Badge>
                                </ReportTrendRow>
                            </div>
                        </div>
                    </template>

                    <ReportTrendRow
                        variant="total"
                        :months="totalMonths"
                        :total-minor-units="totalMinorUnits"
                    >
                        <span class="section-title">{{
                            t.reports.totals
                        }}</span>
                    </ReportTrendRow>
                </div>
            </div>
        </div>
        <p v-else class="text-muted-foreground px-4 py-3 text-sm">
            {{ t.reports.section_empty }}
        </p>

        <template v-if="entries.length > 0" #footer>
            <p class="meta text-muted-foreground">
                {{ t.reports.amounts_in_zl_rounded }}
            </p>
        </template>
    </ListSurface>
</template>
