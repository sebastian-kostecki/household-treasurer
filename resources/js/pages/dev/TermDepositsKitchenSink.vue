<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent, ref } from 'vue';
import TermDepositFormDialog from '@/components/TermDepositFormDialog.vue';
import { Button } from '@/components/ui/button';
import { termDepositsScenarios } from './term-deposits-fixtures';

const TermDeposits = defineAsyncComponent(
    () => import('@/pages/TermDeposits.vue'),
);

const errorDialogOpen = ref(false);

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze default, zero-total, negative-total, with-archived i with-archived-negative poniżej (stopka sumy „W tym zarchiwizowane”, zarchiwizowane saldo ujemne na czerwono w wierszu i w stopce).',
    },
    {
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji): wiersze listy i IconAction.',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty z działającej aplikacji po Tab: przycisk „Dodaj lokatę”, przełącznik, IconAction, X dialogu, wybór banku.',
    },
    {
        state: 'disabled',
        where: 'Zrzuty z działającej aplikacji z opóźnionym POST (przycisk potwierdzenia i Zapisz ze spinnerem, zablokowane Anuluj w potwierdzeniu) oraz Zapisz zablokowany przy nieparsowalnej kwocie.',
    },
    {
        state: 'error',
        where: 'Przycisk „Otwórz formularz lokaty” poniżej: wpisz 12,3,4 w saldzie otwarcia, by zobaczyć komunikat. errors.term_deposit przy usuwaniu: test test_user_cannot_delete_term_deposit_with_capital_movements i ręczne sprawdzenie na nieaktualnej stronie.',
    },
    {
        state: 'empty',
        where: 'Scenariusze empty-new-user i all-archived poniżej.',
    },
    {
        state: 'loading',
        where: 'Przełącznik zablokowany w trakcie wizyty: zrzut z działającej aplikacji z opóźnioną odpowiedzią. Skeleton N/A, bo strona nie ma deferred props.',
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
                zarchiwizowane” (i przycisk o tej nazwie w pustym stanie)
                przechodzi na prawdziwe /term-deposits.
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

        <section class="space-y-2" data-section="error-form">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                error: formularz lokaty (tryb tworzenia)
            </h2>
            <Button variant="secondary" @click="errorDialogOpen = true">
                Otwórz formularz lokaty
            </Button>
            <TermDepositFormDialog
                v-model:open="errorDialogOpen"
                mode="create"
            />
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
