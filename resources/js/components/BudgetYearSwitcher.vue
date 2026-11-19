<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus } from '@lucide/vue';
import { computed, onUnmounted, ref, useId } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarGroupLabel } from '@/components/ui/sidebar';
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { useTranslations } from '@/composables/useTranslations';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import { budgetYearMenu, reportYearSwitchParams } from '@/lib/budget-year-menu';
import type { BudgetYearMenuEntry } from '@/lib/budget-year-menu';
import { isCurrentSection } from '@/lib/nav-active';
import { reports } from '@/routes';
import type { BudgetYearEntry } from '@/types/shell';

type Props = {
    budgetYears: BudgetYearEntry[];
    budgetYear: number | null;
    /** The year of the App Day (`shell.today`), the one the "bieżący" mark follows. */
    currentYear: number;
    /** Kitchen sink only: renders the menu open and non-modal. */
    previewOpen?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    previewOpen: false,
});

const t = useTranslations();
const createBudgetDialog = useCreateBudgetDialog();
const labelId = useId();
const valueId = useId();
const statesId = useId();
const captionId = useId();
const pending = ref(false);
const page = usePage();

/** The year switch stays on Reports; elsewhere it opens the Budget. */
const staysOnReports = computed(() =>
    isCurrentSection(reports().url, page.url),
);

/** On the Trend and Years views the switch stays on that view. */
const reportsView = computed(() =>
    staysOnReports.value
        ? new URL(page.url, 'http://localhost').searchParams.get('view')
        : null,
);

const destination = computed(() => {
    if (!staysOnReports.value) {
        return null;
    }

    if (reportsView.value === 'trend') {
        return 'reports-trend';
    }

    return reportsView.value === 'years' ? 'reports-years' : 'reports';
});

defineExpose({ pending });

const menu = computed(() =>
    budgetYearMenu(props.budgetYears, props.budgetYear, props.currentYear),
);

/** The headings appear only when there are older years to set apart. */
const groups = computed(() =>
    menu.value.older.length === 0
        ? [{ key: 'all', heading: null, entries: menu.value.recent }]
        : [
              {
                  key: 'recent',
                  heading: t.shell.years_recent,
                  entries: menu.value.recent,
              },
              {
                  key: 'older',
                  heading: t.shell.years_older,
                  entries: menu.value.older,
              },
          ],
);

const stopListeningForYearSelectionFailure =
    listenForBudgetYearSelectionFailure(
        () => pending.value,
        t.shell.select_year_failed,
    );

onUnmounted(stopListeningForYearSelectionFailure);

function hasState(entry: BudgetYearMenuEntry | null): boolean {
    return entry !== null && (entry.current || entry.draft);
}

function rowStateId(year: number): string {
    return `${statesId}-${year}`;
}

function addBudgetLabel(year: number): string {
    return t.shell.add_budget_for_year.replace(':year', String(year));
}

function addYearText(year: number): string {
    return t.shell.add_year.replace(':year', String(year));
}

function addYearSourceText(year: number): string {
    return t.shell.add_year_source.replace(':year', String(year));
}

function createYear(): void {
    if (pending.value) {
        return;
    }

    createBudgetDialog.openForYear(menu.value.createYear);
}

/** The Reports year switch also carries the selected Category and the Ranking mode of the address. */
function selectYearPayload(year: number): Record<string, string | number> {
    if (destination.value === null) {
        return { year };
    }

    const payload: Record<string, string | number> = {
        year,
        destination: destination.value,
    };

    if (destination.value === 'reports') {
        return { ...payload, ...reportYearSwitchParams(page.url) };
    }

    return payload;
}

function selectYear(value: unknown): void {
    const year = Number(value);

    if (pending.value || year === props.budgetYear) {
        return;
    }

    router.post(BudgetController.selectYear().url, selectYearPayload(year), {
        preserveScroll: false,
        onStart: () => {
            pending.value = true;
        },
        onError: () => {
            notifyBudgetYearSelectionFailed(t.shell.select_year_failed);
        },
        onFinish: () => {
            pending.value = false;
        },
    });
}
</script>

