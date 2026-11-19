<script setup lang="ts">
import { AlertTriangle, CalendarDays, OctagonX } from '@lucide/vue';
import { computed } from 'vue';
import AccountAvatar from '@/components/AccountAvatar.vue';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { noticePresentation } from '@/lib/dashboard-notice-presentation';
import { formatDayMonth, formatMoney } from '@/lib/format';
import type { DashboardNotice } from '@/types/dashboard';

// The inside of one `.dashboard-notice` card, shared by the link and the button cards.
const props = defineProps<{
    notice: DashboardNotice;
    pending: boolean;
}>();

const t = useTranslations();

const presentation = computed(() => noticePresentation(props.notice));

const label = computed(
    () => t.dashboard[`notice_label_${presentation.value.labelKey}`],
);

const identity = computed((): string => {
    const value = presentation.value.identity;

    if (value.type === 'year') {
        return t.dashboard.notice_identity_budget_year.replace(
            ':year',
            String(value.year),
        );
    }

    if (value.type === 'month') {
        return capitalized(t.budget[`month_name_${value.month}`] ?? '');
    }

    return value.name;
});

const avatar = computed(() => {
    const value = presentation.value.identity;

    return value.type === 'account' ? value.avatar : null;
});

const isMonthIdentity = computed(
    () => presentation.value.identity.type === 'month',
);

const formattedValue = computed((): string | null => {
    const value = presentation.value.value;

    if (value === null) {
        return null;
    }

    if (value.type === 'money') {
        return formatMoney(value.minorUnits);
    }

    if (value.type === 'approve_by') {
        return t.dashboard.notice_value_approve_by.replace(
            ':date',
            formatDayMonth(value.date),
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
});

function capitalized(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}
</script>

<template>
    <span class="contents">
        <span
            class="dashboard-notice-icon"
            :class="`dashboard-notice-icon-${notice.severity}`"
        >
            <Spinner v-if="pending" class="size-3.5" />
            <CalendarDays
                v-else-if="isMonthIdentity"
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
            <span class="dashboard-notice-kind meta text-muted-foreground">
                {{ label }}
            </span>
            <span class="dashboard-notice-identity">
                <AccountAvatar
                    v-if="avatar"
                    size="xs"
                    :type="avatar.type"
                    :bank="avatar.bank"
                    :mark_url="avatar.mark_url"
                />
                <span class="truncate">{{ identity }}</span>
            </span>
        </span>

        <span
            v-if="formattedValue !== null"
            class="dashboard-notice-value amount"
            :class="
                notice.severity === 'exceeded'
                    ? 'text-destructive'
                    : 'text-warning'
            "
        >
            {{ formattedValue }}
        </span>
    </span>
</template>
