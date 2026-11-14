<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, onUnmounted, reactive, ref, useId } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CategoryChip from '@/components/CategoryChip.vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportYearsRow from '@/components/ReportYearsRow.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import type { ReportYearsCategory, ReportYearsSection } from '@/types/reports';

const props = defineProps<{
    type: 'income' | 'expense';
    title: string;
    section: ReportYearsSection;
    columns: number[];
    currentYear: number | null;
    /** The year that carries the "w toku" caption (Pełne lata: none). */
    inProgressYear: number | null;
    /** Caption of the change column ("Zmiana od 2020", "Zmiana 2020–2025"). */
    changeLabel: string;
}>();

const t = useTranslations();

const tableId = useId();

const regionLabel = computed(() =>
    t.reports.years_table_label.replace(':title', props.title),
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

/** The target year is the last column; the link goes through the year switch because the year lives in the session (ADR-0101). */
const targetYear = computed(() => props.columns[props.columns.length - 1]);

function categoryLinkData(
    category: ReportYearsCategory,
): Record<string, string | number> {
    return {
        year: targetYear.value,
        destination: 'reports',
        report_category: String(category.id),
        report_panel: 'years',
    };
}

/** A year switch in flight: the links are disabled and a failure shows the same toast as the sidebar switch. */
const panelLinkPending = ref(false);

function onPanelLinkFailed(): void {
    notifyBudgetYearSelectionFailed(t.shell.select_year_failed);
}

// <Link> exposes no HTTP/network error callbacks, only validation `error`.
onUnmounted(
    listenForBudgetYearSelectionFailure(
        () => panelLinkPending.value,
        t.shell.select_year_failed,
    ),
);

function panelLinkLabel(name: string): string {
    return t.reports.years_open_panel
        .replace(':name', name)
        .replace(':year', String(targetYear.value));
}

const hasPanelLink = computed(() =>
    props.section.entries.some((entry) =>
        entry.kind === 'category'
            ? entry.opens_panel
            : entry.categories.some((category) => category.opens_panel),
    ),
);

function toggleLabel(name: string, open: boolean): string {
    return (open ? t.reports.group_collapse : t.reports.group_expand).replace(
        ':name',
        name,
    );
}
</script>

<template>
    <ListSurface flush :data-years-table="type">
        <template #header>
            <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                <h2 class="section-title">{{ title }}</h2>
                <p class="meta text-muted-foreground">
                    {{ t.reports.years_legend_max }}
                </p>
            </div>
        </template>

        <div
            v-if="section.entries.length > 0"
            class="report-trend-scroll"
            role="region"
            tabindex="0"
            :aria-label="regionLabel"
            :style="{ '--report-years-count': columns.length }"
        >
            <div role="table" :aria-label="title">
                <div role="rowgroup">
                    <ReportYearsRow
                        variant="header"
                        :type="type"
                        :columns="columns"
                        :current-year="currentYear"
                        :in-progress-year="inProgressYear"
                        :change-label="changeLabel"
                    >
                        {{ t.reports.category }}
                    </ReportYearsRow>
                </div>

                <div role="rowgroup">
                    <template
                        v-for="entry in section.entries"
                        :key="`${entry.kind}-${entry.id}`"
                    >
                        <ReportYearsRow
                            v-if="entry.kind === 'category'"
                            variant="row"
                            :type="type"
                            :columns="columns"
                            :current-year="currentYear"
                            :cells="entry.cells"
                            :max-years="entry.max_years"
                            :change-tenths="entry.change_tenths"
                            :change-first-year="entry.change_first_year"
                            :muted="entry.is_archived"
                            data-report-row="category"
                        >
                            <Link
                                v-if="entry.opens_panel"
                                :href="BudgetController.selectYear()"
                                method="post"
                                as="button"
                                :data="categoryLinkData(entry)"
                                :preserve-scroll="false"
                                :preserve-state="false"
                                :disabled="panelLinkPending ? true : undefined"
                                :aria-busy="
                                    panelLinkPending ? 'true' : undefined
                                "
                                class="report-name-link cursor-pointer text-left"
                                :aria-label="panelLinkLabel(entry.name)"
                                data-report-years-link
                                @start="panelLinkPending = true"
                                @error="onPanelLinkFailed"
                                @finish="panelLinkPending = false"
                            >
                                <CategoryChip
                                    :name="entry.name"
                                    :icon="entry.icon"
                                    :color="entry.color"
                                />
                            </Link>
                            <CategoryChip
                                v-else
                                :name="entry.name"
                                :icon="entry.icon"
                                :color="entry.color"
                            />
                            <Badge v-if="entry.is_archived" variant="neutral">
                                {{ t.reports.archived_badge }}
                            </Badge>
                        </ReportYearsRow>
                        <div v-else role="none">
                            <ReportYearsRow
                                variant="group"
                                :type="type"
                                :columns="columns"
                                :current-year="currentYear"
                                :cells="entry.cells"
                                :max-years="entry.max_years"
                                :change-tenths="entry.change_tenths"
                                :change-first-year="entry.change_first_year"
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
                            </ReportYearsRow>
                            <div
                                v-show="isOpen(entry.id)"
                                :id="bodyId(entry.id)"
                                role="none"
                            >
                                <ReportYearsRow
                                    v-for="category in entry.categories"
                                    :key="category.id"
                                    variant="row"
                                    nested
                                    :type="type"
                                    :columns="columns"
                                    :current-year="currentYear"
                                    :cells="category.cells"
                                    :max-years="category.max_years"
                                    :change-tenths="category.change_tenths"
                                    :change-first-year="
                                        category.change_first_year
                                    "
                                    :muted="category.is_archived"
                                    data-report-row="category"
                                >
                                    <Link
                                        v-if="category.opens_panel"
                                        :href="BudgetController.selectYear()"
                                        method="post"
                                        as="button"
                                        :data="categoryLinkData(category)"
                                        :preserve-scroll="false"
                                        :preserve-state="false"
                                        :disabled="
                                            panelLinkPending ? true : undefined
                                        "
                                        :aria-busy="
                                            panelLinkPending
                                                ? 'true'
                                                : undefined
                                        "
                                        class="report-name-link cursor-pointer text-left"
                                        :aria-label="
                                            panelLinkLabel(category.name)
                                        "
                                        data-report-years-link
                                        @start="panelLinkPending = true"
                                        @error="onPanelLinkFailed"
                                        @finish="panelLinkPending = false"
                                    >
                                        <CategoryChip
                                            :name="category.name"
                                            :icon="category.icon"
                                            :color="category.color"
                                        />
                                    </Link>
                                    <CategoryChip
                                        v-else
                                        :name="category.name"
                                        :icon="category.icon"
                                        :color="category.color"
                                    />
                                    <Badge
                                        v-if="category.is_archived"
                                        variant="neutral"
                                    >
                                        {{ t.reports.archived_badge }}
                                    </Badge>
                                </ReportYearsRow>
                            </div>
                        </div>
                    </template>

                    <ReportYearsRow
                        variant="total"
                        :type="type"
                        :columns="columns"
                        :current-year="currentYear"
                        :cells="section.total.cells"
                        :max-years="section.total.max_years"
                        :change-tenths="section.total.change_tenths"
                        :change-first-year="section.total.change_first_year"
                    >
                        <span class="section-title">{{
                            t.reports.totals
                        }}</span>
                    </ReportYearsRow>
                </div>
            </div>
        </div>
        <p v-else class="text-muted-foreground px-4 py-3 text-sm">
            {{ t.reports.section_empty }}
        </p>

        <template v-if="section.entries.length > 0" #footer>
            <p class="meta text-muted-foreground">
                {{ t.reports.years_footer }}
            </p>
            <p v-if="hasPanelLink" class="meta text-muted-foreground">
                {{ t.reports.years_footer_panel_hint }}
            </p>
        </template>
    </ListSurface>
</template>
