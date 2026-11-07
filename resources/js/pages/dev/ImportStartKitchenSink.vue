<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent, reactive } from 'vue';
import ImportColumnMapping from '@/components/ImportColumnMapping.vue';
import ImportRecognizedFields from '@/components/ImportRecognizedFields.vue';
import {
    columnLabelsWithHeader,
    columnLabelsWithoutHeader,
    importStartAccounts,
    importStartErrors,
    importStartNoAccounts,
    noImportStartErrors,
} from './import-start-fixtures';

const OperationsImport = defineAsyncComponent(
    () => import('@/pages/OperationsImport.vue'),
);

function mappingState(amountLayout: 'signed' | 'split') {
    return reactive({
        hasHeader: true,
        amountLayout,
        dateColumn: 0 as number | '',
        descriptionColumn: 1 as number | '',
        counterpartyColumn: '' as number | '',
        amountColumn: 3 as number | '',
        inflowColumn: 4 as number | '',
        outflowColumn: 5 as number | '',
    });
}

const mappingScenarios = [
    {
        key: 'mapping-no-file',
        title: 'ImportColumnMapping — bez pliku (sam checkbox)',
        state: mappingState('signed'),
        labels: [] as string[],
        errors: noImportStartErrors,
    },
    {
        key: 'mapping-signed',
        title: 'ImportColumnMapping — kwota ze znakiem, z nagłówkiem',
        state: mappingState('signed'),
        labels: columnLabelsWithHeader,
        errors: noImportStartErrors,
    },
    {
        key: 'mapping-split',
        title: 'ImportColumnMapping — wpływ i wypływ, bez nagłówka',
        state: mappingState('split'),
        labels: columnLabelsWithoutHeader,
        errors: noImportStartErrors,
    },
    {
        key: 'mapping-errors',
        title: 'ImportColumnMapping — błędy walidacji',
        state: mappingState('signed'),
        labels: columnLabelsWithHeader,
        errors: importStartErrors,
    },
];

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze import-with-accounts (start „Inny”), recognized-mbank, recognized-bnp, mapping-no-file, mapping-signed i mapping-split poniżej.',
    },
    {
        state: 'hover',
        where: 'Zrzut z działającej aplikacji: screenshots/import-hover-submit-1280-light.png (przycisk podglądu).',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty po Tab z działającej aplikacji: screenshots/import-focus-file-1280-light.png (pole pliku) i import-focus-segment-1280-light.png (układ kwoty, strzałki).',
    },
    {
        state: 'disabled',
        where: 'Przycisk podglądu w scenariuszu import-with-accounts poniżej: bez wybranego pliku jest zablokowany.',
    },
    {
        state: 'error',
        where: 'Scenariusz mapping-errors poniżej: błędy has_header, date_column i amount_layout. Błąd pliku daje tylko serwer w działającej aplikacji.',
    },
    {
        state: 'empty',
        where: 'Scenariusz import-no-accounts poniżej: brak kont, stan pusty z przejściem do Kont.',
    },
    {
        state: 'loading',
        where: 'Zrzut z opóźnionym POST: screenshots/import-loading-1280-light.png (przycisk podglądu ze spinnerem). Skeleton N/A, bo strona nie ma deferred props.',
    },
];
</script>

<template>
    <Head title="Import start kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Import start kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Krok 1 importu CSV
                i XLSX w każdym scenariuszu danych. Fixture'y nie istnieją w
                bazie, więc wysłanie formularza nie zadziała.
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
        </section>

        <section class="space-y-2" data-scenario="import-with-accounts">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                Strona z kontami (start „Inny”, bez pliku)
            </h2>
            <OperationsImport :accounts="importStartAccounts" />
        </section>

        <section class="space-y-2" data-scenario="import-no-accounts">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                Strona bez kont (stan pusty)
            </h2>
            <OperationsImport :accounts="importStartNoAccounts" />
        </section>

        <section class="space-y-2" data-scenario="recognized-mbank">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                ImportRecognizedFields — mBank
            </h2>
            <ImportRecognizedFields format="mbank" />
        </section>

        <section class="space-y-2" data-scenario="recognized-bnp">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                ImportRecognizedFields — BNP Paribas
            </h2>
            <ImportRecognizedFields format="bnp_paribas" />
        </section>

        <section
            v-for="scenario in mappingScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                {{ scenario.title }}
            </h2>
            <ImportColumnMapping
                v-model:has-header="scenario.state.hasHeader"
                v-model:amount-layout="scenario.state.amountLayout"
                v-model:date-column="scenario.state.dateColumn"
                v-model:description-column="scenario.state.descriptionColumn"
                v-model:counterparty-column="scenario.state.counterpartyColumn"
                v-model:amount-column="scenario.state.amountColumn"
                v-model:inflow-column="scenario.state.inflowColumn"
                v-model:outflow-column="scenario.state.outflowColumn"
                :column-labels="scenario.labels"
                :errors="scenario.errors"
            />
        </section>
    </div>
</template>
