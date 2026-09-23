<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import { formatMoney } from '@/lib/format';
import { operations as operationsRoute } from '@/routes';
import { index as termDepositsIndex } from '@/routes/term-deposits';

export type DashboardNotice =
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
      };

const VISIBLE_NOTICE_LIMIT = 3;

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

function noticeKindLabel(notice: DashboardNotice): string | null {
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
        <h2
            :id="headingId"
            class="flex items-center gap-2 text-sm font-semibold"
        >
            <AlertTriangle
                class="text-destructive size-4 shrink-0"
                aria-hidden="true"
            />
            <span>{{ t.dashboard.attention_zone_title }}</span>
            <span class="text-muted-foreground tabular-nums">
                · {{ notices.length }}
            </span>
        </h2>

        <ListSurface>
            <Link
                v-for="notice in visibleNotices"
                :key="noticeKey(notice)"
                :href="noticeHref(notice)"
                class="border-border/60 hover:bg-foreground/[0.03] flex items-center justify-between gap-3 border-b py-3 transition-colors last:border-b-0"
            >
                <div class="min-w-0 flex-1">
                    <AccountIdentityBlock
                        v-if="notice.kind === 'negative_account'"
                        :name="notice.name"
                        :type="notice.type"
                        :bank="notice.bank"
                        :mark_url="notice.mark_url"
                    >
                        <p class="truncate font-medium">{{ notice.name }}</p>
                    </AccountIdentityBlock>
                    <AccountIdentityBlock
                        v-else-if="notice.kind === 'negative_term_deposit'"
                        type="bank"
                        :name="notice.name"
                        :bank="notice.bank"
                        :mark_url="notice.mark_url"
                    >
                        <p class="truncate font-medium">{{ notice.name }}</p>
                    </AccountIdentityBlock>
                    <CategoryChip
                        v-else
                        :name="notice.name"
                        :icon="notice.icon"
                        :color="notice.color"
                    />
                </div>
                <div class="shrink-0 text-right">
                    <p
                        class="text-destructive font-medium tabular-nums"
                    >
                        {{ formatMoney(notice.amount_minor_units) }}
                    </p>
                    <p
                        v-if="noticeKindLabel(notice)"
                        class="text-muted-foreground text-xs"
                    >
                        {{ noticeKindLabel(notice) }}
                    </p>
                </div>
            </Link>
        </ListSurface>

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
