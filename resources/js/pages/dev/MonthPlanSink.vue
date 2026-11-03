<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import MonthPlanItemRow from '@/components/MonthPlanItemRow.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    expenseRows,
    incomeRows,
    overPlanExpense,
    runningExpense,
} from '@/lib/month-plan-fixtures';
import { minorUnitsToMoneyInput } from '@/lib/format';
import type { MonthPlanItem } from '@/types/month-plan';

const t = useTranslations();

const stateMatrix = [
    'default',
    'hover',
    'focus-visible',
    'disabled',
    'error',
    'empty',
    'loading',
];

const amounts = ref<Record<number, string>>(
    Object.fromEntries(
        [runningExpense, overPlanExpense].map((row) => [
            row.category_id,
            minorUnitsToMoneyInput(row.plan_minor_units),
        ]),
    ),
);

function rowReading(row: MonthPlanItem): {
    deviation: number | null;
    overPlan: boolean;
} {
    return {
        deviation: row.deviation_minor_units,
        overPlan: row.is_over_plan,
    };
}

const direction = ref<'set_aside' | 'draw'>('set_aside');
</script>

<template>
    <Head title="Kitchen sink: plan miesiąca" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:gap-8">
        <header class="space-y-1">
            <h1 class="text-lg font-semibold">Kitchen sink: plan miesiąca</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko APP_ENV=local). Pokazuje części
                widoku /month-plan z danych testowych.
            </p>
        </header>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Wiersze: odczyt</h2>
            <ListSurface :title="t.month_plan.income_table">
                <MonthPlanItemRow
                    v-for="row in incomeRows"
                    :key="row.category_id"
                    :row="row"
                    :editable="false"
                    :deviation="rowReading(row).deviation"
                    :over-plan="rowReading(row).overPlan"
                />
            </ListSurface>
            <ListSurface :title="t.month_plan.expense_table">
                <div
                    class="border-border/60 text-muted-foreground grid grid-cols-[1fr_5.5rem_5.5rem] gap-3 border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase sm:grid-cols-[1fr_9rem_9rem] lg:grid-cols-[1fr_9rem_9rem_9rem]"
                >
                    <span>{{ t.month_plan.category }}</span>
                    <span class="text-right">{{ t.month_plan.plan }}</span>
                    <span class="text-right">{{ t.month_plan.actual }}</span>
                    <span class="hidden text-right lg:block">
                        {{ t.month_plan.deviation }}
                    </span>
                </div>
                <MonthPlanItemRow
                    v-for="row in expenseRows"
                    :key="row.category_id"
                    :row="row"
                    :editable="false"
                    :deviation="rowReading(row).deviation"
                    :over-plan="rowReading(row).overPlan"
                />
            </ListSurface>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Wiersze: edycja</h2>
            <ListSurface :title="t.month_plan.expense_table">
                <MonthPlanItemRow
                    v-for="row in [runningExpense, overPlanExpense]"
                    :key="row.category_id"
                    v-model="amounts[row.category_id]"
                    :row="row"
                    :editable="true"
                    :deviation="rowReading(row).deviation"
                    :over-plan="rowReading(row).overPlan"
                />
            </ListSurface>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Przełączniki (stan obecny)</h2>
            <div
                role="group"
                :aria-label="t.month_plan.set_aside_direction"
                class="border-border/60 bg-muted/40 inline-flex gap-1 rounded-xl border p-1"
            >
                <Button
                    v-for="option in ['set_aside', 'draw'] as const"
                    :key="option"
                    type="button"
                    size="sm"
                    :variant="direction === option ? 'default' : 'ghost'"
                    :aria-pressed="direction === option"
                    class="h-7 rounded-lg px-2 text-xs"
                    @click="direction = option"
                >
                    {{
                        option === 'set_aside'
                            ? t.month_plan.set_aside_direction
                            : t.month_plan.set_aside
                    }}
                </Button>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Puste stany</h2>
            <IndexEmptyState
                :title="t.month_plan.no_budget_title"
                :description="t.month_plan.no_budget_description"
            />
            <IndexEmptyState
                :title="t.month_plan.draft_title"
                :description="t.month_plan.draft_description"
            />
            <IndexEmptyState
                :title="t.month_plan.none_title"
                :description="t.month_plan.none_description"
            />
            <p class="text-muted-foreground py-6 text-center text-sm">
                {{ t.month_plan.empty_list }}
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Macierz stanów</h2>
            <section
                v-for="state in stateMatrix"
                :key="state"
                class="space-y-1"
            >
                <h3 class="text-sm font-medium">{{ state }}</h3>
                <p class="text-muted-foreground text-xs">
                    Do wypełnienia w kolejnych fazach.
                </p>
            </section>
        </section>
    </div>
</template>
