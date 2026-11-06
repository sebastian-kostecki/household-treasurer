<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import DashboardBudgetBlock from '@/components/DashboardBudgetBlock.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { notifyBudgetYearSelectionFailed } from '@/lib/budget-year-selection';
import { dashboardScenarios } from './dashboard-fixtures';

const Dashboard = defineAsyncComponent(() => import('@/pages/Dashboard.vue'));
const t = useTranslations();

const overrunScenario = dashboardScenarios.find(
    (scenario) => scenario.key === 'overrun',
);
const overrunProps = overrunScenario?.props ?? null;
const overrunBudget = computed(() => overrunProps?.budget ?? null);
const switchStates = [
    { key: 'on', caption: 'Switch wł.', model: ref(true) },
    { key: 'off', caption: 'Switch wył.', model: ref(false) },
];

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze default, zero-amounts, negative, many-categories (ponad 5 kategorii w grupie), income-over-100, overrun („przekroczono” obok procentu i w sumach, grupa wydatków ponad 100%), all-notice-kinds (7 rodzajów, „Pokaż wszystkie”), deposits-only, month-start, month-end, pace-unknown i no-month-plan (3 miary) poniżej. Oba stany Switcha „Postęp roku”: sekcja year-mark-switch.',
    },
    {
        state: 'empty',
        where: 'Scenariusze empty-new-user, no-budget, draft i no-categories poniżej.',
    },
    {
        state: 'loading',
        where: 'N/A: strona nie ma deferred props, a skeletony wyklucza dashboard-hierarchy.md:78. Jedyny „loading” to processing, pokazany jako disabled.',
    },
    {
        state: 'error',
        where: 'Przycisk „Pokaż błąd wyboru roku” poniżej (toast); InputError w CreateBudgetDialog w zrzutach z działającej aplikacji.',
    },
    {
        state: 'disabled',
        where: 'Zrzuty z fazy 6 przy spowolnionej sieci (link Plan miesiąca, „Otwórz budżet”, karta ostrzeżenia z POST).',
    },
    {
        state: 'hover',
        where: 'Zrzuty z fazy 6 z działającej aplikacji (wiersz kategorii, karta alertu).',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty z fazy 6 z działającej aplikacji, kolejność Tab: Switch „Postęp roku”, „Otwórz budżet”, wiersze kategorii, karty alertów.',
    },
];

function showYearSelectionError(): void {
    notifyBudgetYearSelectionFailed(t.dashboard.select_year_failed);
}
</script>

<template>
    <Head title="Dashboard kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Dashboard kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Widok Dashboard w
                każdym scenariuszu danych. Checklista pierwszego uruchomienia i
                lista lat budżetu (shell.budgetYears) pokazują stan aktualnie
                zalogowanego użytkownika, nie fixture. Zduplikowane id pól roku
                w CreateBudgetDialog są tu dopuszczalne.
            </p>
        </header>

        <section class="space-y-3" data-section="state-matrix">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                Macierz stanów
            </h2>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="table-header pb-2.5 text-left">Stan</th>
                        <th class="table-header pb-2.5 text-left">
                            Gdzie widoczny
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in stateMatrix"
                        :key="row.state"
                        class="border-t align-top"
                    >
                        <td class="py-2 pr-4 font-mono">{{ row.state }}</td>
                        <td class="py-2">{{ row.where }}</td>
                    </tr>
                </tbody>
            </table>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="showYearSelectionError"
            >
                Pokaż błąd wyboru roku
            </Button>
        </section>

        <section class="space-y-3" data-section="year-mark-switch">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                year-mark-switch (blok z danych overrun, stan Switcha lokalny)
            </h2>
            <template v-if="overrunProps && overrunBudget">
                <div
                    v-for="state in switchStates"
                    :key="state.key"
                    :data-switch-state="state.key"
                    class="space-y-2"
                >
                    <p class="text-muted-foreground font-mono text-xs">
                        {{ state.caption }}
                    </p>
                    <DashboardBudgetBlock
                        v-model:show-year-elapsed-mark="state.model.value"
                        :budget="overrunBudget"
                        :budget-year="overrunProps.budget_year"
                        :plan-summary="overrunProps.planSummary"
                        :income-categories="overrunProps.incomeCategories"
                        :expense-categories="overrunProps.expenseCategories"
                        :income-totals="overrunProps.incomeTotals"
                        :expense-totals="overrunProps.expenseTotals"
                        :year-elapsed-percent="
                            overrunProps.year_elapsed_percent
                        "
                        :can-select-current-year-in-shell="true"
                        @error="showYearSelectionError"
                    />
                </div>
            </template>
        </section>

        <section
            v-for="scenario in dashboardScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                {{ scenario.title }}
            </h2>
            <Dashboard v-bind="scenario.props" />
        </section>
    </div>
</template>
