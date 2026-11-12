<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTranslations } from '@/composables/useTranslations';
import { reportMonthRangeLabel } from '@/lib/report-window';
import type { ReportWindow } from '@/types/reports';

/**
 * Notka nad panelem miar okna 12 miesięcy (ADR-0081): których miesięcy brak
 * Budżetu w realizacji i ile miesięcy liczą kwoty. Daty pokrycia i braku
 * przychodzą z serwera; komponent ich nie liczy.
 */
const props = defineProps<{
    window: ReportWindow;
}>();

const t = useTranslations();

const monthNames = computed(() =>
    Array.from(
        { length: 12 },
        (_, index) =>
            t.budget[`month_name_${index + 1}` as keyof typeof t.budget],
    ),
);

const visible = computed(
    () =>
        props.window.covered_month_count > 0 &&
        props.window.covered_month_count < 12 &&
        props.window.missing_from !== null &&
        props.window.missing_through !== null &&
        props.window.covered_from !== null &&
        props.window.covered_through !== null,
);

function range(from: string | null, through: string | null): string {
    return reportMonthRangeLabel(
        from ?? '',
        through ?? '',
        monthNames.value,
        t.reports.window_range,
    );
}

const note = computed(() =>
    t.reports.coverage_note
        .replace(
            ':missing',
            range(props.window.missing_from, props.window.missing_through),
        )
        .replace(
            ':covered',
            range(props.window.covered_from, props.window.covered_through),
        )
        .replace(':count', String(props.window.covered_month_count)),
);
</script>

<template>
    <Alert v-if="visible" variant="note" data-testid="report-coverage-note">
        <AlertTriangle aria-hidden="true" />
        <AlertDescription>
            {{ note }}
        </AlertDescription>
    </Alert>
</template>
