<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, OctagonX } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import type { Component } from 'vue';
import AccountAvatar from '@/components/AccountAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { useTranslations } from '@/composables/useTranslations';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import { formatMoney } from '@/lib/format';
import { noticePresentation } from '@/lib/dashboard-notice-presentation';
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
const monthPlanRequestPending = ref(false);

function onMonthPlanRequestFailed(): void {
    notifyBudgetYearSelectionFailed(t.dashboard.select_year_failed);
}

// <Link> exposes no HTTP/network error callbacks, only validation `error`.
onUnmounted(
    listenForBudgetYearSelectionFailure(
        () => monthPlanRequestPending.value,
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
    return isSelectYearNotice(notice) && monthPlanRequestPending.value;
}

function isMissingBudgetNotice(notice: DashboardNotice): boolean {
    return (
        notice.kind === 'budget_not_in_execution' &&
        notice.state === 'missing'
    );
}

// A month notice and a draft Budget year both POST `select-year` to open a screen.
function isSelectYearNotice(notice: DashboardNotice): boolean {
    return (
        isMonthNotice(notice) ||
        (notice.kind === 'budget_not_in_execution' &&
            notice.state === 'draft')
    );
}

function openMissingBudget(notice: DashboardNotice): void {
    if (notice.kind === 'budget_not_in_execution') {
        createBudgetDialog.openForYear(notice.year);
    }
}

// `select-year` needs an existing Budget (422), so a missing year is a button
// that opens the "Dodaj rok" dialog instead of a link.
function cardAttributes(notice: DashboardNotice): Record<string, unknown> {
    if (isMissingBudgetNotice(notice)) {
        return { type: 'button' };
    }

    return {
        href: noticeHref(notice),
        data: noticeData(notice),
        as: isSelectYearNotice(notice) ? 'button' : 'a',
        disabled: isNoticePending(notice) ? true : undefined,
    };
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

function cardComponent(notice: DashboardNotice): Component | string {
    return isMissingBudgetNotice(notice) ? 'button' : Link;
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

function monthName(month: number): string {
    return t.budget[`month_name_${month}`] ?? '';
}

function noticeLabel(notice: DashboardNotice): string {
    return t.dashboard[`notice_label_${noticePresentation(notice).labelKey}`];
}

function noticeIdentity(notice: DashboardNotice): string {
    const identity = noticePresentation(notice).identity;

    if (identity.type === 'year') {
        return t.dashboard.notice_identity_budget_year.replace(
            ':year',
            String(identity.year),
        );
    }

    return identity.type === 'month'
        ? capitalized(monthName(identity.month))
        : identity.name;
}

function noticeAvatar(notice: DashboardNotice) {
    const identity = noticePresentation(notice).identity;

    return identity.type === 'account' ? identity.avatar : null;
}

function noticeValue(notice: DashboardNotice): string | null {
    const value = noticePresentation(notice).value;

    if (value === null) {
        return null;
    }

    if (value.type === 'money') {
        return formatMoney(value.minorUnits);
    }

    if (value.type === 'approve_by') {
        return t.dashboard.notice_value_approve_by.replace(
            ':date',
            `${value.date.slice(8, 10)}.${value.date.slice(5, 7)}`,
        );
    }

    if (value.type === 'import_refused') {
        return t.dashboard.notice_value_import_refused.replace(
            ':year',
            String(value.year),
        );
    }

    if (value.type === 'collected_shortfall') {
        return t.dashboard.notice_value_collected_shortfall
            .replace(':shortfall', formatMoney(value.shortfallMinorUnits))
            .replace(':collected', formatMoney(value.collectedMinorUnits));
    }

    return t.dashboard.notice_value_pace
        .replace(':percent', String(value.percent))
        .replace(':days_percent', String(value.daysPercent));
}

function capitalized(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
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
            <component
                :is="cardComponent(notice)"
                v-for="notice in visibleNotices"
                :key="noticeKey(notice)"
                v-bind="cardAttributes(notice)"
                class="dashboard-notice"
                :aria-busy="isNoticePending(notice) ? 'true' : undefined"
                @click="
                    isMissingBudgetNotice(notice)
                        ? openMissingBudget(notice)
                        : null
                "
                @start="monthPlanRequestPending = isSelectYearNotice(notice)"
                @error="
                    isSelectYearNotice(notice)
                        ? onMonthPlanRequestFailed()
                        : null
                "
                @finish="monthPlanRequestPending = false"
            >
                <span
                    class="dashboard-notice-icon"
                    :class="`dashboard-notice-icon-${notice.severity}`"
                >
                    <Spinner v-if="isNoticePending(notice)" class="size-3.5" />
                    <CalendarDays
                        v-else-if="isMonthNotice(notice)"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <OctagonX
                        v-else-if="notice.severity === 'exceeded'"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <AlertTriangle v-else class="size-3.5" aria-hidden="true" />
                </span>

                <span class="dashboard-notice-body">
                    <span
                        class="dashboard-notice-kind meta text-muted-foreground"
                    >
                        {{ noticeLabel(notice) }}
                    </span>
                    <span class="dashboard-notice-identity">
                        <template
                            v-for="avatar in [noticeAvatar(notice)]"
                            :key="noticeKey(notice)"
                        >
                            <AccountAvatar
                                v-if="avatar"
                                size="xs"
                                :type="avatar.type"
                                :bank="avatar.bank"
                                :mark_url="avatar.mark_url"
                            />
                        </template>
                        <span class="truncate">{{
                            noticeIdentity(notice)
                        }}</span>
                    </span>
                </span>

                <span
                    v-if="noticeValue(notice) !== null"
                    class="dashboard-notice-value amount"
                    :class="
                        notice.severity === 'exceeded'
                            ? 'text-destructive'
                            : 'text-warning'
                    "
                >
                    {{ noticeValue(notice) }}
                </span>
            </component>
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
