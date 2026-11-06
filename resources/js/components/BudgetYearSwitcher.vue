<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, useId } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import { SidebarGroupLabel } from '@/components/ui/sidebar';
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { useTranslations } from '@/composables/useTranslations';
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import { budgetYearSegments } from '@/lib/budget-year-segments';
import type { BudgetYearSegment } from '@/lib/budget-year-segments';

type Props = {
    budgetYears: number[];
    budgetYear: number | null;
};

const props = defineProps<Props>();

const t = useTranslations();
const createBudgetDialog = useCreateBudgetDialog();
const labelId = useId();
const pending = ref(false);

defineExpose({ pending });

const segments = computed(() =>
    budgetYearSegments(
        props.budgetYears,
        props.budgetYear,
        new Date().getFullYear(),
    ),
);

const stopListeningForYearSelectionFailure =
    listenForBudgetYearSelectionFailure(
        () => pending.value,
        t.shell.select_year_failed,
    );

onUnmounted(stopListeningForYearSelectionFailure);

function addBudgetLabel(year: number): string {
    return t.shell.add_budget_for_year.replace(':year', String(year));
}

function activate(segment: BudgetYearSegment): void {
    if (pending.value) {
        return;
    }

    if (segment.kind === 'create') {
        createBudgetDialog.openForYear(segment.year);

        return;
    }

    if (segment.year === props.budgetYear) {
        return;
    }

    router.post(
        BudgetController.selectYear().url,
        { year: segment.year },
        {
            onStart: () => {
                pending.value = true;
            },
            onError: () => {
                notifyBudgetYearSelectionFailed(t.shell.select_year_failed);
            },
            onFinish: () => {
                pending.value = false;
            },
        },
    );
}
</script>

<template>
    <div>
        <SidebarGroupLabel :id="labelId">
            {{ t.shell.budget_year }}
        </SidebarGroupLabel>
        <div
            role="group"
            class="sidebar-year-segments"
            :aria-labelledby="labelId"
            :aria-busy="pending"
        >
            <button
                v-for="segment in segments"
                :key="`${segment.kind}-${segment.year}`"
                type="button"
                class="sidebar-year-segment"
                :aria-pressed="
                    segment.kind === 'select' ? segment.selected : undefined
                "
                :aria-label="
                    segment.kind === 'create'
                        ? addBudgetLabel(segment.year)
                        : undefined
                "
                :aria-disabled="pending"
                @click="activate(segment)"
            >
                {{ segment.year }}<template v-if="segment.kind === 'create'">
                    +</template
                >
            </button>
        </div>
    </div>
</template>
