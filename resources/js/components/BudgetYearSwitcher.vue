<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, Plus } from '@lucide/vue';
import { computed, onUnmounted, ref, useId } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
import { budgetYearMenu } from '@/lib/budget-year-menu';
import { isCurrentSection } from '@/lib/nav-active';
import { reports } from '@/routes';

type Props = {
    budgetYears: number[];
    budgetYear: number | null;
    /** Kitchen sink only: renders the menu open and non-modal. */
    previewOpen?: boolean;
};

const props = withDefaults(defineProps<Props>(), { previewOpen: false });

const t = useTranslations();
const createBudgetDialog = useCreateBudgetDialog();
const labelId = useId();
const valueId = useId();
const pending = ref(false);
const page = usePage();

/** The year switch stays on Reports; elsewhere it opens the Budget. */
const staysOnReports = computed(() =>
    isCurrentSection(reports().url, page.url),
);

/** On the Trend view the switch stays on the Trend. */
const staysOnTrend = computed(
    () =>
        staysOnReports.value &&
        new URL(page.url, 'http://localhost').searchParams.get('view') ===
            'trend',
);

const destination = computed(() => {
    if (staysOnTrend.value) {
        return 'reports-trend';
    }

    return staysOnReports.value ? 'reports' : null;
});

defineExpose({ pending });

const menu = computed(() =>
    budgetYearMenu(
        props.budgetYears,
        props.budgetYear,
        new Date().getFullYear(),
    ),
);

const stopListeningForYearSelectionFailure =
    listenForBudgetYearSelectionFailure(
        () => pending.value,
        t.shell.select_year_failed,
    );

onUnmounted(stopListeningForYearSelectionFailure);

function addBudgetLabel(year: number): string {
    return t.shell.add_budget_for_year.replace(':year', String(year));
}

function addYearText(year: number): string {
    return t.shell.add_year.replace(':year', String(year));
}

function createYear(): void {
    if (pending.value) {
        return;
    }

    createBudgetDialog.openForYear(menu.value.createYear);
}

function selectYear(value: unknown): void {
    const year = Number(value);

    if (pending.value || year === props.budgetYear) {
        return;
    }

    router.post(
        BudgetController.selectYear().url,
        destination.value === null
            ? { year }
            : { year, destination: destination.value },
        {
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
        },
    );
}
</script>

<template>
    <div>
        <SidebarGroupLabel :id="labelId">
            {{ t.shell.budget_year }}
        </SidebarGroupLabel>
        <button
            v-if="menu.years.length === 0"
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
                    :aria-busy="pending"
                    :aria-disabled="pending"
                >
                    <span :id="valueId" class="tabular-nums">
                        {{ budgetYear }}
                    </span>
                    <ChevronsUpDown class="ml-auto size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-(--reka-dropdown-menu-trigger-width) min-w-40 rounded-lg"
                side="bottom"
                align="start"
                :side-offset="4"
            >
                <DropdownMenuRadioGroup
                    :model-value="String(budgetYear)"
                    @update:model-value="selectYear"
                >
                    <DropdownMenuRadioItem
                        v-for="entry in menu.years"
                        :key="entry.year"
                        :value="String(entry.year)"
                        class="tabular-nums"
                        :disabled="pending"
                    >
                        {{ entry.year }}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    :aria-label="addBudgetLabel(menu.createYear)"
                    :disabled="pending"
                    @select="createYear"
                >
                    <Plus />
                    {{ addYearText(menu.createYear) }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
