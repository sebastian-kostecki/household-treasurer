<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { budget as budgetRoute } from '@/routes';

const props = defineProps<{
    budgetYear: number;
    viewedMonth: number;
    showArchived: boolean;
}>();

const t = useTranslations();
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
        budgetRoute({
            query: {
                month,
                show_archived: props.showArchived ? 1 : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <Button
            v-for="month in months"
            :key="month"
            type="button"
            size="sm"
            :variant="month === viewedMonth ? 'default' : 'outline'"
            :aria-label="monthAccessibleName(month)"
            class="min-w-10 px-2 tabular-nums"
            @click="selectMonth(month)"
        >
            {{ monthChipLabel(month) }}
        </Button>
    </div>
</template>
