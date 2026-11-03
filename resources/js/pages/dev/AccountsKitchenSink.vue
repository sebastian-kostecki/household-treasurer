<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import { accountsScenarios } from './accounts-fixtures';

const Accounts = defineAsyncComponent(() => import('@/pages/Accounts.vue'));

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze default, with-archived, zero-total i negative-total poniżej.',
    },
    {
        state: 'empty',
        where: 'Scenariusze empty-new-user i all-archived poniżej.',
    },
    {
        state: 'loading',
        where: 'Do uzupełnienia w fazie 4.',
    },
    {
        state: 'error',
        where: 'Do uzupełnienia w fazie 4.',
    },
    {
        state: 'disabled',
        where: 'Do uzupełnienia w fazie 4.',
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
    <Head title="Accounts kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Accounts kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Widok Konta w
                każdym scenariuszu danych. Fixture'y nie istnieją w bazie, więc
                akcje formularzy (dodaj, edytuj, usuń, archiwizuj, przywróć) nie
                zadziałają. Zduplikowane id pól w kilku AccountFormDialog są tu
                dopuszczalne.
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
            v-for="scenario in accountsScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                {{ scenario.title }}
            </h2>
            <Accounts v-bind="scenario.props" />
        </section>
    </div>
</template>
