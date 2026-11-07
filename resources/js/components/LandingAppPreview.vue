<script setup lang="ts">
import {
    ArrowLeftRight,
    CalendarDays,
    Landmark,
    LayoutGrid,
    PiggyBank,
    Wallet,
} from '@lucide/vue';
import BrandMark from '@/components/BrandMark.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import ListSurface from '@/components/ListSurface.vue';
import StatPanel from '@/components/StatPanel.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { DashboardCategory } from '@/lib/dashboard-categories';
import { formatMoney, formatMoneyAmount } from '@/lib/format';

const t = useTranslations();

const yearElapsedPercent = 84;

const navItems = [
    { icon: LayoutGrid, label: t.nav.dashboard, active: true },
    { icon: PiggyBank, label: t.nav.budget, active: false },
    { icon: CalendarDays, label: t.nav.month_plan, active: false },
    { icon: Wallet, label: t.nav.accounts, active: false },
    { icon: Landmark, label: t.nav.term_deposits, active: false },
    { icon: ArrowLeftRight, label: t.nav.operations, active: false },
];

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
            <BrandMark />
            <div class="flex flex-col gap-0.5">
                <div
                    v-for="item in navItems"
                    :key="item.label"
                    class="landing-preview-nav-item"
                    :data-active="item.active"
                >
                    <component :is="item.icon" class="size-4 shrink-0" />
                    <span>{{ item.label }}</span>
                </div>
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
                        {{ t.budget.amounts_in_zl }}
                    </p>
                </template>
            </ListSurface>
        </div>
    </div>
</template>
