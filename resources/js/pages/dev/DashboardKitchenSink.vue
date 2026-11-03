<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Dashboard from '@/pages/Dashboard.vue';
import { dashboardScenarios } from './dashboard-fixtures';
</script>

<template>
    <Head title="Dashboard kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Dashboard kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Widok Dashboard w
                każdym scenariuszu danych. Checklista pierwszego uruchomienia i
                lista lat budżetu (shell.budgetYears) pokazują stan aktualnie
                zalogowanego użytkownika, nie fixture. Zduplikowane id pól roku
                w CreateBudgetDialog są tu dopuszczalne.
            </p>
        </header>

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
