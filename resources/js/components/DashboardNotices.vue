<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, OctagonX } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import { formatMoney } from '@/lib/format';
import { monthElapsedPercent } from '@/lib/month-pace-calculator';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import { operations as operationsRoute } from '@/routes';
import { index as termDepositsIndex } from '@/routes/term-deposits';

export type DashboardNoticeSeverity = 'exceeded' | 'warning';

export type DashboardNotice = {
    severity: DashboardNoticeSeverity;
} & (
    | {
          kind: 'negative_account';
          id: number;
          name: string;
          type: 'bank' | 'cash';
          bank: AccountBank | null;
          mark_url: string | null;
          amount_minor_units: number;
      }
    | {
          kind: 'negative_term_deposit';
          id: number;
          name: string;
          bank: AccountBank;
          mark_url: string | null;
          amount_minor_units: number;
      }
    | {
          kind: 'month_to_spend_negative';
          month: number;
          amount_minor_units: number;
      }
    | {
          kind: 'month_item_over_plan';
          id: number;
          name: string;
          icon: string;
          color: string;
          category_name: string;
          amount_minor_units: number;
      }
    | {
          kind: 'month_plan_not_approved';
          month: number;
      }
    | {
          kind: 'month_pace_ahead';
          month: number;
          percent: number;
          elapsed_day: number;
          days_in_month: number;
      }
    | {
          kind: 'one_off_over_limit';
          id: number;
          name: string;
          icon: string;
          color: string;
          category_name: string;
          amount_minor_units: number;
      }
);

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

function severityTextClass(severity: DashboardNoticeSeverity): string {
    return severity === 'exceeded' ? 'text-destructive' : 'text-warning';
}

function severityCardClass(severity: DashboardNoticeSeverity): string {
    return severity === 'exceeded'
        ? 'border-destructive/25 bg-destructive/5 hover:bg-destructive/10'
        : 'border-warning/30 bg-warning/5 hover:bg-warning/10';
}

function noticeKindLabel(notice: DashboardNotice): string {
    switch (notice.kind) {
        case 'negative_account':
        case 'negative_term_deposit':
            return t.dashboard.notice_negative_balance;
        case 'month_to_spend_negative':
            return t.dashboard.notice_month_to_spend_negative;
        case 'month_item_over_plan':
            return t.dashboard.notice_month_item_over_plan;
        case 'month_plan_not_approved':
            return t.dashboard.notice_month_plan_not_approved;
        case 'month_pace_ahead':
            return t.dashboard.notice_month_pace_ahead;
        case 'one_off_over_limit':
            return t.dashboard.notice_one_off_over_limit;
    }
}

function isMonthNotice(notice: DashboardNotice): notice is Extract<
    DashboardNotice,
    {
        kind:
            | 'month_to_spend_negative'
            | 'month_plan_not_approved'
            | 'month_pace_ahead';
    }
> {
    return (
        notice.kind === 'month_to_spend_negative' ||
        notice.kind === 'month_plan_not_approved' ||
        notice.kind === 'month_pace_ahead'
    );
}

function monthName(month: number): string {
    return t.budget[`month_name_${month}`] ?? '';
}

function noticeValue(notice: DashboardNotice): string | null {
    if (notice.kind === 'month_plan_not_approved') {
        return null;
    }

    if (notice.kind === 'month_pace_ahead') {
        return t.dashboard.notice_month_pace_value
            .replace(':pace', String(notice.percent))
            .replace(
                ':elapsed',
                String(
                    monthElapsedPercent(
                        notice.elapsed_day,
                        notice.days_in_month,
                    ),
                ),
            );
    }

    return formatMoney(notice.amount_minor_units);
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
    if (isMonthNotice(notice)) {
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
                category_name: notice.category_name,
                category_type: 'expense',
                date_from: range.date_from,
                date_to: range.date_to,
            },
        });
    }

    if (notice.kind === 'one_off_over_limit') {
        return operationsRoute({
            query: {
                category_name: notice.category_name,
                category_type: 'expense',
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

    return `${notice.kind}-${notice.month}`;
}
</script>

<template>
    <section class="space-y-3" :aria-labelledby="headingId">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 :id="headingId" class="text-sm font-semibold">
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
            <Link
                v-for="notice in visibleNotices"
                :key="noticeKey(notice)"
                :href="noticeHref(notice)"
                :data="
                    isMonthNotice(notice)
                        ? { year: budgetYear, destination: 'month-plan' }
                        : undefined
                "
                :as="isMonthNotice(notice) ? 'button' : 'a'"
                :disabled="
                    isMonthNotice(notice) && monthPlanRequestPending
                        ? true
                        : undefined
                "
                class="focus-visible:outline-ring flex h-full w-full flex-col gap-2 rounded-2xl border p-4 text-left transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid disabled:opacity-60"
                :class="severityCardClass(notice.severity)"
                @start="monthPlanRequestPending = isMonthNotice(notice)"
                @error="
                    isMonthNotice(notice) ? onMonthPlanRequestFailed() : null
                "
                @finish="monthPlanRequestPending = false"
            >
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-medium"
                    :class="severityTextClass(notice.severity)"
                >
                    <OctagonX
                        v-if="notice.severity === 'exceeded'"
                        class="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <AlertTriangle
                        v-else
                        class="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ noticeKindLabel(notice) }}
                </span>

                <div class="flex min-w-0 items-center gap-2">
                    <AccountIdentityBlock
                        v-if="notice.kind === 'negative_account'"
                        class="shrink-0"
                        size="sm"
                        :name="notice.name"
                        :type="notice.type"
                        :bank="notice.bank"
                        :mark_url="notice.mark_url"
                    />
                    <AccountIdentityBlock
                        v-else-if="notice.kind === 'negative_term_deposit'"
                        class="shrink-0"
                        size="sm"
                        type="bank"
                        :name="notice.name"
                        :bank="notice.bank"
                        :mark_url="notice.mark_url"
                    />
                    <span
                        v-else-if="isMonthNotice(notice)"
                        class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg"
                        aria-hidden="true"
                    >
                        <CalendarDays class="size-4" />
                    </span>
                    <CategoryChip
                        v-else
                        class="shrink-0"
                        :name="notice.name"
                        :icon="notice.icon"
                        :color="notice.color"
                        size="sm"
                        :show-name="false"
                    />

                    <span
                        class="min-w-0 flex-1 truncate text-base font-semibold"
                    >
                        {{
                            isMonthNotice(notice)
                                ? monthName(notice.month)
                                : notice.name
                        }}
                    </span>

                    <span
                        v-if="noticeValue(notice) !== null"
                        class="shrink-0 text-base font-semibold tabular-nums"
                        :class="severityTextClass(notice.severity)"
                    >
                        {{ noticeValue(notice) }}
                    </span>
                </div>
            </Link>
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
