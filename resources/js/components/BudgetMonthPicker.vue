<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { budget as budgetRoute } from '@/routes';

const props = defineProps<{
    budgetYear: number;
    viewedMonth: number;
    showArchived: boolean;
    currentMonth: number | null;
    monthUrl?: (month: number) => string;
}>();

const t = useTranslations();
const pendingMonth = ref<number | null>(null);
const displayedMonth = computed(() => pendingMonth.value ?? props.viewedMonth);
const months = Array.from({ length: 12 }, (_, index) => index + 1);

function monthChipLabel(month: number): string {
    return t.budget[`month_chip_${month}` as keyof typeof t.budget];
}

function monthAccessibleName(month: number): string {
    const monthName = t.budget[`month_name_${month}` as keyof typeof t.budget];

    return `${monthName} ${props.budgetYear}`;
}

function selectMonth(month: number): void {
    router.get(
        props.monthUrl?.(month) ??
            budgetRoute({
                query: {
                    month,
                    show_archived: props.showArchived ? 1 : undefined,
                },
            }),
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
        class="border-border/60 bg-muted/40 grid grid-cols-6 gap-1 rounded-xl border p-1 sm:grid-cols-12"
    >
        <Button
            v-for="month in months"
            :key="month"
            type="button"
            size="sm"
            :variant="month === displayedMonth ? 'default' : 'ghost'"
            :aria-label="monthAccessibleName(month)"
            :aria-current="month === displayedMonth ? 'true' : undefined"
            class="relative w-full rounded-lg px-0 text-xs tabular-nums"
            @click="selectMonth(month)"
        >
            {{ monthChipLabel(month) }}
            <span
                v-if="month === currentMonth && month !== displayedMonth"
                class="bg-primary absolute bottom-1 left-1/2 size-1 -translate-x-1/2 rounded-full"
                aria-hidden="true"
            />
        </Button>
    </div>
</template>
