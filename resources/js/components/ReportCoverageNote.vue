<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTranslations } from '@/composables/useTranslations';
import { reportCoverageNote, reportMonthNamesFrom } from '@/lib/report-window';
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

const note = computed(() =>
    reportCoverageNote(props.window, reportMonthNamesFrom(t.budget), {
        note: t.reports.coverage_note,
        range: t.reports.window_range,
    }),
);
</script>

<template>
    <Alert
        v-if="note !== null"
        variant="note"
        data-testid="report-coverage-note"
    >
        <AlertTriangle aria-hidden="true" />
        <AlertDescription>
            {{ note }}
        </AlertDescription>
    </Alert>
</template>
