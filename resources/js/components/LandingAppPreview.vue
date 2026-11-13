<script setup lang="ts">
import { ChevronsUpDown, Search } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import ListSurface from '@/components/ListSurface.vue';
import StatPanel from '@/components/StatPanel.vue';
import { Badge } from '@/components/ui/badge';
import { useAppViews } from '@/composables/useAppViews';
import { useTranslations } from '@/composables/useTranslations';
import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { DashboardCategory } from '@/lib/dashboard-categories';
import { formatMoney, formatMoneyAmount } from '@/lib/format';

const t = useTranslations();

const yearElapsedPercent = 84;

const name = usePage().props.name;
const views = useAppViews();

/** A neutral sample user and year, not the visitor's data. */
const sampleUser = {
    initials: 'JK',
    name: 'Jan Kowalski',
    email: 'jan@example.com',
};
const selectedYear = 2026;

const shortcutsHint = t.shell.shortcuts_hint.replace(
    ':last',
    String(views.length),
);

const metrics = [
    {
        label: t.landing.preview_metric_to_spend,
        value: formatMoney(2_340_50),
        toneClass: 'landing-kpi',
    },
    {
        label: t.dashboard.liquidity_title,
        value: formatMoney(27_770_50),
        toneClass: 'landing-kpi',
    },
    {
        label: t.dashboard.total_funds,
        value: formatMoney(47_770_50),
        toneClass: 'landing-kpi',
    },
];

/** A mock-up, not the user's data. */
const categories: DashboardCategory[] = [
    {
        id: 1,
        name: t.landing.preview_category_food,
        icon: 'shopping-cart',
        color: DEFAULT_CATEGORY_COLOR,
        initial_plan_minor_units: 18_000_00,
        current_plan_minor_units: 18_000_00,
        execution_minor_units: 14_912_40,
        remaining_minor_units: 3_087_60,
        percent_complete: 83,
        is_over_limit: false,
        is_archived: false,
    },
    {
        id: 2,
        name: t.landing.preview_category_housing,
        icon: 'home',
        color: DEFAULT_CATEGORY_COLOR,
        initial_plan_minor_units: 30_000_00,
        current_plan_minor_units: 30_000_00,
        execution_minor_units: 25_000_00,
        remaining_minor_units: 5_000_00,
        percent_complete: 83,
        is_over_limit: false,
        is_archived: false,
    },
    {
        id: 3,
        name: t.landing.preview_category_transport,
        icon: 'car',
        color: DEFAULT_CATEGORY_COLOR,
        initial_plan_minor_units: 6_000_00,
        current_plan_minor_units: 6_000_00,
        execution_minor_units: 5_520_00,
        remaining_minor_units: 480_00,
        percent_complete: 92,
        is_over_limit: false,
        is_archived: false,
    },
    {
        id: 4,
        name: t.landing.preview_category_health,
        icon: 'stethoscope',
        color: DEFAULT_CATEGORY_COLOR,
        initial_plan_minor_units: 2_400_00,
        current_plan_minor_units: 2_400_00,
        execution_minor_units: 2_720_00,
        remaining_minor_units: -320_00,
        percent_complete: 113,
        is_over_limit: true,
        is_archived: false,
    },
];

const yearProgress = t.budget.year_progress.replace(
    ':percent',
    String(yearElapsedPercent),
);
</script>

<template>
    <div class="landing-preview" aria-hidden="true">
        <div class="landing-preview-side">
            <div class="landing-preview-nav-item h-12">
                <BrandMark />
                <span class="text-sidebar-strong truncate font-semibold">
                    {{ name }}
                </span>
            </div>
            <div class="landing-preview-nav-item sidebar-search">
                <Search />
                <span class="truncate">{{ t.shell.search_and_go }}</span>
                <kbd class="kbd">⌘K</kbd>
            </div>
            <div>
                <div class="label-caps flex h-8 items-center px-2">
                    {{ t.shell.budget_year }}
                </div>
                <div class="sidebar-year-trigger">
                    <span class="tabular-nums">{{ selectedYear }}</span>
                    <ChevronsUpDown class="ml-auto size-4" />
                </div>
            </div>
            <div class="flex flex-1 flex-col gap-0.5">
                <div class="label-caps flex h-8 items-center px-2">
                    {{ t.nav.menu }}
                </div>
                <div
                    v-for="(view, index) in views"
                    :key="view.title"
                    class="landing-preview-nav-item"
                    :data-active="index === 0"
                >
                    <component :is="view.icon" />
                    <span class="truncate">{{ view.title }}</span>
                    <kbd class="kbd">{{ view.shortcut }}</kbd>
                </div>
            </div>
            <div class="bg-sidebar-border mx-2 h-px" />
            <p class="sidebar-hint">{{ shortcutsHint }}</p>
            <div class="landing-preview-nav-item text-sidebar-strong h-12">
                <span
                    class="bg-sidebar-accent text-sidebar-accent-foreground meta flex size-7 shrink-0 items-center justify-center rounded-full font-semibold"
                >
                    {{ sampleUser.initials }}
                </span>
                <span class="grid min-w-0 flex-1 text-sm leading-tight">
                    <span class="truncate font-medium">{{
                        sampleUser.name
                    }}</span>
                    <span class="meta text-sidebar-foreground truncate">
                        {{ sampleUser.email }}
                    </span>
                </span>
                <ChevronsUpDown class="ml-auto" />
            </div>
        </div>

        <div class="landing-preview-main">
            <div class="flex items-center justify-between gap-2">
                <span class="section-title">{{ t.nav.dashboard }}</span>
                <Badge variant="brand">{{ t.budget.phase_execution }}</Badge>
            </div>

            <StatPanel :metrics="metrics" />

            <ListSurface flush>
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <span class="section-title">
                            {{ t.dashboard.expenses }}
                            {{ t.landing.preview_year }}
                        </span>
                        <span class="meta text-muted-foreground">
                            {{ yearProgress }}
                        </span>
                    </div>
                </template>

                <div class="landing-preview-row table-header">
                    <span>{{ t.budget.category }}</span>
                    <span class="landing-preview-row-plan">
                        {{ t.budget.current_plan }}
                    </span>
                    <span class="text-right">
                        {{ t.dashboard.execution_column }}
                    </span>
                    <span>{{ t.dashboard.percent_complete }}</span>
                </div>

                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="landing-preview-row"
                >
                    <div
                        class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"
                    >
                        <CategoryChip
                            :name="category.name"
                            :icon="category.icon"
                            :color="category.color"
                            size="sm"
                            text-size="sm"
                        />
                        <Badge
                            v-if="category.is_over_limit"
                            variant="destructive-soft"
                        >
                            {{ t.budget.over_limit_badge }}
                        </Badge>
                    </div>
                    <span class="landing-preview-row-plan amount">
                        {{
                            formatMoneyAmount(category.current_plan_minor_units)
                        }}
                    </span>
                    <span class="amount text-right">
                        {{ formatMoneyAmount(category.execution_minor_units) }}
                    </span>
                    <ExecutionMeter
                        :percent-complete="category.percent_complete"
                        category-type="expense"
                        :is-over-limit="category.is_over_limit"
                        :year-elapsed-percent="yearElapsedPercent"
                    />
                </div>

                <template #footer>
                    <p class="meta text-muted-foreground">
                        {{ t.common.amounts_in_zl }}
                    </p>
                </template>
            </ListSurface>
        </div>
    </div>
</template>
