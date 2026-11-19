<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, OctagonX } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import DashboardNoticeCardBody from '@/components/DashboardNoticeCardBody.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { useTranslations } from '@/composables/useTranslations';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import { operations as operationsRoute } from '@/routes';
import { index as termDepositsIndex } from '@/routes/term-deposits';
import type { DashboardNotice } from '@/types/dashboard';

// Two rows of the card grid's widest breakpoint (3 columns).
const VISIBLE_NOTICE_LIMIT = 6;

const props = defineProps<{
    notices: DashboardNotice[];
    budgetYear: number;
    currentMonth: number | null;
}>();

const t = useTranslations();
const isExpanded = ref(false);
const headingId = 'dashboard-notices-heading';

const hiddenNoticeCount = computed(() =>
    Math.max(props.notices.length - VISIBLE_NOTICE_LIMIT, 0),
);

const createBudgetDialog = useCreateBudgetDialog();
const selectYearRequestPending = ref(false);

function onSelectYearRequestFailed(): void {
    notifyBudgetYearSelectionFailed(t.dashboard.select_year_failed);
}

// <Link> exposes no HTTP/network error callbacks, only validation `error`.
onUnmounted(
    listenForBudgetYearSelectionFailure(
        () => selectYearRequestPending.value,
        t.dashboard.select_year_failed,
    ),
);

const visibleNotices = computed(() => {
    if (isExpanded.value || props.notices.length <= VISIBLE_NOTICE_LIMIT) {
        return props.notices;
    }

    return props.notices.slice(0, VISIBLE_NOTICE_LIMIT);
});

const showExpander = computed(
    () => hiddenNoticeCount.value > 0 && !isExpanded.value,
);

const exceededCount = computed(
    () =>
        props.notices.filter((notice) => notice.severity === 'exceeded').length,
);

const warningCount = computed(
    () =>
        props.notices.filter((notice) => notice.severity === 'warning').length,
);

function isNoticePending(notice: DashboardNotice): boolean {
    return isSelectYearNotice(notice) && selectYearRequestPending.value;
}

function isMissingBudgetNotice(
    notice: DashboardNotice,
): notice is Extract<DashboardNotice, { kind: 'budget_not_in_execution' }> {
    return (
        notice.kind === 'budget_not_in_execution' && notice.state === 'missing'
    );
}

// A month notice and a draft Budget year both POST `select-year` to open a screen.
function isSelectYearNotice(notice: DashboardNotice): boolean {
    return (
        isMonthNotice(notice) ||
        (notice.kind === 'budget_not_in_execution' && notice.state === 'draft')
    );
}

function noticeData(
    notice: DashboardNotice,
): { year: number; destination: string } | undefined {
    if (isMonthNotice(notice)) {
        return { year: props.budgetYear, destination: 'month-plan' };
    }

    if (notice.kind === 'budget_not_in_execution') {
        return { year: notice.year, destination: 'budget' };
    }

    return undefined;
}

function isMonthNotice(notice: DashboardNotice): notice is Extract<
    DashboardNotice,
    {
        kind:
            | 'month_to_spend_negative'
            | 'month_plan_not_approved'
            | 'month_pace_ahead'
            | 'collected_not_covered';
    }
> {
    return (
        notice.kind === 'month_to_spend_negative' ||
        notice.kind === 'month_plan_not_approved' ||
        notice.kind === 'month_pace_ahead' ||
        notice.kind === 'collected_not_covered'
    );
}

const showCollapse = computed(
    () => isExpanded.value && props.notices.length > VISIBLE_NOTICE_LIMIT,
);

function toIsoDate(year: number, month: number, day: number): string {
    const monthString = String(month).padStart(2, '0');
    const dayString = String(day).padStart(2, '0');

    return `${year}-${monthString}-${dayString}`;
}

function monthDateRange(
    year: number,
    month: number,
): { date_from: string; date_to: string } {
    const lastDay = new Date(year, month, 0).getDate();

    return {
        date_from: toIsoDate(year, month, 1),
        date_to: toIsoDate(year, month, lastDay),
    };
}

