<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import { termDepositsScenarios } from './term-deposits-fixtures';

const TermDeposits = defineAsyncComponent(
    () => import('@/pages/TermDeposits.vue'),
);

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze default, zero-total i negative-total poniżej.',
    },
    {
        state: 'empty',
        where: 'Scenariusze empty-new-user i all-archived poniżej.',
    },
    {
        state: 'loading',
        where: 'N/A: strona nie ma deferred props ani skeletonów.',
    },
    {
        state: 'error',
        where: 'Zrzuty z działającej aplikacji (InputError w TermDepositFormDialog).',
    },
    {
        state: 'disabled',
        where: 'Zrzuty z działającej aplikacji (processing na formularzach).',
    },
    {
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji).',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji).',
    },
];
</script>

<template>
    <Head title="Term deposits kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Term deposits kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Widok Lokaty w
                każdym scenariuszu danych. Zduplikowane id pól w kilku
                TermDepositFormDialog są tu dopuszczalne. Akcje formularzy nie
                działają, bo fixture'ów nie ma w bazie. Przełącznik „Pokaż
                zarchiwizowane” (a później także przycisk o tej nazwie w pustym
                stanie) przechodzi na prawdziwe /term-deposits.
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
                        <th class="column-caption pb-2.5 text-left">Stan</th>
                        <th class="column-caption pb-2.5 text-left">
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
        </section>

        <section
            v-for="scenario in termDepositsScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                {{ scenario.title }}
            </h2>
            <TermDeposits v-bind="scenario.props" />
        </section>
    </div>
</template>
