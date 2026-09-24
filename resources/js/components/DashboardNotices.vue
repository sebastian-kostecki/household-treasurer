<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, OctagonX } from '@lucide/vue';
import { computed, ref } from 'vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import { formatMoney } from '@/lib/format';
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
          kind: 'paced_month_remaining';
          id: number;
          name: string;
          icon: string;
          color: string;
          category_name: string;
          amount_minor_units: number;
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

function noticeKindLabel(notice: DashboardNotice): string | null {
    if (
        notice.kind === 'negative_account' ||
        notice.kind === 'negative_term_deposit'
    ) {
        return t.dashboard.notice_negative_balance;
    }

    if (notice.kind === 'paced_month_remaining') {
        return t.dashboard.notice_paced_month_remaining;
    }

    if (notice.kind === 'one_off_over_limit') {
        return t.dashboard.notice_one_off_over_limit;
    }

    return null;
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

    if (notice.kind === 'paced_month_remaining') {
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

    return operationsRoute({
        query: {
            category_name: notice.category_name,
            category_type: 'expense',
            date_from: `${props.budgetYear}-01-01`,
            date_to: `${props.budgetYear}-12-31`,
        },
    });
}

function noticeKey(notice: DashboardNotice): string {
    return `${notice.kind}-${notice.id}`;
}
</script>

<template>
    <section class="space-y-3" :aria-labelledby="headingId">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 :id="headingId" class="text-sm font-semibold">
                {{ t.dashboard.attention_zone_title }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="exceededCount > 0"
                    class="bg-destructive/10 text-destructive inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium tabular-nums"
                >
                    <OctagonX class="size-3.5 shrink-0" aria-hidden="true" />
                    {{ t.dashboard.notices_exceeded_group }} ·
                    {{ exceededCount }}
                </span>
                <span
                    v-if="warningCount > 0"
                    class="bg-warning/10 text-warning inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium tabular-nums"
                >
                    <AlertTriangle
                        class="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t.dashboard.notices_warning_group }} ·
                    {{ warningCount }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="notice in visibleNotices"
                :key="noticeKey(notice)"
                :href="noticeHref(notice)"
                class="flex flex-col gap-2 rounded-2xl border p-4 transition-colors"
                :class="severityCardClass(notice.severity)"
            >
                <span
                    v-if="noticeKindLabel(notice)"
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
                        {{ notice.name }}
                    </span>

                    <span
                        class="shrink-0 text-base font-semibold tabular-nums"
                        :class="severityTextClass(notice.severity)"
                    >
                        {{ formatMoney(notice.amount_minor_units) }}
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
