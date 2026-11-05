<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { MonthStripState } from '@/types/month-plan';

const props = defineProps<{
    budgetYear: number;
    viewedMonth: number;
    monthUrl: (month: number) => string;
    /** State of each month, January first; a month without a plan has no dot. */
    monthStates?: (MonthStripState | null)[];
}>();

const t = useTranslations();
const pendingMonth = ref<number | null>(null);
const displayedMonth = computed(() => pendingMonth.value ?? props.viewedMonth);
const months = Array.from({ length: 12 }, (_, index) => index + 1);

function monthChipLabel(month: number): string {
    return t.budget[`month_chip_${month}` as keyof typeof t.budget];
}

function monthState(month: number): MonthStripState | null {
    return props.monthStates?.[month - 1] ?? null;
}

const stateNames: Record<MonthStripState, string> = {
    summary: t.month_plan.month_state_summary,
    approved: t.month_plan.month_state_approved,
    proposal: t.month_plan.month_state_proposal,
};

function monthAccessibleName(month: number): string {
    const monthName = t.budget[`month_name_${month}` as keyof typeof t.budget];
    const state = monthState(month);

    return state === null
        ? `${monthName} ${props.budgetYear}`
        : `${monthName} ${props.budgetYear}, ${stateNames[state]}`;
}

function selectMonth(month: number): void {
    router.get(
        props.monthUrl(month),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                pendingMonth.value = month;
            },
            onFinish: () => {
                if (pendingMonth.value === month) {
                    pendingMonth.value = null;
                }
            },
        },
    );
}
</script>

<template>
    <div
        role="group"
        :aria-label="t.budget.month_picker_label"
        :aria-busy="pendingMonth !== null"
        class="bg-card grid grid-cols-6 gap-1 rounded-lg border p-1 sm:grid-cols-12"
    >
        <Button
            v-for="month in months"
            :key="month"
            type="button"
            size="sm"
            :variant="month === displayedMonth ? 'default' : 'ghost'"
            :aria-label="monthAccessibleName(month)"
            :aria-current="month === displayedMonth ? 'true' : undefined"
            class="relative w-full px-0 tabular-nums"
            @click="selectMonth(month)"
        >
            <span class="meta">{{ monthChipLabel(month) }}</span>
            <span
                v-if="monthState(month) !== null"
                class="month-strip-dot absolute bottom-1 left-1/2 -translate-x-1/2"
                :class="`month-strip-dot-${monthState(month)}`"
                aria-hidden="true"
            />
        </Button>
    </div>
</template>
