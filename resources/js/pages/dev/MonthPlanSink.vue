<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import MonthPlanEditBar from '@/components/MonthPlanEditBar.vue';
import MonthPlanItemRow from '@/components/MonthPlanItemRow.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

const focusAmount = ref('1200,50');
const invalidSetAside = ref('abc');

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

        <section class="space-y-6">
            <h2 class="text-base font-medium">Macierz stanów</h2>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">default</h3>
                <p class="text-muted-foreground text-xs">
                    Wiersze odczytu i edycji, pasek edycji i wiersz tytułu:
                    sekcje powyżej.
                </p>
            </section>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">hover</h3>
                <p class="text-muted-foreground text-xs">
                    Najedź na przełącznik, przycisk i wiersz zwijania
                    jednorazowych.
                </p>
                <div class="flex flex-wrap items-center gap-4">
                    <SegmentedControl
                        v-model="neutralValue"
                        :options="contractKinds"
                        ariaLabel="Hover: przełącznik"
                        tone="neutral"
                    />
                    <Button type="button">{{ t.month_plan.change }}</Button>
                    <Button type="button" variant="outline">
                        {{ t.month_plan.cancel }}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="gap-2 px-0 hover:bg-transparent"
                    >
                        <span class="text-sm font-medium">
                            {{ t.month_plan.idle_one_offs }}
                        </span>
                        <Badge variant="secondary">1</Badge>
                    </Button>
                </div>
            </section>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">focus-visible</h3>
                <p class="text-muted-foreground text-xs">
                    Przejdź klawiszem Tab przez przełącznik, pole kwoty i
                    przyciski paska.
                </p>
                <div class="flex flex-wrap items-center gap-4">
                    <SegmentedControl
                        v-model="neutralValue"
                        :options="contractKinds"
                        ariaLabel="Fokus: przełącznik"
                        tone="neutral"
                    />
                    <Input
                        v-model="focusAmount"
                        inputmode="decimal"
                        autocomplete="off"
                        aria-label="Fokus: kwota"
                        class="w-40 text-right tabular-nums"
                    />
                </div>
                <MonthPlanEditBar
                    :to-spend-minor-units="373000"
                    mode="change"
                    :processing="false"
                    :can-submit="true"
                />
            </section>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">disabled</h3>
                <p class="text-muted-foreground text-xs">
                    Przycisk paska przy błędnej kwocie z podpisem liczby i
                    przełącznik rodzaju w czasie zapisu.
                </p>
                <MonthPlanEditBar
                    :to-spend-minor-units="373000"
                    mode="approve"
                    :processing="false"
                    :can-submit="false"
                    :invalid-count="1"
                />
                <ListSurface :title="t.month_plan.expense_table">
                    <MonthPlanItemRow
                        :row="runningExpense"
                        :editable="true"
                        :deviation="runningExpense.deviation_minor_units"
                        :over-plan="false"
                        :kind-pending="true"
                        model-value="2000,00"
                    />
                </ListSurface>
            </section>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">error</h3>
                <p class="text-muted-foreground text-xs">
                    Pole kwoty z aria-invalid i komunikatem, pole odkładania,
                    błąd rodzaju w wierszu i błąd listy w pasku.
                </p>
                <ListSurface :title="t.month_plan.expense_table">
                    <MonthPlanItemRow
                        :row="runningExpense"
                        :editable="true"
                        :deviation="null"
                        :over-plan="false"
                        :invalid="true"
                        :kind-error="'Nie udało się zapisać rodzaju pozycji.'"
                        model-value="12,3,4"
                    />
                </ListSurface>
                <div class="grid gap-1">
                    <Input
                        v-model="invalidSetAside"
                        inputmode="decimal"
                        autocomplete="off"
                        :aria-label="t.month_plan.set_aside_amount"
                        aria-invalid="true"
                        aria-describedby="sink-set-aside-error"
                        class="w-40 text-right tabular-nums"
                    />
                    <InputError
                        id="sink-set-aside-error"
                        :message="t.month_plan.amount_invalid"
                    />
                </div>
                <MonthPlanEditBar
                    :to-spend-minor-units="373000"
                    mode="approve"
                    :processing="false"
                    :can-submit="false"
                    :invalid-count="2"
                    error="Dodaj co najmniej jedną pozycję planu."
                />
            </section>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">empty</h3>
                <p class="text-muted-foreground text-xs">
                    Puste stany: sekcja „Puste stany” powyżej (brak budżetu,
                    szkic, miniony bez planu i „Brak pozycji w tym miesiącu”).
                </p>
            </section>

            <section class="space-y-2">
                <h3 class="text-sm font-medium">loading</h3>
                <p class="text-muted-foreground text-xs">
                    Przełącznik rodzaju ze Spinner (wyżej, w „disabled”) i pasek
                    z processing. Zmiana miesiąca to nawigacja Inertia z paskiem
                    postępu, a propsy przychodzą w całości: brak skeletonu i
                    przeskoku układu, więc N/A.
                </p>
                <MonthPlanEditBar
                    :to-spend-minor-units="373000"
                    mode="change"
                    :processing="true"
                    :can-submit="true"
                />
            </section>
        </section>
    </div>
</template>
