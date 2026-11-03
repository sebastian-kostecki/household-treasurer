<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import MonthPlanEditBar from '@/components/MonthPlanEditBar.vue';
import MonthPlanItemRow from '@/components/MonthPlanItemRow.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Badge } from '@/components/ui/badge';
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

const contractKinds = [
    { value: 'current', label: 'Bieżący' },
    { value: 'bill', label: 'Rachunek' },
];
const primaryValue = ref('current');
const neutralValue = ref('bill');
const disabledValue = ref('current');
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
                    class="month-plan-grid month-plan-grid-kind border-border/60 border-b pb-2.5"
                >
                    <span class="column-header">
                        {{ t.month_plan.category }}
                    </span>
                    <span class="column-header hidden sm:block">
                        {{ t.month_plan.kind_column }}
                    </span>
                    <span class="column-header text-right">
                        {{ t.month_plan.plan }}
                    </span>
                    <span class="column-header text-right">
                        {{ t.month_plan.actual }}
                    </span>
                    <span class="column-header hidden text-right lg:block">
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
            <h2 class="text-base font-medium">Wiersz tytułu</h2>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold capitalize">
                        czerwiec 2026
                    </h3>
                    <Badge variant="secondary">
                        {{ t.month_plan.state_approved }}
                    </Badge>
                </div>
                <Button type="button">{{ t.month_plan.change }}</Button>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold capitalize">
                        lipiec 2026
                    </h3>
                    <Badge variant="outline">
                        {{ t.month_plan.state_proposal }}
                    </Badge>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Pasek edycji</h2>
            <p class="text-muted-foreground text-sm">
                W widoku pasek jest przyklejony do dołu ekranu.
            </p>
            <MonthPlanEditBar
                :to-spend-minor-units="373000"
                mode="approve"
                :processing="false"
                :can-submit="true"
            />
            <MonthPlanEditBar
                :to-spend-minor-units="-12000"
                mode="change"
                :processing="false"
                :can-submit="true"
            />
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">Kontrakt: SegmentedControl</h2>
            <p class="text-muted-foreground text-sm">
                Tab: jeden przystanek na przełącznik. Strzałki, Home i End
                zmieniają fokus, Spacja i Enter wybierają. Kliknięcie aktywnej
                opcji jej nie odznacza. Najedź, by zobaczyć hover.
            </p>
            <div class="flex flex-wrap items-center gap-6">
                <div class="space-y-1">
                    <p class="section-label">primary</p>
                    <SegmentedControl
                        v-model="primaryValue"
                        :options="contractKinds"
                        ariaLabel="Ton primary"
                        tone="primary"
                    />
                </div>
                <div class="space-y-1">
                    <p class="section-label">neutral</p>
                    <SegmentedControl
                        v-model="neutralValue"
                        :options="contractKinds"
                        ariaLabel="Ton neutral"
                        tone="neutral"
                    />
                </div>
                <div class="space-y-1">
                    <p class="section-label">disabled</p>
                    <SegmentedControl
                        v-model="disabledValue"
                        :options="contractKinds"
                        ariaLabel="Wyłączony"
                        disabled
                    />
                </div>
            </div>
            <p class="text-muted-foreground text-xs">
                Wartości: primary={{ primaryValue }}, neutral={{ neutralValue }}
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-medium">
                Kontrakt: .month-plan-grid i .column-header
            </h2>
            <ListSurface :title="t.month_plan.expense_table">
                <div class="month-plan-grid border-border/60 border-b pb-2.5">
                    <span class="column-header">
                        {{ t.month_plan.category }}
                    </span>
                    <span class="column-header text-right">
                        {{ t.month_plan.plan }}
                    </span>
                    <span class="column-header text-right">
                        {{ t.month_plan.actual }}
                    </span>
                    <span class="column-header hidden text-right lg:block">
                        {{ t.month_plan.deviation }}
                    </span>
                </div>
                <div
                    class="month-plan-grid border-border/60 items-center border-b py-3"
                >
                    <span class="min-w-0 text-sm">
                        Bardzo długa nazwa kategorii, która musi się zawinąć bez
                        rozpychania kolumn z kwotami
                    </span>
                    <span class="text-right text-sm tabular-nums">
                        1 234,56 zł
                    </span>
                    <span class="text-right text-sm tabular-nums">
                        999,00 zł
                    </span>
                    <span
                        class="hidden text-right text-sm tabular-nums lg:block"
                    >
                        235,56 zł
                    </span>
                </div>
            </ListSurface>
            <ListSurface :title="t.month_plan.expense_table">
                <div
                    class="month-plan-grid month-plan-grid-kind border-border/60 border-b pb-2.5"
                >
                    <span class="column-header">
                        {{ t.month_plan.category }}
                    </span>
                    <span class="column-header hidden sm:block">
                        {{ t.month_plan.kind }}
                    </span>
                    <span class="column-header text-right">
                        {{ t.month_plan.plan }}
                    </span>
                    <span class="column-header text-right">
                        {{ t.month_plan.actual }}
                    </span>
                    <span class="column-header hidden text-right lg:block">
                        {{ t.month_plan.deviation }}
                    </span>
                </div>
                <div
                    class="month-plan-grid month-plan-grid-kind border-border/60 items-center border-b py-3"
                >
                    <span class="min-w-0 text-sm">
                        Bardzo długa nazwa kategorii, która musi się zawinąć bez
                        rozpychania kolumn z kwotami i rodzajem
                    </span>
                    <span class="hidden sm:block">
                        <SegmentedControl
                            v-model="primaryValue"
                            :options="contractKinds"
                            ariaLabel="Rodzaj"
                            tone="neutral"
                        />
                    </span>
                    <span class="text-right text-sm tabular-nums">
                        1 234,56 zł
                    </span>
                    <span class="text-right text-sm tabular-nums">
                        999,00 zł
                    </span>
                    <span
                        class="hidden text-right text-sm tabular-nums lg:block"
                    >
                        235,56 zł
                    </span>
                </div>
            </ListSurface>
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