<template>
    <div>
        <SidebarGroupLabel :id="labelId">
            {{ t.shell.budget_year }}
        </SidebarGroupLabel>
        <button
            v-if="menu.recent.length === 0"
            type="button"
            class="sidebar-year-trigger"
            :aria-label="addBudgetLabel(menu.createYear)"
            :aria-busy="pending"
            :aria-disabled="pending"
            @click="createYear"
        >
            <Plus class="size-4" />
            <span>{{ addYearText(menu.createYear) }}</span>
        </button>
        <DropdownMenu v-else :default-open="previewOpen" :modal="!previewOpen">
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="sidebar-year-trigger"
                    :aria-labelledby="`${labelId} ${valueId}`"
                    :aria-describedby="
                        hasState(menu.selected) ? statesId : undefined
                    "
                    :aria-busy="pending"
                    :aria-disabled="pending"
                >
                    <span :id="valueId" class="font-mono tabular-nums">
                        {{ budgetYear }}
                    </span>
                    <span
                        v-if="menu.selected !== null && hasState(menu.selected)"
                        :id="statesId"
                        class="sidebar-year-states"
                        aria-hidden="true"
                    >
                        <span
                            v-if="menu.selected.current"
                            class="sidebar-year-state sidebar-year-state-current"
                        >
                            {{ t.shell.year_current }}
                        </span>
                        <span
                            v-if="menu.selected.draft"
                            class="sidebar-year-state sidebar-year-state-draft"
                        >
                            {{ t.shell.year_draft }}
                        </span>
                    </span>
                    <ChevronsUpDown
                        class="size-4 shrink-0"
                        :class="{ 'ml-auto': !hasState(menu.selected) }"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="sidebar-year-menu w-(--reka-dropdown-menu-trigger-width) min-w-40"
                side="bottom"
                align="start"
                :side-offset="4"
            >
                <DropdownMenuRadioGroup
                    :model-value="String(budgetYear)"
                    @update:model-value="selectYear"
                >
                    <template v-for="group in groups" :key="group.key">
                        <DropdownMenuLabel
                            v-if="group.heading !== null"
                            class="sidebar-year-menu-group"
                        >
                            {{ group.heading }}
                        </DropdownMenuLabel>
                        <DropdownMenuRadioItem
                            v-for="entry in group.entries"
                            :key="entry.year"
                            :value="String(entry.year)"
                            class="sidebar-year-menu-item sidebar-year-menu-row"
                            :aria-describedby="
                                hasState(entry)
                                    ? rowStateId(entry.year)
                                    : undefined
                            "
                            :disabled="pending"
                        >
                            <template #indicator-icon>
                                <Check class="size-4" />
                            </template>
                            {{ entry.year }}
                            <span
                                v-if="hasState(entry)"
                                :id="rowStateId(entry.year)"
                                class="sidebar-year-states"
                                aria-hidden="true"
                            >
                                <span
                                    v-if="entry.current"
                                    class="sidebar-year-menu-note"
                                >
                                    {{ t.shell.year_current }}
                                </span>
                                <span
                                    v-if="entry.draft"
                                    class="sidebar-year-state sidebar-year-state-draft"
                                >
                                    {{ t.shell.year_draft }}
                                </span>
                            </span>
                        </DropdownMenuRadioItem>
                    </template>
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator class="sidebar-year-menu-separator" />
                <DropdownMenuItem
                    class="sidebar-year-menu-item"
                    :aria-label="addBudgetLabel(menu.createYear)"
                    :aria-describedby="
                        menu.createSourceYear !== null ? captionId : undefined
                    "
                    :disabled="pending"
                    @select="createYear"
                >
                    <Plus />
                    {{ addYearText(menu.createYear) }}
                </DropdownMenuItem>
                <small
                    v-if="menu.createSourceYear !== null"
                    :id="captionId"
                    class="sidebar-year-menu-caption"
                    aria-hidden="true"
                >
                    {{ addYearSourceText(menu.createSourceYear) }}
                </small>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