function noticeHref(notice: DashboardNotice) {
    if (isMonthNotice(notice) || notice.kind === 'budget_not_in_execution') {
        return BudgetController.selectYear();
    }

    if (notice.kind === 'negative_account') {
        return operationsRoute({
            query: {
                account_id: notice.id,
            },
        });
    }

    if (notice.kind === 'negative_term_deposit') {
        return termDepositsIndex();
    }

    if (notice.kind === 'month_item_over_plan') {
        const month = props.currentMonth;

        if (month === null) {
            return operationsRoute();
        }

        const range = monthDateRange(props.budgetYear, month);

        return operationsRoute({
            query: {
                category_id: notice.id,
                date_from: range.date_from,
                date_to: range.date_to,
            },
        });
    }

    if (notice.kind === 'even_annual_over_limit') {
        return operationsRoute({
            query: {
                category_id: notice.id,
                date_from: `${props.budgetYear}-01-01`,
                date_to: `${props.budgetYear}-12-31`,
            },
        });
    }

    return notice satisfies never;
}

function noticeKey(notice: DashboardNotice): string {
    if ('id' in notice) {
        return `${notice.kind}-${notice.id}`;
    }

    if (notice.kind === 'budget_not_in_execution') {
        return `${notice.kind}-${notice.year}-${notice.state}`;
    }

    return `${notice.kind}-${notice.month}`;
}
</script>

<template>
    <section class="space-y-3" :aria-labelledby="headingId">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 :id="headingId" class="section-title">
                {{ t.dashboard.attention_zone_title }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <Badge
                    v-if="exceededCount > 0"
                    variant="destructive-soft"
                    class="gap-1.5 tabular-nums"
                >
                    <OctagonX class="size-3.5 shrink-0" aria-hidden="true" />
                    {{ t.dashboard.notices_exceeded_group }} ·
                    {{ exceededCount }}
                </Badge>
                <Badge
                    v-if="warningCount > 0"
                    variant="warning"
                    class="gap-1.5 tabular-nums"
                >
                    <AlertTriangle
                        class="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t.dashboard.notices_warning_group }} ·
                    {{ warningCount }}
                </Badge>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template v-for="notice in visibleNotices" :key="noticeKey(notice)">
                <!-- `select-year` needs an existing Budget (422), so a missing
                     year opens the "Dodaj rok" dialog instead of a link. -->
                <button
                    v-if="isMissingBudgetNotice(notice)"
                    type="button"
                    class="dashboard-notice"
                    aria-haspopup="dialog"
                    :disabled="selectYearRequestPending"
                    @click="createBudgetDialog.openForYear(notice.year)"
                >
                    <DashboardNoticeCardBody
                        :notice="notice"
                        :pending="false"
                    />
                </button>
                <Link
                    v-else
                    :href="noticeHref(notice)"
                    :data="noticeData(notice)"
                    :as="isSelectYearNotice(notice) ? 'button' : 'a'"
                    :disabled="isNoticePending(notice) ? true : undefined"
                    class="dashboard-notice"
                    :aria-busy="isNoticePending(notice) ? 'true' : undefined"
                    @start="
                        selectYearRequestPending = isSelectYearNotice(notice)
                    "
                    @error="
                        isSelectYearNotice(notice)
                            ? onSelectYearRequestFailed()
                            : null
                    "
                    @finish="selectYearRequestPending = false"
                >
                    <DashboardNoticeCardBody
                        :notice="notice"
                        :pending="isNoticePending(notice)"
                    />
                </Link>
            </template>
        </div>

        <div v-if="showExpander || showCollapse">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="isExpanded = !isExpanded"
            >
                {{
                    showCollapse
                        ? t.dashboard.show_fewer_notices
                        : t.dashboard.show_all_notices.replace(
                              ':count',
                              String(notices.length),
                          )
                }}
            </Button>
        </div>
    </section>
</template>
