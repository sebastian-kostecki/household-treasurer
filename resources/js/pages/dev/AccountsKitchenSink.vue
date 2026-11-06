<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent, ref } from 'vue';
import AccountFormDialog from '@/components/AccountFormDialog.vue';
import AccountAvatar from '@/components/AccountAvatar.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    type AccountBank,
    bankLabel,
    bankOptions,
} from '@/lib/account-identity';
import { accountsScenarios } from './accounts-fixtures';

const Accounts = defineAsyncComponent(() => import('@/pages/Accounts.vue'));

const createDialogOpen = ref(false);

const t = useTranslations();

const bankMarkUrls: Record<AccountBank, string | null> = {
    alior: '/marks/alior.svg',
    bnp_paribas: '/marks/bnp-paribas.jpg',
    ing: null,
    lokaty_facto: '/marks/lokaty-facto.svg',
    mbank: '/marks/mbank.jpg',
    pekao: null,
    pko_bp: '/marks/pko-bp.svg',
    revolut: '/marks/revolut.svg',
    santander: null,
    other: null,
};

const bankMarkTiles = [
    ...bankOptions.map((bank) => ({
        key: bank,
        label: bankLabel(t.accounts, bank),
        type: 'bank' as const,
        bank,
        mark_url: bankMarkUrls[bank],
    })),
    {
        key: 'cash',
        label: t.accounts.type_cash,
        type: 'cash' as const,
        bank: null,
        mark_url: null,
    },
    {
        key: 'no-bank',
        label: 'Bank bez znaku',
        type: 'bank' as const,
        bank: null,
        mark_url: null,
    },
    {
        key: 'image-error',
        label: 'Błąd obrazu (zły URL, skrót zamiast logo)',
        type: 'bank' as const,
        bank: 'pko_bp' as const,
        mark_url: '/marks/missing-logo.svg',
    },
];

const avatarSizes = ['xs', 'sm', 'md'] as const;

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze default (panel z dwiema miarami), zero-total, negative-total, with-archived (Switch włączony), archived-negative (zarchiwizowane z ujemnym i dodatnim saldem) i long-names (zawijanie nazw przy 390 px) poniżej; Switch stoi w wierszu tytułu przed „Dodaj konto” i zawija się razem z nim przy 390 px; wyłączony w default i all-archived.',
    },
    {
        state: 'hover',
        where: 'Zrzuty z działającej aplikacji (stan zależny od interakcji): wiersze listy i IconAction.',
    },
    {
        state: 'focus-visible',
        where: 'Zrzuty po Tab z działającej aplikacji: kolejność Tab: przełącznik (Switch), potem przycisk „Dodaj konto”, dalej IconAction, X dialogu, wyzwalacz banku.',
    },
    {
        state: 'disabled',
        where: 'Zrzuty z opóźnionym POST (przycisk potwierdzenia ze spinnerem, zablokowane Anuluj) oraz przycisk poniżej: Zapisz jest zablokowany przy nieparsowalnej kwocie.',
    },
    {
        state: 'error',
        where: 'Przycisk poniżej otwiera formularz konta: wpisz 12,3,4 w saldzie otwarcia. Błąd serwera errors.account przy usuwaniu: test Feature destroy z historią i sprawdzenie na nieaktualnej stronie.',
    },
    {
        state: 'empty',
        where: 'Scenariusze empty-new-user (brak kont, bez przełącznika) i all-archived (wszystkie zarchiwizowane) poniżej.',
    },
    {
        state: 'loading',
        where: 'Zrzut z opóźnioną odpowiedzią: przełącznik zablokowany w trakcie wizyty. Skeleton N/A, bo strona nie ma deferred props.',
    },
];
</script>

<template>
    <Head title="Accounts kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Accounts kitchen sink</h1>
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

        <section class="space-y-3" data-section="bank-marks">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                Znaki banków
            </h2>
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="tile in bankMarkTiles"
                    :key="tile.key"
                    class="flex items-center gap-3 text-sm"
                >
                    <AccountAvatar
                        v-for="size in avatarSizes"
                        :key="size"
                        :type="tile.type"
                        :bank="tile.bank"
                        :mark_url="tile.mark_url"
                        :size="size"
                    />
                    <span>{{ tile.label }}</span>
                </li>
            </ul>
        </section>

        <section class="space-y-2" data-section="error-form">
            <h2
                class="text-muted-foreground border-b pb-1 font-mono text-sm font-semibold"
            >
                error / disabled — formularz konta
            </h2>
            <Button @click="createDialogOpen = true">
                Otwórz formularz konta
            </Button>
            <AccountFormDialog v-model:open="createDialogOpen" mode="create" />
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
